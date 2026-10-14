<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecording;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Respuesta con el fichero de un audio, compartida por la API y la web. `Range` lo resuelve
 * BinaryFileResponse al prepararse la respuesta (`206` con el tramo pedido). Nunca expone la
 * ruta ni la clave de almacenamiento.
 */
class AudioFileResponder
{
    private const MAX_AGE_SECONDS = 86400;

    private const CONTENT_TYPES = [
        'ogg' => 'audio/ogg',
        'm4a' => 'audio/mp4',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
    ];

    private const DEFAULT_CONTENT_TYPE = 'application/octet-stream';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws AudioFileMissingException si el fichero del audio no está en disco
     */
    public function respond(AudioRecording $audioRecording): BinaryFileResponse
    {
        $filePath = $audioRecording->getFilePath();
        if (!is_file($filePath) || !is_readable($filePath)) {
            $this->logger->warning('El fichero del audio no está en disco', [
                'event' => 'audio.file_missing',
                'audio_recording_id' => $audioRecording->getId(),
                'audio_source' => $audioRecording->getSource()->value,
            ]);

            throw new AudioFileMissingException(sprintf('El fichero del audio %d no está en disco.', $audioRecording->getId()));
        }

        $extension = strtolower(pathinfo($filePath, \PATHINFO_EXTENSION));

        $response = new BinaryFileResponse($filePath, autoLastModified: true);
        $response->headers->set('Content-Type', self::contentType($extension));
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('audio-%d%s', $audioRecording->getId(), '' !== $extension ? '.'.$extension : ''),
        );
        $response->setPrivate();
        $response->setMaxAge(self::MAX_AGE_SECONDS);

        return $response;
    }

    public static function contentType(string $extension): string
    {
        return self::CONTENT_TYPES[strtolower($extension)] ?? self::DEFAULT_CONTENT_TYPE;
    }
}
