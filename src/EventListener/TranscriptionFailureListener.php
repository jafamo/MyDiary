<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Contract\TranscriptionException;
use App\Entity\AudioRecordingStatus;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
class TranscriptionFailureListener
{
    private const MESSAGE_FAILED = 'No se pudo transcribir este audio ❌';

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TelegramClient $telegramClient,
        private readonly LoggerInterface $logger,
        private readonly string $authorizedChatId,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof TranscribeAudioMessage) {
            return;
        }

        $audioRecording = $this->audioRecordingRepository->find($message->audioRecordingId);

        if (null === $audioRecording) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof TranscriptionException) {
            $errorCode = $exception->getErrorCode();
            $errorMessage = $exception->getErrorMessage();
        } else {
            $errorCode = 'UNKNOWN';
            $errorMessage = sprintf('Fallo inesperado al transcribir: %s.', $exception->getMessage());
        }

        $audioRecording
            ->setStatus(AudioRecordingStatus::ERROR)
            ->setErrorCode($errorCode)
            ->setErrorMessage($errorMessage)
        ;
        $this->entityManager->flush();

        $this->logger->error('Se agotaron los reintentos de transcripción', [
            'event' => 'transcription.retry_exhausted',
            'audio_recording_id' => $audioRecording->getId(),
            'telegram_file_unique_id' => $audioRecording->getTelegramFileUniqueId(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'exception_class' => $exception::class,
        ]);

        $this->telegramClient->sendMessage((int) $this->authorizedChatId, self::MESSAGE_FAILED);
    }
}
