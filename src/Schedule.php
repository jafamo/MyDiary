<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
        private string $idleDayReminderHours,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run
            ->add(
                RecurringMessage::cron(
                    '0 21 * * *',
                    new RunCommandMessage('app:generate-daily-summary'),
                    timezone: LocalTimezone::NAME,
                ),
            )
            ->add(
                RecurringMessage::cron(
                    '0 8 * * *',
                    new RunCommandMessage('app:notify-reminders'),
                    timezone: LocalTimezone::NAME,
                ),
            )
            ->add(
                RecurringMessage::cron(
                    '*/15 21,22,23 * * *',
                    new RunCommandMessage('app:recheck-daily-summary'),
                    timezone: LocalTimezone::NAME,
                ),
            )
            ->add(
                RecurringMessage::cron(
                    '0,15,30 0 * * *',
                    new RunCommandMessage('app:recheck-daily-summary'),
                    timezone: LocalTimezone::NAME,
                ),
            )
        ;

        foreach (self::parseHours($this->idleDayReminderHours) as $hour) {
            $schedule->add(
                RecurringMessage::cron(
                    sprintf('0 %d * * *', $hour),
                    new RunCommandMessage('app:notify-idle-day'),
                    timezone: LocalTimezone::NAME,
                ),
            );
        }

        return $schedule;
    }

    /**
     * Horas en punto de IDLE_DAY_REMINDER_HOURS ("15,17"). Cadena vacía: ninguna (aviso desactivado).
     *
     * @return list<int>
     */
    public static function parseHours(string $hours): array
    {
        $parsed = [];

        foreach (explode(',', $hours) as $value) {
            $value = trim($value);

            if ('' === $value) {
                continue;
            }

            $hour = filter_var($value, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 23]]);

            if (false === $hour) {
                throw new \InvalidArgumentException(sprintf('Hora inválida en IDLE_DAY_REMINDER_HOURS: "%s" (debe ser un entero entre 0 y 23).', $value));
            }

            $parsed[] = $hour;
        }

        return array_values(array_unique($parsed));
    }
}
