<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;

class HistorialService
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
    ) {
    }

    /**
     * Calendario del mes: cada día indica si tiene audios (`has_entries`, `count`) y resumen (`has_summary`).
     *
     * @return array{month_date: \DateTimeImmutable, weeks: list<list<array<string, mixed>>>, previous_month: \DateTimeImmutable, next_month: \DateTimeImmutable}
     */
    public function month(\DateTimeImmutable $now, int $year, int $month): array
    {
        $grid = new MonthGrid($now, $year, $month);

        $entryCounts = $this->audioRecordingRepository->countByDateInRange($grid->gridStart, $grid->gridEnd);
        $summaryDates = array_flip($this->dailySummaryRepository->findDatesWithSummaryInRange($grid->gridStart, $grid->gridEnd));

        return [
            'month_date' => $grid->firstOfMonth,
            'weeks' => $grid->weeks(static fn (string $key) => [
                'has_entries' => isset($entryCounts[$key]),
                'has_summary' => isset($summaryDates[$key]),
                'count' => $entryCounts[$key] ?? 0,
            ]),
            'previous_month' => $grid->previousMonth,
            'next_month' => $grid->nextMonth,
        ];
    }

    /**
     * Audios del día seleccionado (`Y-m-d`). Sin fecha o con una fecha inválida no hay selección.
     *
     * @return array{selected_date: \DateTimeImmutable|null, selected_entries: list<AudioRecording>}
     */
    public function selectedDay(?string $date, ?AudioRecordingStatus $status): array
    {
        $selectedDate = DateRange::parseDay($date);

        return [
            'selected_date' => $selectedDate,
            'selected_entries' => null !== $selectedDate ? $this->audioRecordingRepository->findAllReceivedOn($selectedDate, $status) : [],
        ];
    }
}
