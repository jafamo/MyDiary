<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Contract\SummaryGenerationException;
use App\Contract\SummaryGeneratorInterface;
use App\Entity\DailySummary;
use App\Entity\Topic;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DailySummaryService
{
    private const MESSAGE_FAILED = 'No se pudo generar el resumen de hoy ⚠️';

    private const MONTHS_ES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly TopicRepository $topicRepository,
        private readonly SummaryGeneratorInterface $summaryGenerator,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TelegramClient $telegramClient,
        private readonly UsageFormatter $usageFormatter,
        private readonly LoggerInterface $logger,
        private readonly string $authorizedChatId,
        private readonly int $pendingWaitIntervalSeconds = 15,
        private readonly int $pendingWaitMaxAttempts = 12, // ~3 minutos
        private readonly int $generationMaxAttempts = 3, // intento inicial + 2 reintentos
        private readonly int $generationRetryDelaySeconds = 5,
    ) {
    }

    public function hasNewTranscriptionsSince(\DateTimeImmutable $date): bool
    {
        $dailySummary = $this->dailySummaryRepository->findOneByDate($date);

        if (null === $dailySummary) {
            return $this->audioRecordingRepository->existsTranscribedReceivedOn($date);
        }

        return $this->audioRecordingRepository->existsTranscribedReceivedAfter($date, $dailySummary->getGeneratedAt());
    }

    public function generateForDate(\DateTimeImmutable $date, bool $waitForPending = true): void
    {
        if ($waitForPending) {
            $this->waitForPendingTranscriptions($date);
        }

        $transcribedRecords = $this->audioRecordingRepository->findTranscribedReceivedOn($date);

        if ([] === $transcribedRecords) {
            return;
        }

        $transcriptions = array_map(
            static fn ($audioRecording) => $audioRecording->getTranscription()->getContent(),
            $transcribedRecords,
        );

        try {
            $result = $this->generateWithRetries($transcriptions);
        } catch (SummaryGenerationException $exception) {
            $this->logger->error('Fallo al generar el resumen diario', [
                'event' => 'daily_summary.generation_failed',
                'date' => $date->format('Y-m-d'),
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
                'attempt_count' => $this->generationMaxAttempts,
            ]);

            $this->telegramClient->sendMessage((int) $this->authorizedChatId, self::MESSAGE_FAILED);

            return;
        }

        $this->saveDailySummary($date, $result, \count($transcriptions));
    }

    private function waitForPendingTranscriptions(\DateTimeImmutable $date): void
    {
        $attempts = 0;

        while ($attempts < $this->pendingWaitMaxAttempts) {
            if ([] === $this->audioRecordingRepository->findPendingReceivedOn($date)) {
                return;
            }

            ++$attempts;
            sleep($this->pendingWaitIntervalSeconds);
        }
    }

    /**
     * @param list<string> $transcriptions
     *
     * @return array{summary: string, topics: list<string>, legend: list<array{emoji: string, meaning: string}>, usage: array{promptTokens: ?int, completionTokens: ?int, model: string}, generationMs: int}
     */
    private function generateWithRetries(array $transcriptions): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->generationMaxAttempts; ++$attempt) {
            try {
                $startedAt = hrtime(true);
                $result = $this->summaryGenerator->generate($transcriptions);
                $result['generationMs'] = (int) round((hrtime(true) - $startedAt) / 1_000_000);

                return $result;
            } catch (SummaryGenerationException $exception) {
                $lastException = $exception;

                if ($attempt < $this->generationMaxAttempts) {
                    sleep($this->generationRetryDelaySeconds);
                }
            }
        }

        throw $lastException;
    }

    /**
     * @param array{summary: string, topics: list<string>, legend: list<array{emoji: string, meaning: string}>, usage: array{promptTokens: ?int, completionTokens: ?int, model: string}, generationMs: int} $result
     */
    private function saveDailySummary(\DateTimeImmutable $date, array $result, int $transcriptionCount): void
    {
        $summaryText = $result['summary'];
        $topicNames = $result['topics'];
        $emojiLegend = $result['legend'];

        $dailySummary = $this->dailySummaryRepository->findOneByDate($date);
        $isNew = null === $dailySummary;

        if ($isNew) {
            $dailySummary = new DailySummary();
            $dailySummary->setDate($date);
        }

        $dailySummary
            ->setSummaryText($summaryText)
            ->setEmojiLegend($emojiLegend)
            ->setGeneratedAt(new \DateTimeImmutable())
            ->setPromptTokens($result['usage']['promptTokens'])
            ->setCompletionTokens($result['usage']['completionTokens'])
            ->setGenerationMs($result['generationMs'])
            ->setModel($result['usage']['model'])
        ;

        foreach (iterator_to_array($dailySummary->getTopics()) as $existingTopic) {
            $dailySummary->removeTopic($existingTopic);
        }

        foreach ($topicNames as $topicName) {
            $topic = $this->topicRepository->findOneByName($topicName);

            if (null === $topic) {
                $topic = new Topic();
                $topic->setName($topicName);
                $this->entityManager->persist($topic);
            }

            $dailySummary->addTopic($topic);
        }

        if ($isNew) {
            $this->entityManager->persist($dailySummary);
        }

        $this->entityManager->flush();

        $this->generateEmbedding($dailySummary);
        $this->notifySummaryGenerated($date, $summaryText, $topicNames, $emojiLegend, $this->usageLine($dailySummary, $transcriptionCount));
    }

    private function generateEmbedding(DailySummary $dailySummary): void
    {
        try {
            $embedding = $this->embeddingGenerator->generate($dailySummary->getSummaryText());
        } catch (EmbeddingGenerationException $exception) {
            $this->logger->error('Fallo al generar el embedding del resumen diario', [
                'event' => 'daily_summary.embedding_generation_failed',
                'date' => $dailySummary->getDate()->format('Y-m-d'),
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
            ]);

            return;
        }

        $dailySummary->setEmbedding($embedding);
        $this->entityManager->flush();
    }

    private function usageLine(DailySummary $dailySummary, int $transcriptionCount): string
    {
        $parts = [sprintf('🧮 %d %s', $transcriptionCount, 1 === $transcriptionCount ? 'audio' : 'audios')];

        $totalTokens = $dailySummary->getTotalTokens();
        if (null !== $totalTokens) {
            $parts[] = sprintf(
                '%s tokens (%s entrada + %s salida)',
                $this->usageFormatter->number($totalTokens),
                $this->usageFormatter->number((int) $dailySummary->getPromptTokens()),
                $this->usageFormatter->number((int) $dailySummary->getCompletionTokens()),
            );
        }

        if (null !== $dailySummary->getGenerationMs()) {
            $parts[] = '⏱️ '.$this->usageFormatter->duration($dailySummary->getGenerationMs());
        }

        if (null !== $dailySummary->getModel()) {
            $parts[] = '🤖 '.$dailySummary->getModel();
        }

        return implode(' · ', $parts);
    }

    /**
     * @param list<string>                                  $topicNames
     * @param list<array{emoji: string, meaning: string}> $emojiLegend
     */
    private function notifySummaryGenerated(\DateTimeImmutable $date, string $summaryText, array $topicNames, array $emojiLegend, string $usageLine): void
    {
        $header = sprintf(
            '📔 Resumen día: %d de %s de %s',
            (int) $date->format('j'),
            self::MONTHS_ES[(int) $date->format('n')],
            $date->format('Y'),
        );

        $message = $header."\n\n".$summaryText;

        if ([] !== $topicNames) {
            $message .= "\n\n🏷️ ".implode(' · ', $topicNames);
        }

        if ([] !== $emojiLegend) {
            $message .= "\n\n".implode(' · ', array_map(
                static fn (array $entry): string => $entry['emoji'].' '.$entry['meaning'],
                $emojiLegend,
            ));
        }

        $message .= "\n\n".$usageLine;

        try {
            $this->telegramClient->sendMessage((int) $this->authorizedChatId, $message);
        } catch (\Throwable $exception) {
            $this->logger->error('Fallo al enviar la notificación del resumen diario por Telegram', [
                'event' => 'daily_summary.telegram_notification_failed',
                'date' => $date->format('Y-m-d'),
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
