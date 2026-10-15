<?php

declare(strict_types=1);

namespace App\Tests;

use App\Schedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Yaml\Yaml;

class ScheduleTest extends TestCase
{
    public function testParsesCommaSeparatedHours(): void
    {
        self::assertSame([15, 17], Schedule::parseHours('15,17'));
        self::assertSame([9, 0, 23], Schedule::parseHours(' 9 , 0,23,9 '));
    }

    public function testEmptyHoursDisableTheReminder(): void
    {
        self::assertSame([], Schedule::parseHours(''));
        self::assertSame([], Schedule::parseHours(' , '));
    }

    #[DataProvider('invalidHours')]
    public function testRejectsInvalidHours(string $hours): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IDLE_DAY_REMINDER_HOURS');

        Schedule::parseHours($hours);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHours(): iterable
    {
        yield 'fuera de rango' => ['24'];
        yield 'negativa' => ['-1'];
        yield 'con minutos' => ['15:30'];
        yield 'texto' => ['tarde'];
    }

    public function testDefaultsWhenTheVariablesAreNotDefined(): void
    {
        // Sin IDLE_DAY_REMINDER_* en el .env, se aplican los valores por defecto de services.yaml.
        $parameters = Yaml::parseFile(\dirname(__DIR__).'/config/services.yaml')['parameters'];

        self::assertSame([15, 17], Schedule::parseHours($parameters['env(IDLE_DAY_REMINDER_HOURS)']));
        self::assertSame('No has hecho nada hoy? venga no seas así', $parameters['env(IDLE_DAY_REMINDER_MESSAGE)']);
    }

    public function testSchedulesTheIdleDayReminderAtEachHour(): void
    {
        $triggers = $this->triggers('15,17');

        self::assertContains('0 15 * * *', $triggers);
        self::assertContains('0 17 * * *', $triggers);
    }

    public function testDoesNotScheduleTheIdleDayReminderWhenHoursAreEmpty(): void
    {
        self::assertSame($this->triggers(''), array_values(array_diff($this->triggers('15,17'), ['0 15 * * *', '0 17 * * *'])));
    }

    /**
     * @return list<string>
     */
    private function triggers(string $hours): array
    {
        $schedule = (new Schedule(new ArrayAdapter(), $hours))->getSchedule();

        return array_values(array_map(
            static fn (RecurringMessage $message): string => (string) $message->getTrigger(),
            $schedule->getRecurringMessages(),
        ));
    }
}
