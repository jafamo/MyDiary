<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Entity\AudioRecording;
use App\Service\AudioFileMissingException;
use App\Service\AudioFileResponder;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioUploadService;
use App\Service\InvalidAudioUploadException;
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
            $upload = $this->audioUploadService->upload($file->getPathname());
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
}
