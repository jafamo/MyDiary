<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class AudioRecordingService
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @param callable(): string $downloadFile devuelve la ruta local del fichero descargado; solo se invoca para audios nuevos
     */
    public function receive(
        string $telegramMessageId,
        string $telegramFileUniqueId,
        int $durationSeconds,
        callable $downloadFile,
    ): AudioRecordingReceiveResult {
        if (null !== $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId)) {
            return AudioRecordingReceiveResult::DUPLICATE_MESSAGE;
        }

        $existing = $this->audioRecordingRepository->findOneByTelegramFileUniqueId($telegramFileUniqueId);

        if (null !== $existing) {
            if (AudioRecordingStatus::ERROR === $existing->getStatus()) {
                $existing->setTelegramMessageId($telegramMessageId);
                $this->retryAfterError($existing);

                return AudioRecordingReceiveResult::RETRYING_AFTER_ERROR;
            }

            return AudioRecordingReceiveResult::DUPLICATE_FILE;
        }

        $filePath = $downloadFile();

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath($filePath)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds($durationSeconds)
        ;

        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new TranscribeAudioMessage($audioRecording->getId()));

        return AudioRecordingReceiveResult::CREATED;
    }

    public function retryAfterError(AudioRecording $audioRecording): void
    {
        $audioRecording
            ->setStatus(AudioRecordingStatus::PENDING)
            ->setErrorCode(null)
            ->setErrorMessage(null)
        ;
        $this->entityManager->flush();

        $this->messageBus->dispatch(new TranscribeAudioMessage($audioRecording->getId()));
    }
}
