<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Rejilla de calendario de un mes: semanas completas de lunes a domingo, con los días
 * de relleno de los meses vecinos que hagan falta al principio y al final.
 */
final class MonthGrid
{
    public readonly \DateTimeImmutable $firstOfMonth;
    public readonly \DateTimeImmutable $lastOfMonth;
    public readonly \DateTimeImmutable $gridStart;
    public readonly \DateTimeImmutable $gridEnd;
    public readonly \DateTimeImmutable $previousMonth;
    public readonly \DateTimeImmutable $nextMonth;

    public function __construct(\DateTimeImmutable $now, int $year, private readonly int $month)
    {
        $this->firstOfMonth = $now->setDate($year, $month, 1)->setTime(0, 0, 0);
        $this->lastOfMonth = $this->firstOfMonth->modify('last day of this month');

        $leadingDays = ((int) $this->firstOfMonth->format('N')) - 1;
        $trailingDays = 7 - ((int) $this->lastOfMonth->format('N'));
        $this->gridStart = $this->firstOfMonth->modify(sprintf('-%d days', $leadingDays));
        $this->gridEnd = $this->lastOfMonth->modify(sprintf('+%d days', $trailingDays));

        $this->previousMonth = $this->firstOfMonth->modify('-1 month');
        $this->nextMonth = $this->firstOfMonth->modify('+1 month');
    }

    /**
     * Semanas de la rejilla. Cada día lleva `date`, `day` y `muted` (día de relleno), más las
     * marcas que devuelva $marks para su clave `Y-m-d`.
     *
     * @param callable(string): array<string, mixed> $marks
     *
     * @return list<list<array<string, mixed>>>
     */
    public function weeks(callable $marks): array
    {
        $weeks = [];
        $week = [];
        $cursor = $this->gridStart;
        while ($cursor <= $this->gridEnd) {
            $week[] = [
                'date' => $cursor,
                'day' => (int) $cursor->format('j'),
                'muted' => ((int) $cursor->format('n')) !== $this->month,
                ...$marks($cursor->format('Y-m-d')),
            ];
            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->modify('+1 day');
        }

        return $weeks;
    }
}
