<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\ReminderRepository;
use App\Repository\TopicRepository;

class EstadisticasService
{
    private const PRESETS = [15, 30, 90, 365];

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly TopicRepository $topicRepository,
        private readonly ReminderRepository $reminderRepository,
    ) {
    }

    /**
     * Rango efectivo: un preset de días que termina en $today, o `custom` con $from/$to (`Y-m-d`).
     * Un rango personalizado inválido recae en el preset por defecto.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public function resolveRange(string $range, ?string $from, ?string $to, \DateTimeImmutable $today): array
    {
        if ('custom' === $range) {
            $fromDate = DateRange::parseDay($from);
            $toDate = DateRange::parseDay($to);

            if (null !== $fromDate && null !== $toDate && $fromDate <= $toDate) {
                return [$fromDate, $toDate];
            }

            $range = '30';
        }

        $days = \in_array((int) $range, self::PRESETS, true) ? (int) $range : 30;

        return [$today->modify(sprintf('-%d days', $days - 1)), $today];
    }

    /**
     * Métricas del rango [$from, $to]. El filtro de estado afecta a las series y medias de audios
     * y a la comparativa, no a recordatorios, resúmenes, estados ni temas.
     *
     * @return array{
     *     series: list<array{date: string, value: int}>,
     *     reminders_series: list<array{date: string, value: int}>,
     *     total_audios: int,
     *     total_reminders_in_range: int,
     *     total_days: int,
     *     avg_audios_per_day: float,
     *     avg_duration_seconds: int,
     *     days_with_summary: int,
     *     status_counts: array<mixed>,
     *     topic_frequency: array<mixed>,
     *     max_topic_count: int,
     *     current_streak: int,
     *     best_streak: int,
     *     record_day: array{date: string, value: int}|null,
     *     previous_period_comparison: array{available: bool, percentage: float|null}
     * }
     */
    public function overview(\DateTimeImmutable $from, \DateTimeImmutable $to, ?AudioRecordingStatus $status): array
    {
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
        $avgAudiosPerDay = $totalDays > 0 ? round($totalAudios / $totalDays, 1) : 0.0;
        $topicFrequency = $this->topicRepository->findTopicFrequencyInRange($from, $to);

        return [
            'series' => $series,
            'reminders_series' => $remindersSeries,
            'total_audios' => $totalAudios,
            'total_reminders_in_range' => array_sum(array_column($remindersSeries, 'value')),
            'total_days' => $totalDays,
            'avg_audios_per_day' => $avgAudiosPerDay,
            'avg_duration_seconds' => (int) round($this->audioRecordingRepository->averageDurationInRange($from, $to, $status)),
            'days_with_summary' => $this->dailySummaryRepository->countInRange($from, $to),
            'status_counts' => $this->audioRecordingRepository->countByStatusInRange($from, $to),
            'topic_frequency' => $topicFrequency,
            'max_topic_count' => $topicFrequency[0]['count'] ?? 1,
            'current_streak' => $this->currentStreak($series),
            'best_streak' => $this->bestStreak($series),
            'record_day' => $this->recordDay($series),
            'previous_period_comparison' => $this->previousPeriodComparison($from, $totalDays, $avgAudiosPerDay, $status),
        ];
    }

    /**
     * Sección "Consumo IA": no depende del filtro de estado (solo cuentan los audios transcritos).
     * Las métricas null (registros anteriores a su captura) se ignoran en sumas y medias.
     *
     * @return array{tiles: array<string, int|null>, days: list<array<string, mixed>>}
     */
    public function aiUsage(\DateTimeImmutable $from, \DateTimeImmutable $to): array
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
}
