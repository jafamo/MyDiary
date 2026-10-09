<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\RecordatoriosService;
use App\Service\DateRange;
use Twig\Extension\RuntimeExtensionInterface;

class ReminderRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly RecordatoriosService $recordatoriosService,
    ) {
    }

    /**
     * @return array{count: int, level: 'urgent'|'upcoming'|null, nearest_date: ?\DateTimeImmutable, nearest_reminders: list<\App\Entity\Reminder>}
     */
    public function upcomingReminders(): array
    {
        return $this->recordatoriosService->upcomingAlert(DateRange::nowInMadrid()->setTime(0, 0, 0));
    }
}
