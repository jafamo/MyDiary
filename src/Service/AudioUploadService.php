<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\AudioProbeException;
use App\Contract\AudioProbeInterface;

/**
 * Entrada de los audios subidos desde la app: valida el fichero, lo identifica por su
 * contenido y lo deja en el almacenamiento de audios.
 */
class AudioUploadService
{
    /** Mantener alineado con docker/php/conf.d/uploads.ini y client_max_body_size de nginx. */
    public const MAX_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly AudioRecordingService $audioRecordingService,
        private readonly AudioProbeInterface $audioProbe,
        private readonly string $audioStorageDir,
    ) {
    }

    public static function tooLargeMessage(): string
    {
        return sprintf('El audio supera el tamaño máximo de %d MB.', self::MAX_BYTES / 1024 / 1024);
    }

    /**
     * @param string $uploadedFilePath fichero temporal de la subida; no se modifica
     *
     * @throws InvalidAudioUploadException si el fichero supera el tamaño máximo o no es un audio admitido
     */
    public function upload(string $uploadedFilePath): AudioUploadResult
    {
        if (filesize($uploadedFilePath) > self::MAX_BYTES) {
            throw new InvalidAudioUploadException(self::tooLargeMessage());
        }

        $contentHash = hash_file('sha256', $uploadedFilePath);

        return $this->audioRecordingService->receiveUpload(
            $contentHash,
            function () use ($uploadedFilePath, $contentHash): array {
                try {
                    $probe = $this->audioProbe->probe($uploadedFilePath);
                } catch (AudioProbeException $exception) {
                    throw new InvalidAudioUploadException('El fichero no es un audio admitido (m4a, mp3, ogg o wav).', 0, $exception);
                }

                if (!is_dir($this->audioStorageDir)) {
                    mkdir($this->audioStorageDir, 0775, true);
                }
                $destination = sprintf('%s/%s.%s', $this->audioStorageDir, $contentHash, $probe->format);
                copy($uploadedFilePath, $destination);

                return ['filePath' => $destination, 'durationSeconds' => $probe->durationSeconds];
            },
        );
    }
}
