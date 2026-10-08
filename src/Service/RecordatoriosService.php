<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Reminder;
use App\Repository\ReminderRepository;

class RecordatoriosService
{
    private const UPCOMING_PAGE_SIZE = 20;
    private const HISTORY_PAGE_SIZE = 20;

    public function __construct(
        private readonly ReminderRepository $reminderRepository,
    ) {
    }

    /**
     * Calendario del mes: cada día indica si tiene recordatorios (`has_reminders`, `count`).
     * `month_total` cuenta solo los del mes, sin los días de relleno.
     *
     * @return array{month_date: \DateTimeImmutable, weeks: list<list<array<string, mixed>>>, previous_month: \DateTimeImmutable, next_month: \DateTimeImmutable, month_total: int}
     */
    public function month(\DateTimeImmutable $now, int $year, int $month): array
    {
        $grid = new MonthGrid($now, $year, $month);

        $reminderCounts = $this->reminderRepository->countByDateInRange($grid->gridStart, $grid->gridEnd);

        return [
            'month_date' => $grid->firstOfMonth,
            'weeks' => $grid->weeks(static fn (string $key) => [
                'has_reminders' => isset($reminderCounts[$key]),
                'count' => $reminderCounts[$key] ?? 0,
            ]),
            'previous_month' => $grid->previousMonth,
            'next_month' => $grid->nextMonth,
            'month_total' => array_sum($this->reminderRepository->countByDateInRange($grid->firstOfMonth, $grid->lastOfMonth)),
        ];
    }

    /**
     * Recordatorios del día seleccionado (`Y-m-d`). Sin fecha o con una fecha inválida no hay selección.
     *
     * @return array{selected_date: \DateTimeImmutable|null, selected_reminders: list<Reminder>}
     */
    public function selectedDay(?string $date): array
    {
        $selectedDate = DateRange::parseDay($date);

        return [
            'selected_date' => $selectedDate,
            'selected_reminders' => null !== $selectedDate ? $this->reminderRepository->findAllOn($selectedDate) : [],
        ];
    }

    /**
     * Página de recordatorios de $today en adelante. Una página fuera de rango se ajusta a la más cercana.
     *
     * @return array{upcoming_reminders: list<Reminder>, upcoming_page: int, upcoming_total_pages: int, upcoming_total: int}
     */
    public function upcoming(\DateTimeImmutable $today, int $page): array
    {
        $total = $this->reminderRepository->countFromDate($today);
        $totalPages = max(1, (int) ceil($total / self::UPCOMING_PAGE_SIZE));
        $page = max(1, min($totalPages, $page));

        return [
            'upcoming_reminders' => $this->reminderRepository->findPageFromDate($today, $page, self::UPCOMING_PAGE_SIZE),
            'upcoming_page' => $page,
            'upcoming_total_pages' => $totalPages,
            'upcoming_total' => $total,
        ];
    }

    /**
     * Página de recordatorios anteriores a $today. Una página fuera de rango se ajusta a la más cercana.
     *
     * @return array{history_reminders: list<Reminder>, history_page: int, history_total_pages: int, history_total: int}
     */
    public function history(\DateTimeImmutable $today, int $page): array
    {
        $total = $this->reminderRepository->countBeforeDate($today);
        $totalPages = max(1, (int) ceil($total / self::HISTORY_PAGE_SIZE));
        $page = max(1, min($totalPages, $page));

        return [
            'history_reminders' => $this->reminderRepository->findPageBeforeDate($today, $page, self::HISTORY_PAGE_SIZE),
            'history_page' => $page,
            'history_total_pages' => $totalPages,
            'history_total' => $total,
        ];
    }

    /**
     * Próximo recordatorio desde $today y días que faltan (0 si es hoy).
     *
     * @return array{next_reminder: Reminder|null, days_until_next: int|null}
     */
    public function next(\DateTimeImmutable $today): array
    {
        $nextReminder = $this->reminderRepository->findPageFromDate($today, 1, 1)[0] ?? null;

        return [
            'next_reminder' => $nextReminder,
            'days_until_next' => null !== $nextReminder ? (int) $today->diff($nextReminder->getDate())->days : null,
        ];
    }
}
