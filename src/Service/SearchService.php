<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\DailySummary;
use App\Entity\Reminder;
use App\Entity\Transcription;
use App\Repository\DailySummaryRepository;
use App\Repository\ReminderRepository;
use App\Repository\TranscriptionRepository;
use Psr\Log\LoggerInterface;

class SearchService
{
    private const RESULTS_PER_SOURCE = 20;
    private const MAX_RESULTS = 20;
    private const MAX_REMINDER_RESULTS = 20;

    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly ReminderRepository $reminderRepository,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Búsqueda semántica en transcripciones y resúmenes (fusionados por distancia) y búsqueda
     * textual en recordatorios. Si falla el embedding, `results` va vacío y los recordatorios se buscan igual.
     *
     * @return array{results: list<array{type: string, distance: float, date: \DateTimeImmutable, transcription: ?Transcription, dailySummary: ?DailySummary}>, reminders: list<Reminder>}
     */
    public function search(string $query): array
    {
        return [
            'results' => $this->semanticResults($query),
            'reminders' => $this->reminderRepository->searchByText($query, self::MAX_REMINDER_RESULTS),
        ];
    }

    /**
     * @return list<array{type: string, distance: float, date: \DateTimeImmutable, transcription: ?Transcription, dailySummary: ?DailySummary}>
     */
    private function semanticResults(string $query): array
    {
        try {
            $queryEmbedding = $this->embeddingGenerator->generate($query);
        } catch (EmbeddingGenerationException $exception) {
            $this->logger->warning('Fallo al generar el embedding de una búsqueda', [
                'event' => 'search.embedding_generation_failed',
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
            ]);

            return [];
        }

        $transcriptionResults = array_map(static fn (array $row) => [
            'type' => 'transcription',
            'distance' => $row['distance'],
            'date' => $row['transcription']->getAudioRecording()->getReceivedAt(),
            'transcription' => $row['transcription'],
            'dailySummary' => null,
        ], $this->transcriptionRepository->searchBySimilarity($queryEmbedding, self::RESULTS_PER_SOURCE));

        $dailySummaryResults = array_map(static fn (array $row) => [
            'type' => 'daily_summary',
            'distance' => $row['distance'],
            'date' => $row['dailySummary']->getDate(),
            'transcription' => null,
            'dailySummary' => $row['dailySummary'],
        ], $this->dailySummaryRepository->searchBySimilarity($queryEmbedding, self::RESULTS_PER_SOURCE));

        $merged = [...$transcriptionResults, ...$dailySummaryResults];
        usort($merged, static fn (array $a, array $b) => $a['distance'] <=> $b['distance']);

        return \array_slice($merged, 0, self::MAX_RESULTS);
    }
}
