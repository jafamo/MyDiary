<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Service\HistorialService;
use PHPUnit\Framework\TestCase;

class HistorialServiceTest extends TestCase
{
    public function testMonthMarksDaysWithEntriesAndSummary(): void
    {
        $audioRecordingRepository = $this->createStub(AudioRecordingRepository::class);
        $audioRecordingRepository->method('countByDateInRange')->willReturn(['2026-10-01' => 2, '2026-09-28' => 1]);
        $dailySummaryRepository = $this->createStub(DailySummaryRepository::class);
        $dailySummaryRepository->method('findDatesWithSummaryInRange')->willReturn(['2026-10-01']);
        $service = new HistorialService($audioRecordingRepository, $dailySummaryRepository);

        $month = $service->month(new \DateTimeImmutable('2026-10-08 12:00:00'), 2026, 10);

        self::assertSame('2026-10-01', $month['month_date']->format('Y-m-d'));
        self::assertSame('2026-09-01', $month['previous_month']->format('Y-m-d'));
        self::assertSame('2026-11-01', $month['next_month']->format('Y-m-d'));
        self::assertCount(5, $month['weeks']);

        $padding = $month['weeks'][0][0];
        self::assertTrue($padding['muted']);
        self::assertTrue($padding['has_entries']);
        self::assertFalse($padding['has_summary']);
        self::assertSame(1, $padding['count']);

        $first = $month['weeks'][0][3];
        self::assertSame(['date', 'day', 'muted', 'has_entries', 'has_summary', 'count'], array_keys($first));
        self::assertTrue($first['has_entries']);
        self::assertTrue($first['has_summary']);
        self::assertSame(2, $first['count']);

        $second = $month['weeks'][0][4];
        self::assertFalse($second['has_entries']);
        self::assertFalse($second['has_summary']);
        self::assertSame(0, $second['count']);
    }

    public function testSelectedDayReturnsEntriesWithStatusFilter(): void
    {
        $entries = [new AudioRecording()];
        $audioRecordingRepository = $this->createMock(AudioRecordingRepository::class);
        $audioRecordingRepository->expects(self::once())
            ->method('findAllReceivedOn')
            ->with(
                self::callback(static fn (\DateTimeImmutable $date) => '2026-10-03 00:00:00' === $date->format('Y-m-d H:i:s')),
                AudioRecordingStatus::ERROR,
            )
            ->willReturn($entries);
        $service = new HistorialService($audioRecordingRepository, $this->createStub(DailySummaryRepository::class));

        $day = $service->selectedDay('2026-10-03', AudioRecordingStatus::ERROR);

        self::assertSame('2026-10-03', $day['selected_date']->format('Y-m-d'));
        self::assertSame($entries, $day['selected_entries']);
    }

    public function testMissingOrInvalidDateHasNoSelection(): void
    {
        $audioRecordingRepository = $this->createMock(AudioRecordingRepository::class);
        $audioRecordingRepository->expects(self::never())->method('findAllReceivedOn');
        $service = new HistorialService($audioRecordingRepository, $this->createStub(DailySummaryRepository::class));

        foreach ([null, '', 'ayer', '03/10/2026'] as $date) {
            self::assertSame(['selected_date' => null, 'selected_entries' => []], $service->selectedDay($date, null));
        }
    }
}
