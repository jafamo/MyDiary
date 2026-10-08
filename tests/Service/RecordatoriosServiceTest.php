<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use App\Service\RecordatoriosService;
use PHPUnit\Framework\TestCase;

class RecordatoriosServiceTest extends TestCase
{
    public function testMonthMarksDaysAndCountsOnlyTheMonth(): void
    {
        $repository = $this->createStub(ReminderRepository::class);
        $repository->method('countByDateInRange')->willReturnCallback(
            // La rejilla (desde el lunes 28 de septiembre) incluye un recordatorio del día de relleno; el mes, no.
            static fn (\DateTimeImmutable $from) => '2026-09-28' === $from->format('Y-m-d')
                ? ['2026-09-29' => 1, '2026-10-10' => 2]
                : ['2026-10-10' => 2],
        );
        $service = new RecordatoriosService($repository);

        $month = $service->month(new \DateTimeImmutable('2026-10-08 12:00:00'), 2026, 10);

        self::assertSame('2026-10-01', $month['month_date']->format('Y-m-d'));
        self::assertSame('2026-09-01', $month['previous_month']->format('Y-m-d'));
        self::assertSame('2026-11-01', $month['next_month']->format('Y-m-d'));
        self::assertSame(2, $month['month_total']);

        $padding = $month['weeks'][0][1];
        self::assertTrue($padding['muted']);
        self::assertTrue($padding['has_reminders']);

        $tenth = $month['weeks'][1][5];
        self::assertSame(['date', 'day', 'muted', 'has_reminders', 'count'], array_keys($tenth));
        self::assertSame(10, $tenth['day']);
        self::assertTrue($tenth['has_reminders']);
        self::assertSame(2, $tenth['count']);

        self::assertFalse($month['weeks'][1][6]['has_reminders']);
        self::assertSame(0, $month['weeks'][1][6]['count']);
    }

    public function testSelectedDay(): void
    {
        $reminders = [new Reminder()];
        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::once())
            ->method('findAllOn')
            ->with(self::callback(static fn (\DateTimeImmutable $date) => '2026-10-10 00:00:00' === $date->format('Y-m-d H:i:s')))
            ->willReturn($reminders);
        $service = new RecordatoriosService($repository);

        $day = $service->selectedDay('2026-10-10');

        self::assertSame('2026-10-10', $day['selected_date']->format('Y-m-d'));
        self::assertSame($reminders, $day['selected_reminders']);
    }

    public function testInvalidSelectedDayHasNoSelection(): void
    {
        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::never())->method('findAllOn');
        $service = new RecordatoriosService($repository);

        self::assertSame(['selected_date' => null, 'selected_reminders' => []], $service->selectedDay(null));
        self::assertSame(['selected_date' => null, 'selected_reminders' => []], $service->selectedDay('mañana'));
    }

    public function testUpcomingPageBeyondLastIsClampedToLastPage(): void
    {
        $today = new \DateTimeImmutable('2026-10-08 00:00:00');
        $reminders = [new Reminder()];
        $repository = $this->createMock(ReminderRepository::class);
        $repository->method('countFromDate')->willReturn(45);
        $repository->expects(self::once())->method('findPageFromDate')->with($today, 3, 20)->willReturn($reminders);
        $service = new RecordatoriosService($repository);

        self::assertSame([
            'upcoming_reminders' => $reminders,
            'upcoming_page' => 3,
            'upcoming_total_pages' => 3,
            'upcoming_total' => 45,
        ], $service->upcoming($today, 9));
    }

    public function testUpcomingWithoutRemindersIsSinglePage(): void
    {
        $today = new \DateTimeImmutable('2026-10-08 00:00:00');
        $repository = $this->createMock(ReminderRepository::class);
        $repository->method('countFromDate')->willReturn(0);
        $repository->expects(self::once())->method('findPageFromDate')->with($today, 1, 20)->willReturn([]);
        $service = new RecordatoriosService($repository);

        self::assertSame([
            'upcoming_reminders' => [],
            'upcoming_page' => 1,
            'upcoming_total_pages' => 1,
            'upcoming_total' => 0,
        ], $service->upcoming($today, 0));
    }

    public function testHistoryPageIsClamped(): void
    {
        $today = new \DateTimeImmutable('2026-10-08 00:00:00');
        $repository = $this->createMock(ReminderRepository::class);
        $repository->method('countBeforeDate')->willReturn(21);
        $repository->expects(self::once())->method('findPageBeforeDate')->with($today, 2, 20)->willReturn([]);
        $service = new RecordatoriosService($repository);

        $history = $service->history($today, 5);

        self::assertSame(2, $history['history_page']);
        self::assertSame(2, $history['history_total_pages']);
        self::assertSame(21, $history['history_total']);
    }

    public function testNextReminderAndDaysUntil(): void
    {
        $today = new \DateTimeImmutable('2026-10-08 00:00:00');
        $reminder = (new Reminder())->setDate(new \DateTimeImmutable('2026-10-11 00:00:00'))->setText('Dentista');
        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::once())->method('findPageFromDate')->with($today, 1, 1)->willReturn([$reminder]);
        $service = new RecordatoriosService($repository);

        self::assertSame(['next_reminder' => $reminder, 'days_until_next' => 3], $service->next($today));
    }

    public function testNoNextReminder(): void
    {
        $repository = $this->createStub(ReminderRepository::class);
        $repository->method('findPageFromDate')->willReturn([]);
        $service = new RecordatoriosService($repository);

        self::assertSame(['next_reminder' => null, 'days_until_next' => null], $service->next(new \DateTimeImmutable('2026-10-08 00:00:00')));
    }
}
