<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\ReminderRepository;
use App\Service\DateRange;
use Twig\Extension\RuntimeExtensionInterface;

class ReminderRuntime implements RuntimeExtensionInterface
{
    private const WINDOW_DAYS = 5;
    private const URGENT_THRESHOLD_DAYS = 1;

    public function __construct(
        private readonly ReminderRepository $reminderRepository,
    ) {
    }

    /**
     * @return array{count: int, level: 'urgent'|'upcoming'|null, nearest_date: ?\DateTimeImmutable, nearest_reminders: list<\App\Entity\Reminder>}
     */
    public function upcomingReminders(): array
    {
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $reminders = $this->reminderRepository->findUpcoming($today, self::WINDOW_DAYS);

        if ([] === $reminders) {
            return ['count' => 0, 'level' => null, 'nearest_date' => null, 'nearest_reminders' => []];
        }

        $nearestDate = $reminders[0]->getDate();
        $daysToClosest = (int) $today->diff($nearestDate)->days;
        $level = $daysToClosest <= self::URGENT_THRESHOLD_DAYS ? 'urgent' : 'upcoming';
        $nearestReminders = array_values(array_filter(
            $reminders,
            static fn ($reminder) => $reminder->getDate() == $nearestDate,
        ));

        return [
            'count' => \count($reminders),
            'level' => $level,
            'nearest_date' => $nearestDate,
            'nearest_reminders' => $nearestReminders,
        ];
    }
}
