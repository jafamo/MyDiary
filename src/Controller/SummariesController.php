<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class SummariesController
{
    private const PAGE_SIZE = 20;
    private const DEFAULT_LIMIT = 5;

    public function __construct(
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/resumenes', name: 'app_resumenes', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        [$from, $to] = $this->resolveRange($request);

        if (null === $from || null === $to) {
            $summaries = $this->dailySummaryRepository->findLatest(self::DEFAULT_LIMIT);

            return new Response($this->twig->render('resumenes/index.html.twig', [
                'mode' => 'latest',
                'summaries' => $summaries,
                'audio_counts' => $this->audioCountsFor($summaries),
                'from' => null,
                'to' => null,
                'page' => null,
                'total_pages' => null,
            ]));
        }

        $totalPages = max(1, (int) ceil($this->dailySummaryRepository->countInRange($from, $to) / self::PAGE_SIZE));
        $page = max(1, min($totalPages, (int) $request->query->get('page', 1)));
        $summaries = $this->dailySummaryRepository->findPageInRange($from, $to, $page, self::PAGE_SIZE);

        return new Response($this->twig->render('resumenes/index.html.twig', [
            'mode' => 'range',
            'summaries' => $summaries,
            'audio_counts' => $this->audioCountsFor($summaries),
            'from' => $from,
            'to' => $to,
            'page' => $page,
            'total_pages' => $totalPages,
        ]));
    }

    /**
     * @param list<\App\Entity\DailySummary> $summaries
     *
     * @return array<string, int> número de audios por fecha (Y-m-d) de los resúmenes dados
     */
    private function audioCountsFor(array $summaries): array
    {
        if ([] === $summaries) {
            return [];
        }

        $dates = array_map(static fn (\App\Entity\DailySummary $s) => $s->getDate(), $summaries);
        $min = min($dates);
        $max = max($dates);

        return $this->audioRecordingRepository->countByDateInRange($min, $max);
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable}
     */
    private function resolveRange(Request $request): array
    {
        $fromParam = $request->query->get('from');
        $toParam = $request->query->get('to');

        if (null === $fromParam || null === $toParam) {
            return [null, null];
        }

        $from = \DateTimeImmutable::createFromFormat('Y-m-d', $fromParam);
        $to = \DateTimeImmutable::createFromFormat('Y-m-d', $toParam);

        if (false === $from || false === $to || $from > $to) {
            return [null, null];
        }

        return [$from->setTime(0, 0, 0), $to->setTime(0, 0, 0)];
    }
}
