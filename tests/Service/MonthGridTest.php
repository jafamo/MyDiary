<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\MonthGrid;
use PHPUnit\Framework\TestCase;

class MonthGridTest extends TestCase
{
    public function testMonthWithLeadingAndTrailingDays(): void
    {
        $grid = new MonthGrid(new \DateTimeImmutable('2026-10-08 17:45:00'), 2026, 10);

        self::assertSame('2026-10-01 00:00:00', $grid->firstOfMonth->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-31', $grid->lastOfMonth->format('Y-m-d'));
        self::assertSame('2026-09-28', $grid->gridStart->format('Y-m-d'));
        self::assertSame('2026-11-01', $grid->gridEnd->format('Y-m-d'));

        $weeks = $grid->weeks(static fn (string $key) => []);

        self::assertCount(5, $weeks);
        foreach ($weeks as $week) {
            self::assertCount(7, $week);
        }
        self::assertTrue($weeks[0][0]['muted']);
        self::assertSame(28, $weeks[0][0]['day']);
        self::assertFalse($weeks[0][3]['muted']);
        self::assertSame(1, $weeks[0][3]['day']);
        self::assertFalse($weeks[4][5]['muted']);
        self::assertTrue($weeks[4][6]['muted']);
        self::assertSame('2026-11-01', $weeks[4][6]['date']->format('Y-m-d'));
    }

    public function testMonthStartingOnMondayHasNoLeadingDays(): void
    {
        $grid = new MonthGrid(new \DateTimeImmutable('2026-10-08'), 2026, 6);

        self::assertSame('2026-06-01', $grid->gridStart->format('Y-m-d'));
        self::assertSame('2026-07-05', $grid->gridEnd->format('Y-m-d'));
        self::assertFalse($grid->weeks(static fn (string $key) => [])[0][0]['muted']);
    }

    public function testPreviousAndNextMonthAcrossYears(): void
    {
        $january = new MonthGrid(new \DateTimeImmutable('2026-10-31'), 2026, 1);
        $december = new MonthGrid(new \DateTimeImmutable('2026-10-31'), 2026, 12);

        self::assertSame('2025-12-01', $january->previousMonth->format('Y-m-d'));
        self::assertSame('2026-02-01', $january->nextMonth->format('Y-m-d'));
        self::assertSame('2027-01-01', $december->nextMonth->format('Y-m-d'));
    }

    public function testMarksAreMergedIntoEachDay(): void
    {
        $grid = new MonthGrid(new \DateTimeImmutable('2026-10-08'), 2026, 10);

        $weeks = $grid->weeks(static fn (string $key) => ['count' => '2026-10-01' === $key ? 3 : 0]);

        self::assertSame(['date', 'day', 'muted', 'count'], array_keys($weeks[0][3]));
        self::assertSame(3, $weeks[0][3]['count']);
        self::assertSame(0, $weeks[0][4]['count']);
    }
}
