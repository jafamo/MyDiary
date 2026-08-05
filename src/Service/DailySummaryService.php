<?php

declare(strict_types=1);

namespace App\Service;

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

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly TopicRepository $topicRepository,
        private readonly SummaryGeneratorInterface $summaryGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly TelegramClient $telegramClient,
        private readonly LoggerInterface $logger,
        private readonly string $authorizedChatId,
        private readonly int $pendingWaitIntervalSeconds = 15,
        private readonly int $pendingWaitMaxAttempts = 12, // ~3 minutos
        private readonly int $generationMaxAttempts = 3, // intento inicial + 2 reintentos
        private readonly int $generationRetryDelaySeconds = 5,
    ) {
    }

    public function generateForDate(\DateTimeImmutable $date): void
    {
        $this->waitForPendingTranscriptions($date);

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

        $this->saveDailySummary($date, $result['summary'], $result['topics']);
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
     * @return array{summary: string, topics: list<string>}
     */
    private function generateWithRetries(array $transcriptions): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->generationMaxAttempts; ++$attempt) {
            try {
                return $this->summaryGenerator->generate($transcriptions);
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
     * @param list<string> $topicNames
     */
    private function saveDailySummary(\DateTimeImmutable $date, string $summaryText, array $topicNames): void
    {
        $dailySummary = $this->dailySummaryRepository->findOneByDate($date);
        $isNew = null === $dailySummary;

        if ($isNew) {
            $dailySummary = new DailySummary();
            $dailySummary->setDate($date);
        }

        $dailySummary
            ->setSummaryText($summaryText)
            ->setGeneratedAt(new \DateTimeImmutable())
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
    }
}
