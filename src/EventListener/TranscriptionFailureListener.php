<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Contract\TranscriptionException;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\LocalTimezone;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use App\Service\UsageFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener]
class TranscriptionFailureListener
{
    private const MESSAGE_FAILED = 'No se pudo transcribir la nota ❌';

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TelegramClient $telegramClient,
        private readonly UsageFormatter $usageFormatter,
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

        $this->logger->error('Se agotaron los reintentos de transcripción', array_filter([
            'event' => 'transcription.retry_exhausted',
            'audio_recording_id' => $audioRecording->getId(),
            'audio_source' => $audioRecording->getSource()->value,
            'telegram_file_unique_id' => $audioRecording->getTelegramFileUniqueId(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'exception_class' => $exception::class,
        ], static fn (mixed $value): bool => null !== $value));

        $telegramMessageId = $audioRecording->getTelegramMessageId();
        $this->telegramClient->sendMessage(
            (int) $this->authorizedChatId,
            $this->failureNotice($audioRecording),
            AudioSource::TELEGRAM === $audioRecording->getSource() && null !== $telegramMessageId ? (int) $telegramMessageId : null,
        );
    }

    /**
     * Título, origen (dispositivo de la app o Telegram) y día, hora local y duración, para
     * saber qué nota ha fallado.
     */
    private function failureNotice(AudioRecording $audioRecording): string
    {
        $origin = match ($audioRecording->getSource()) {
            AudioSource::TELEGRAM => '💬 Telegram',
            AudioSource::APP => '📱 '.($audioRecording->getDeviceName() ?? 'App'),
        };
        $receivedAt = $audioRecording->getReceivedAt()->setTimezone(new \DateTimeZone(LocalTimezone::NAME));

        return implode("\n", [
            self::MESSAGE_FAILED,
            $origin,
            sprintf('🕐 %s · %s', $receivedAt->format('d/m H:i'), $this->usageFormatter->audioDuration($audioRecording->getDurationSeconds())),
        ]);
    }
}
