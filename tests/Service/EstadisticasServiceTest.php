<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\ReminderRepository;
use App\Repository\TopicRepository;
use App\Service\EstadisticasService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class EstadisticasServiceTest extends TestCase
{
    private AudioRecordingRepository&Stub $audioRecordingRepository;
    private DailySummaryRepository&Stub $dailySummaryRepository;
    private TopicRepository&Stub $topicRepository;
    private ReminderRepository&Stub $reminderRepository;
    private EstadisticasService $service;

    protected function setUp(): void
    {
        $this->audioRecordingRepository = $this->createStub(AudioRecordingRepository::class);
        $this->dailySummaryRepository = $this->createStub(DailySummaryRepository::class);
        $this->topicRepository = $this->createStub(TopicRepository::class);
        $this->reminderRepository = $this->createStub(ReminderRepository::class);
        $this->service = new EstadisticasService(
            $this->audioRecordingRepository,
            $this->dailySummaryRepository,
            $this->topicRepository,
            $this->reminderRepository,
        );
    }

    public function testPresetRangeEndsToday(): void
    {
        [$from, $to] = $this->service->resolveRange('15', null, null, $this->day('2026-10-08'));

        self::assertSame('2026-09-24', $from->format('Y-m-d'));
        self::assertSame('2026-10-08', $to->format('Y-m-d'));
    }

    public function testUnknownPresetFallsBackToThirtyDays(): void
    {
        [$from, $to] = $this->service->resolveRange('7', null, null, $this->day('2026-10-08'));

        self::assertSame('2026-09-09', $from->format('Y-m-d'));
        self::assertSame('2026-10-08', $to->format('Y-m-d'));
    }

    public function testCustomRange(): void
    {
        [$from, $to] = $this->service->resolveRange('custom', '2026-03-01', '2026-03-10', $this->day('2026-10-08'));

        self::assertSame('2026-03-01 00:00:00', $from->format('Y-m-d H:i:s'));
        self::assertSame('2026-03-10 00:00:00', $to->format('Y-m-d H:i:s'));
    }

    public function testInvalidCustomRangeFallsBackToThirtyDays(): void
    {
        $today = $this->day('2026-10-08');

        foreach ([['2026-03-10', '2026-03-01'], ['no-es-fecha', '2026-03-01'], [null, null]] as [$fromParam, $toParam]) {
            [$from, $to] = $this->service->resolveRange('custom', $fromParam, $toParam, $today);

            self::assertSame('2026-09-09', $from->format('Y-m-d'));
            self::assertSame('2026-10-08', $to->format('Y-m-d'));
        }
    }

    public function testOverviewStreaksRecordDayAndAverages(): void
    {
        $this->audioCounts(['2026-10-01' => 1, '2026-10-02' => 2, '2026-10-04' => 3, '2026-10-05' => 3]);
        $this->reminderRepository->method('countByDateInRange')->willReturn(['2026-10-02' => 2, '2026-10-05' => 1]);
        $this->audioRecordingRepository->method('averageDurationInRange')->willReturn(41.6);
        $this->dailySummaryRepository->method('countInRange')->willReturn(4);
        $this->topicRepository->method('findTopicFrequencyInRange')->willReturn([['name' => 'trabajo', 'count' => 7], ['name' => 'salud', 'count' => 2]]);

        $overview = $this->service->overview($this->day('2026-10-01'), $this->day('2026-10-05'), null);

        self::assertSame([1, 2, 0, 3, 3], array_column($overview['series'], 'value'));
        self::assertSame('2026-10-03', $overview['series'][2]['date']);
        self::assertSame([0, 2, 0, 0, 1], array_column($overview['reminders_series'], 'value'));
        self::assertSame(5, $overview['total_days']);
        self::assertSame(9, $overview['total_audios']);
        self::assertSame(3, $overview['total_reminders_in_range']);
        self::assertSame(1.8, $overview['avg_audios_per_day']);
        self::assertSame(42, $overview['avg_duration_seconds']);
        self::assertSame(4, $overview['days_with_summary']);
        self::assertSame(7, $overview['max_topic_count']);
        self::assertSame(2, $overview['current_streak']);
        self::assertSame(2, $overview['best_streak']);
        self::assertSame(['date' => '2026-10-04', 'value' => 3], $overview['record_day']);
        self::assertSame(['available' => false, 'percentage' => null], $overview['previous_period_comparison']);
    }

    public function testOverviewWithoutAudios(): void
    {
        $this->audioCounts([]);

        $overview = $this->service->overview($this->day('2026-10-01'), $this->day('2026-10-05'), null);

        self::assertSame(0, $overview['total_audios']);
        self::assertSame(0, $overview['current_streak']);
        self::assertSame(0, $overview['best_streak']);
        self::assertNull($overview['record_day']);
        self::assertSame(1, $overview['max_topic_count']);
    }

    public function testComparisonWithPreviousPeriod(): void
    {
        // Rango: 9 audios en 5 días (1,8/día). Periodo anterior (26-30 sep): 5 audios (1/día).
        $this->audioCounts(
            ['2026-10-01' => 4, '2026-10-02' => 5],
            ['2026-09-26' => 2, '2026-09-30' => 3],
        );

        $overview = $this->service->overview($this->day('2026-10-01'), $this->day('2026-10-05'), null);

        self::assertSame(['available' => true, 'percentage' => 80.0], $overview['previous_period_comparison']);
    }

    public function testAiUsageIgnoresMissingMetrics(): void
    {
        $this->audioRecordingRepository->method('transcriptionUsageByDateInRange')->willReturn([
            '2026-10-01' => ['audios' => 2, 'audioSeconds' => 90, 'processingMs' => 4000],
            '2026-10-02' => ['audios' => 1, 'audioSeconds' => 30, 'processingMs' => null],
        ]);
        $this->dailySummaryRepository->method('usageByDateInRange')->willReturn([
            '2026-10-01' => ['promptTokens' => 1000, 'completionTokens' => 200, 'generationMs' => 9000],
            '2026-10-02' => ['promptTokens' => null, 'completionTokens' => null, 'generationMs' => null],
            '2026-10-03' => ['promptTokens' => 600, 'completionTokens' => 200, 'generationMs' => 5000],
        ]);

        $usage = $this->service->aiUsage($this->day('2026-10-01'), $this->day('2026-10-03'));

        self::assertSame(['tiles', 'days'], array_keys($usage));
        self::assertSame([
            'promptTokens' => 1600,
            'completionTokens' => 400,
            'totalTokens' => 2000,
            'avgTokensPerSummary' => 1000,
            'summariesWithTokens' => 2,
            'audioSeconds' => 120,
            'whisperMs' => 4000,
            'ollamaMs' => 14000,
        ], $usage['tiles']);
        self::assertCount(3, $usage['days']);
        self::assertSame(1200, $usage['days'][0]['totalTokens']);
        self::assertNull($usage['days'][1]['totalTokens']);
        self::assertSame(0, $usage['days'][2]['audios']);
    }

    public function testAiUsageWithoutDataHasNullTotals(): void
    {
        $usage = $this->service->aiUsage($this->day('2026-10-01'), $this->day('2026-10-02'));

        self::assertNull($usage['tiles']['totalTokens']);
        self::assertNull($usage['tiles']['avgTokensPerSummary']);
        self::assertNull($usage['tiles']['whisperMs']);
        self::assertSame(0, $usage['tiles']['summariesWithTokens']);
        self::assertSame(0, $usage['tiles']['audioSeconds']);
    }

    /**
     * @param array<string, int> $inRange  cuentas del rango consultado (desde 2026-10-01)
     * @param array<string, int> $previous cuentas del periodo anterior
     */
    private function audioCounts(array $inRange, array $previous = []): void
    {
        $this->audioRecordingRepository->method('countByDateInRange')->willReturnCallback(
            static fn (\DateTimeImmutable $from) => '2026-10-01' === $from->format('Y-m-d') ? $inRange : $previous,
        );
    }

    private function day(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date.' 00:00:00');
    }
}
