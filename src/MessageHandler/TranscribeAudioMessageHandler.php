<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Contract\TranscriberInterface;
use App\Contract\TranscriptionException;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TranscribeAudioMessageHandler
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly TranscriberInterface $transcriber,
        private readonly TelegramClient $telegramClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly string $transcriptionStorageDir,
        private readonly string $authorizedChatId,
    ) {
    }

    public function __invoke(TranscribeAudioMessage $message): void
    {
        $audioRecording = $this->audioRecordingRepository->find($message->audioRecordingId);

        if (null === $audioRecording) {
            return;
        }

        try {
            $content = $this->transcriber->transcribe($audioRecording->getFilePath());
        } catch (TranscriptionException $exception) {
            $this->logger->warning('Fallo al transcribir un intento de audio', [
                'event' => 'transcription.attempt_failed',
                'audio_recording_id' => $audioRecording->getId(),
                'telegram_file_unique_id' => $audioRecording->getTelegramFileUniqueId(),
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
                'exception_class' => $exception::class,
            ]);

            throw $exception;
        }

        $filePath = sprintf('%s/%s.txt', $this->transcriptionStorageDir, $audioRecording->getTelegramFileUniqueId());
        $directory = \dirname($filePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($filePath, $content);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($content)
            ->setFilePath($filePath)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $audioRecording->setStatus(AudioRecordingStatus::TRANSCRIBED);

        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        $this->telegramClient->sendMessage(
            (int) $this->authorizedChatId,
            sprintf("Transcripción lista ✅\n\n%s", $content),
        );
    }
}
