<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ReminderExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('upcoming_reminders', [ReminderRuntime::class, 'upcomingReminders']),
        ];
    }
}
