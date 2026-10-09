<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AudioRecording;
use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioUploadService;
use App\Service\InvalidAudioUploadException;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
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
            $this->serialize($audioRecording) + ['result' => $result],
            self::RESULT_CREATED === $result ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AudioRecording $audioRecording): array
    {
        return [
            'id' => $audioRecording->getId(),
            'status' => $audioRecording->getStatus()->value,
            'source' => $audioRecording->getSource()->value,
            'duration_seconds' => $audioRecording->getDurationSeconds(),
            'received_at' => ApiFormatter::instant($audioRecording->getReceivedAt()),
        ];
    }
}
