<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\ReminderRepository;
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
        private readonly ReminderRepository $reminderRepository,
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
        $reminderCountsByDate = $this->reminderRepository->countByDateInRange($from, $to);
        $series = [];
        $remindersSeries = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $series[] = ['date' => $key, 'value' => $countsByDate[$key] ?? 0];
            $remindersSeries[] = ['date' => $key, 'value' => $reminderCountsByDate[$key] ?? 0];
            $cursor = $cursor->modify('+1 day');
        }

        $totalAudios = array_sum(array_column($series, 'value'));
        $totalReminders = array_sum(array_column($remindersSeries, 'value'));
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
            'reminders_series_json' => json_encode($remindersSeries, \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            'total_reminders_in_range' => $totalReminders,
            'avg_audios_per_day' => $avgAudiosPerDay,
            'avg_duration_seconds' => $avgDurationSeconds,
            'days_with_summary' => $daysWithSummary,
            'total_days' => $totalDays,
            'status_counts' => $statusCounts,
            'topic_frequency' => $topicFrequency,
            'max_topic_count' => $maxTopicCount,
            'total_audios' => $totalAudios,
            'current_streak' => $this->currentStreak($series),
            'best_streak' => $this->bestStreak($series),
            'record_day' => $this->recordDay($series),
            'previous_period_comparison' => $this->previousPeriodComparison($from, $totalDays, $avgAudiosPerDay, $status),
            'ai_usage' => $this->aiUsage($from, $to),
        ]));
    }

    /**
     * Sección "Consumo IA": no depende del filtro de estado (solo cuentan los audios transcritos).
     * Las métricas null (registros anteriores a su captura) se ignoran en sumas y medias.
     *
     * @return array{tiles: array<string, int|null>, days: list<array<string, mixed>>, chart: array{max: int, bars: list<array<string, mixed>>, labels: list<array{x: float, date: string}>}}
     */
    private function aiUsage(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $transcriptionUsage = $this->audioRecordingRepository->transcriptionUsageByDateInRange($from, $to);
        $summaryUsage = $this->dailySummaryRepository->usageByDateInRange($from, $to);

        $days = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $key = $cursor->format('Y-m-d');
            $transcription = $transcriptionUsage[$key] ?? ['audios' => 0, 'audioSeconds' => 0, 'processingMs' => null];
            $summary = $summaryUsage[$key] ?? ['promptTokens' => null, 'completionTokens' => null, 'generationMs' => null];
            $days[] = [
                'date' => $key,
                'audios' => $transcription['audios'],
                'audioSeconds' => $transcription['audioSeconds'],
                'processingMs' => $transcription['processingMs'],
                'promptTokens' => $summary['promptTokens'],
                'completionTokens' => $summary['completionTokens'],
                'totalTokens' => null !== $summary['promptTokens'] && null !== $summary['completionTokens']
                    ? $summary['promptTokens'] + $summary['completionTokens']
                    : null,
                'generationMs' => $summary['generationMs'],
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $summaryTotals = array_values(array_filter(array_column($days, 'totalTokens'), static fn (?int $total) => null !== $total));

        return [
            'tiles' => [
                'promptTokens' => $this->sumOrNull(array_column($days, 'promptTokens')),
                'completionTokens' => $this->sumOrNull(array_column($days, 'completionTokens')),
                'totalTokens' => $this->sumOrNull(array_column($days, 'totalTokens')),
                'avgTokensPerSummary' => [] === $summaryTotals ? null : (int) round(array_sum($summaryTotals) / \count($summaryTotals)),
                'summariesWithTokens' => \count($summaryTotals),
                'audioSeconds' => array_sum(array_column($days, 'audioSeconds')),
                'whisperMs' => $this->sumOrNull(array_column($days, 'processingMs')),
                'ollamaMs' => $this->sumOrNull(array_column($days, 'generationMs')),
            ],
            'days' => $days,
            'chart' => $this->tokensChart($days),
        ];
    }

    /**
     * @param list<int|null> $values
     */
    private function sumOrNull(array $values): ?int
    {
        $present = array_filter($values, static fn (?int $value) => null !== $value);

        return [] === $present ? null : array_sum($present);
    }

    /**
     * Geometría del gráfico de barras apiladas (viewBox 640×220, área de dibujo x 44–628, y 16–192).
     *
     * @param list<array<string, mixed>> $days
     *
     * @return array{max: int, bars: list<array<string, mixed>>, labels: list<array{x: float, date: string}>}
     */
    private function tokensChart(array $days): array
    {
        $left = 44.0;
        $right = 628.0;
        $top = 16.0;
        $bottom = 192.0;

        $maxTotal = max([0, ...array_map(static fn (array $day) => ($day['promptTokens'] ?? 0) + ($day['completionTokens'] ?? 0), $days)]);
        $max = $this->niceMax($maxTotal);
        $scale = ($bottom - $top) / $max;

        $count = \count($days);
        $slot = ($right - $left) / max(1, $count);
        $width = max(1.0, $slot * 0.62);

        $bars = [];
        foreach ($days as $i => $day) {
            $x = $left + $i * $slot + ($slot - $width) / 2;
            $inHeight = ($day['promptTokens'] ?? 0) * $scale;
            $outHeight = ($day['completionTokens'] ?? 0) * $scale;
            $bars[] = [
                'date' => $day['date'],
                'x' => round($x, 2),
                'width' => round($width, 2),
                'inY' => round($bottom - $inHeight, 2),
                'inHeight' => round($inHeight, 2),
                'outY' => round($bottom - $inHeight - $outHeight, 2),
                'outHeight' => round($outHeight, 2),
                'promptTokens' => $day['promptTokens'],
                'completionTokens' => $day['completionTokens'],
            ];
        }

        $labels = [];
        foreach (array_unique([0, intdiv($count - 1, 2), $count - 1]) as $index) {
            if (isset($bars[$index])) {
                $labels[] = ['x' => $bars[$index]['x'] + $bars[$index]['width'] / 2, 'date' => $bars[$index]['date']];
            }
        }

        return ['max' => $max, 'bars' => $bars, 'labels' => $labels];
    }

    /**
     * Máximo "redondo" del eje (múltiplo de media potencia de 10) para que las marcas sean legibles.
     */
    private function niceMax(int $value): int
    {
        if ($value <= 0) {
            return 1000;
        }

        $magnitude = 10 ** (int) floor(log10($value));

        return (int) (ceil($value / $magnitude * 2) / 2 * $magnitude);
    }

    /**
     * Racha final del rango: días consecutivos con audio contando hacia atrás desde el último día.
     *
     * @param list<array{date: string, value: int}> $series
     */
    private function currentStreak(array $series): int
    {
        $streak = 0;
        for ($i = \count($series) - 1; $i >= 0; --$i) {
            if ($series[$i]['value'] <= 0) {
                break;
            }
            ++$streak;
        }

        return $streak;
    }

    /**
     * Racha más larga de días consecutivos con audio dentro del rango.
     *
     * @param list<array{date: string, value: int}> $series
     */
    private function bestStreak(array $series): int
    {
        $best = 0;
        $current = 0;
        foreach ($series as $day) {
            if ($day['value'] > 0) {
                ++$current;
                $best = max($best, $current);
            } else {
                $current = 0;
            }
        }

        return $best;
    }

    /**
     * Día con más audios del rango; en empate, el más antiguo. Null si no hay audios.
     *
     * @param list<array{date: string, value: int}> $series
     *
     * @return array{date: string, value: int}|null
     */
    private function recordDay(array $series): ?array
    {
        $record = null;
        foreach ($series as $day) {
            if ($day['value'] > 0 && (null === $record || $day['value'] > $record['value'])) {
                $record = $day;
            }
        }

        return $record;
    }

    /**
     * Variación de la media de audios/día frente al periodo inmediatamente anterior de igual duración.
     *
     * @return array{available: bool, percentage: float|null}
     */
    private function previousPeriodComparison(\DateTimeImmutable $from, int $totalDays, float $avgAudiosPerDay, ?AudioRecordingStatus $status): array
    {
        $previousTo = $from->modify('-1 day');
        $previousFrom = $previousTo->modify(sprintf('-%d days', $totalDays - 1));

        $previousCounts = $this->audioRecordingRepository->countByDateInRange($previousFrom, $previousTo, $status);
        $previousTotal = array_sum($previousCounts);

        if ($previousTotal <= 0) {
            return ['available' => false, 'percentage' => null];
        }

        $previousAvg = $previousTotal / $totalDays;

        return [
            'available' => true,
            'percentage' => round((($avgAudiosPerDay - $previousAvg) / $previousAvg) * 100, 1),
        ];
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
