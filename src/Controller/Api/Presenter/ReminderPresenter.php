<?php

declare(strict_types=1);

namespace App\Controller\Api\Presenter;

use App\Controller\Api\ApiFormatter;
use App\Entity\Reminder;

class ReminderPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Reminder $reminder): array
    {
        return [
            'id' => $reminder->getId(),
            'date' => ApiFormatter::date($reminder->getDate()),
            'time' => $reminder->getTime()?->format('H:i'),
            'text' => $reminder->getText(),
            'created_at' => ApiFormatter::instant($reminder->getCreatedAt()),
            'updated_at' => ApiFormatter::instant($reminder->getUpdatedAt()),
        ];
    }

    /**
     * @param iterable<Reminder> $reminders
     *
     * @return list<array<string, mixed>>
     */
    public function presentAll(iterable $reminders): array
    {
        $items = [];
        foreach ($reminders as $reminder) {
            $items[] = $this->present($reminder);
        }

        return $items;
    }
}
