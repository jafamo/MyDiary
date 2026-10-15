<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Entity\AudioRecording;
use App\Entity\ApiToken;
use App\Entity\AudioRecordingStatus;
use App\Form\TranscriptionEditType;
use App\Security\ApiTokenHandler;
use App\Service\AudioFileMissingException;
use App\Service\AudioFileResponder;
use App\Service\AudioRecordingDeleter;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioRecordingService;
use App\Service\AudioUploadService;
use App\Service\InvalidAudioUploadException;
use App\Service\TranscriptionEditor;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Audios')]
class AudioController
{
    private const RESULT_CREATED = 'created';
    private const RESULT_DUPLICATE = 'duplicate';
    private const RESULT_RETRYING = 'retrying';

    public function __construct(
        private readonly AudioUploadService $audioUploadService,
        private readonly AudioPresenter $audioPresenter,
        private readonly AudioFileResponder $audioFileResponder,
        private readonly LoggerInterface $logger,
        private readonly ApiForm $apiForm,
        private readonly TranscriptionEditor $transcriptionEditor,
        private readonly AudioRecordingDeleter $audioRecordingDeleter,
        private readonly AudioRecordingService $audioRecordingService,
    ) {
    }

    #[Route('/api/v1/audios', name: 'api_audio_upload', methods: ['POST'])]
    #[OA\Post(
        summary: 'Subir un audio',
        description: 'Guarda el audio y lo pone en cola para transcribirlo, igual que uno recibido por Telegram. El formato se determina por el contenido del fichero (no por su extensión ni su `Content-Type`) y la duración se calcula en el servidor. Subir de nuevo el mismo fichero no crea otro audio: devuelve el existente (`duplicate`) o, si su transcripción había fallado, la relanza (`retrying`). Una petición de más de 30 MB la rechaza el servidor web con un `413` que no sigue el formato de error de la API.',
    )]
    #[OA\RequestBody(required: true, content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['file'],
            properties: [
                new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Audio en formato m4a, mp3, ogg o wav, de 25 MB como máximo.'),
            ],
        ),
    ))]
    #[OA\Response(response: 201, description: 'Audio nuevo, en cola para transcribirse', content: new OA\JsonContent(ref: '#/components/schemas/AudioUpload'))]
    #[OA\Response(response: 200, description: 'El audio ya existía: sin cambios (`duplicate`) o relanzado tras un fallo (`retrying`)', content: new OA\JsonContent(ref: '#/components/schemas/AudioUpload'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta el fichero, la subida llegó incompleta, supera 25 MB o no es un audio admitido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            throw ApiException::validationFailed('El campo "file" es obligatorio y debe ser un fichero.');
        }
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            // El límite de PHP coincide con el de la aplicación: es el primero en cortar un fichero grande.
            throw ApiException::validationFailed(AudioUploadService::tooLargeMessage());
        }
        if (!$file->isValid()) {
            throw ApiException::validationFailed('La subida del fichero no se ha completado. Inténtalo de nuevo.');
        }

        try {
            $apiToken = $request->attributes->get(ApiTokenHandler::REQUEST_ATTRIBUTE);
            $upload = $this->audioUploadService->upload(
                $file->getPathname(),
                $apiToken instanceof ApiToken ? $apiToken->getName() : null,
            );
        } catch (InvalidAudioUploadException $exception) {
            throw ApiException::validationFailed($exception->getMessage());
        }

        $audioRecording = $upload->audioRecording;
        $result = match ($upload->result) {
            AudioRecordingReceiveResult::CREATED => self::RESULT_CREATED,
            AudioRecordingReceiveResult::RETRYING_AFTER_ERROR => self::RESULT_RETRYING,
            default => self::RESULT_DUPLICATE,
        };

        $this->logger->info('Audio subido por la API', [
            'event' => 'audio.uploaded',
            'audio_recording_id' => $audioRecording->getId(),
            'audio_source' => $audioRecording->getSource()->value,
            'audio_content_hash' => $audioRecording->getContentHash(),
            'audio_upload_result' => $result,
        ]);

        return new JsonResponse(
            $this->audioPresenter->brief($audioRecording) + ['result' => $result],
            self::RESULT_CREATED === $result ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route('/api/v1/audios/{id}/fichero', name: 'api_audio_file', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Get(
        summary: 'Descargar el fichero de un audio',
        description: 'Devuelve el audio original tal como se guardó (`ogg` en los de Telegram; `m4a`, `mp3`, `ogg` o `wav` en los subidos desde la app). Admite la cabecera `Range` para reproducir con salto: con un rango válido responde `206` con ese tramo y `Content-Range`. Los audios de Telegram son Ogg/Opus y no se convierten: `AVPlayer` y Safari anterior a iOS 17.4 no los reproducen.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identificador del audio', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'Range', in: 'header', required: false, description: 'Tramo de bytes, p. ej. `bytes=0-1023`', schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Fichero completo', content: [
        new OA\MediaType(mediaType: 'audio/ogg', schema: new OA\Schema(type: 'string', format: 'binary')),
        new OA\MediaType(mediaType: 'audio/mp4', schema: new OA\Schema(type: 'string', format: 'binary')),
        new OA\MediaType(mediaType: 'audio/mpeg', schema: new OA\Schema(type: 'string', format: 'binary')),
        new OA\MediaType(mediaType: 'audio/wav', schema: new OA\Schema(type: 'string', format: 'binary')),
    ])]
    #[OA\Response(response: 206, description: 'Tramo pedido con `Range`, con su cabecera `Content-Range`')]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'El audio no existe o su fichero no está en el servidor', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 416, description: 'El rango pedido está fuera del fichero')]
    public function file(AudioRecording $audioRecording): BinaryFileResponse
    {
        try {
            return $this->audioFileResponder->respond($audioRecording);
        } catch (AudioFileMissingException) {
            throw ApiException::notFound('El fichero de este audio no está disponible.');
        }
    }

    #[Route('/api/v1/audios/{id}/transcripcion', name: 'api_audio_transcription_edit', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Editar la transcripción de un audio',
        description: 'Sustituye el texto de la transcripción, la marca como editada a mano y regenera su embedding para la búsqueda. Solo para audios en estado `TRANSCRIBED`.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identificador del audio', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['content'],
        properties: [
            new OA\Property(property: 'content', type: 'string', example: 'Hoy he ido al dentista a las diez.'),
        ],
    ))]
    #[OA\Response(response: 200, description: 'Audio con la transcripción editada', content: new OA\JsonContent(ref: '#/components/schemas/Audio'))]
    #[OA\Response(response: 400, description: 'El cuerpo no es un objeto JSON', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'El audio no existe', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'El audio no está transcrito', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta `content`, está en blanco o hay campos no admitidos', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function editTranscription(AudioRecording $audioRecording, Request $request): JsonResponse
    {
        $transcription = $audioRecording->getTranscription();
        if (AudioRecordingStatus::TRANSCRIBED !== $audioRecording->getStatus() || null === $transcription) {
            throw ApiException::conflict('Solo se puede editar la transcripción de un audio transcrito.');
        }

        $this->apiForm->submit(TranscriptionEditType::class, $transcription, $request);
        $this->transcriptionEditor->applyManualEdit($transcription);

        return new JsonResponse($this->audioPresenter->detail($audioRecording));
    }

    #[Route('/api/v1/audios/{id}', name: 'api_audio_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Eliminar un audio',
        description: 'Borra el audio, su transcripción y sus ficheros. No se puede deshacer. Solo para audios en estado `TRANSCRIBED` o `ERROR`: uno en `PENDING` puede estar transcribiéndose.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identificador del audio', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 204, description: 'Audio eliminado')]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'El audio no existe', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'El audio está pendiente de transcribir', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function delete(AudioRecording $audioRecording): Response
    {
        if (!$this->audioRecordingDeleter->canDelete($audioRecording)) {
            throw ApiException::conflict('No se puede eliminar un audio pendiente de transcribir.');
        }

        $this->audioRecordingDeleter->delete($audioRecording);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/v1/audios/{id}/reintentar', name: 'api_audio_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Post(
        summary: 'Reintentar la transcripción de un audio',
        description: 'Vuelve a poner en cola un audio cuya transcripción falló: pasa a `PENDING` y se limpian `error_code` y `error_message`. Solo para audios en estado `ERROR`.',
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identificador del audio', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Audio en cola de nuevo', content: new OA\JsonContent(ref: '#/components/schemas/Audio'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'El audio no existe', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'El audio no está en error', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function retry(AudioRecording $audioRecording): JsonResponse
    {
        if (AudioRecordingStatus::ERROR !== $audioRecording->getStatus()) {
            throw ApiException::conflict('Solo se puede reintentar un audio cuya transcripción ha fallado.');
        }

        $this->audioRecordingService->retryAfterError($audioRecording);

        return new JsonResponse($this->audioPresenter->detail($audioRecording));
    }
}
