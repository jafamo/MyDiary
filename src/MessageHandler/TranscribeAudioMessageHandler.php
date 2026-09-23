<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Contract\TranscriberInterface;
use App\Contract\TranscriptionException;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Message\TranscribeAudioMessage;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use App\Service\UsageFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TranscribeAudioMessageHandler
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly TranscriberInterface $transcriber,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly TelegramClient $telegramClient,
        private readonly UsageFormatter $usageFormatter,
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
            $startedAt = hrtime(true);
            $content = $this->transcriber->transcribe($audioRecording->getFilePath());
            $processingMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
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
            ->setProcessingMs($processingMs)
            ->setModel($this->transcriber->getModel())
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $audioRecording->setStatus(AudioRecordingStatus::TRANSCRIBED);

        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        $this->generateEmbedding($transcription);

        $this->telegramClient->sendMessage(
            (int) $this->authorizedChatId,
            sprintf(
                "Transcripción lista ✅\n\n%s\n\n🎙️ %s de audio · ⏱️ transcrito en %s",
                $content,
                $this->usageFormatter->audioDuration($audioRecording->getDurationSeconds()),
                $this->usageFormatter->duration($processingMs),
            ),
        );
    }

    private function generateEmbedding(Transcription $transcription): void
    {
        try {
            $embedding = $this->embeddingGenerator->generate($transcription->getContent());
        } catch (EmbeddingGenerationException $exception) {
            $this->logger->warning('Fallo al generar el embedding de una transcripción', [
                'event' => 'transcription.embedding_generation_failed',
                'transcription_id' => $transcription->getId(),
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
            ]);

            return;
        }

        $transcription->setEmbedding($embedding);
        $this->entityManager->flush();
    }
}
