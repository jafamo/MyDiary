<?php

declare(strict_types=1);

namespace App\Controller;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Repository\DailySummaryRepository;
use App\Repository\TranscriptionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class SearchController
{
    private const RESULTS_PER_SOURCE = 20;
    private const MAX_RESULTS = 20;

    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly LoggerInterface $logger,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/busqueda', name: 'app_busqueda', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        if ('' === $query) {
            return new Response($this->twig->render('busqueda/index.html.twig', [
                'query' => '',
                'results' => [],
                'searched' => false,
            ]));
        }

        $results = $this->search($query);

        return new Response($this->twig->render('busqueda/index.html.twig', [
            'query' => $query,
            'results' => $results,
            'searched' => true,
        ]));
    }

    /**
     * @return list<array{type: string, distance: float, date: \DateTimeImmutable, transcription: ?\App\Entity\Transcription, dailySummary: ?\App\Entity\DailySummary}>
     */
    private function search(string $query): array
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
