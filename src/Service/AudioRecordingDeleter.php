<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Borrado de un audio con su transcripción y sus ficheros, compartido por la web y la API.
 */
class AudioRecordingDeleter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Solo se borran audios terminados: uno en PENDING puede estar transcribiéndose.
     */
    public function canDelete(AudioRecording $audioRecording): bool
    {
        return \in_array($audioRecording->getStatus(), [AudioRecordingStatus::TRANSCRIBED, AudioRecordingStatus::ERROR], true);
    }

    public function delete(AudioRecording $audioRecording): void
    {
        $transcription = $audioRecording->getTranscription();

        $this->deleteFileIfExists($audioRecording->getFilePath());
        if (null !== $transcription) {
            $this->deleteFileIfExists($transcription->getFilePath());
            $this->entityManager->remove($transcription);
        }
        $this->entityManager->remove($audioRecording);
        $this->entityManager->flush();
    }

    private function deleteFileIfExists(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
