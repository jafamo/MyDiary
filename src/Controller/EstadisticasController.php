<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use App\Service\DateRange;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class EstadisticasController
{
    private const PRESETS = [15, 30, 90, 365];

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly TopicRepository $topicRepository,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/estadisticas', name: 'app_estadisticas', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $range = $request->query->get('range', '30');
        $status = AudioRecordingStatus::tryFrom((string) $request->query->get('status'));

        [$from, $to] = $this->resolveRange($range, $request, $today);
        $totalDays = (int) $from->diff($to)->days + 1;

        $countsByDate = $this->audioRecordingRepository->countByDateInRange($from, $to, $status);
        $series = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $series[] = ['date' => $key, 'value' => $countsByDate[$key] ?? 0];
            $cursor = $cursor->modify('+1 day');
        }

        $totalAudios = array_sum(array_column($series, 'value'));
        $avgAudiosPerDay = $totalDays > 0 ? round($totalAudios / $totalDays, 1) : 0.0;
        $avgDurationSeconds = (int) round($this->audioRecordingRepository->averageDurationInRange($from, $to, $status));
        $daysWithSummary = $this->dailySummaryRepository->countInRange($from, $to);
        $statusCounts = $this->audioRecordingRepository->countByStatusInRange($from, $to);
        $topicFrequency = $this->topicRepository->findTopicFrequencyInRange($from, $to);
        $maxTopicCount = $topicFrequency[0]['count'] ?? 1;

        return new Response($this->twig->render('estadisticas/index.html.twig', [
            'range' => $range,
            'status_filter' => $status,
            'from' => $from,
            'to' => $to,
            'series_json' => json_encode($series, \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            'avg_audios_per_day' => $avgAudiosPerDay,
            'avg_duration_seconds' => $avgDurationSeconds,
            'days_with_summary' => $daysWithSummary,
            'total_days' => $totalDays,
            'status_counts' => $statusCounts,
            'topic_frequency' => $topicFrequency,
            'max_topic_count' => $maxTopicCount,
        ]));
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function resolveRange(string $range, Request $request, \DateTimeImmutable $today): array
    {
        if ('custom' === $range) {
            $fromParam = $request->query->get('from');
            $toParam = $request->query->get('to');
            $from = null !== $fromParam ? \DateTimeImmutable::createFromFormat('Y-m-d', $fromParam) : false;
            $to = null !== $toParam ? \DateTimeImmutable::createFromFormat('Y-m-d', $toParam) : false;

            if (false !== $from && false !== $to && $from <= $to) {
                return [$from->setTime(0, 0, 0), $to->setTime(0, 0, 0)];
            }

            // rango personalizado inválido: recae en el preset por defecto
            $range = '30';
        }

        $days = \in_array((int) $range, self::PRESETS, true) ? (int) $range : 30;

        return [$today->modify(sprintf('-%d days', $days - 1)), $today];
    }
}
