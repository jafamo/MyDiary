<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\NotifyRemindersCommand;
use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use App\Service\Telegram\TelegramClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NotifyRemindersCommandTest extends TestCase
{
    public function testSendsMessageWhenRemindersExistToday(): void
    {
        $reminder = new Reminder();
        $reminder->setDate(new \DateTimeImmutable('today'))->setText('Cita médica a las 10:00');

        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::once())->method('findAllOn')->willReturn([$reminder]);

        $telegramClient = $this->createMock(TelegramClient::class);
        $telegramClient->expects(self::once())
            ->method('sendMessage')
            ->with(12345, self::stringContains('Cita médica a las 10:00'))
        ;

        $command = new NotifyRemindersCommand($repository, $telegramClient, '12345');
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:notify-reminders'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testOrdersByTimeWithUntimedRemindersLast(): void
    {
        $untimed = new Reminder();
        $untimed->setDate(new \DateTimeImmutable('today'))->setText('Sin hora');

        $late = new Reminder();
        $late->setDate(new \DateTimeImmutable('today'))->setText('Por la tarde')->setTime(new \DateTimeImmutable('16:00'));

        $early = new Reminder();
        $early->setDate(new \DateTimeImmutable('today'))->setText('Por la mañana')->setTime(new \DateTimeImmutable('08:30'));

        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::once())->method('findAllOn')->willReturn([$untimed, $late, $early]);

        $sentMessage = null;
        $telegramClient = $this->createMock(TelegramClient::class);
        $telegramClient->expects(self::once())
            ->method('sendMessage')
            ->willReturnCallback(function (int $chatId, string $message) use (&$sentMessage): void {
                $sentMessage = $message;
            })
        ;

        $command = new NotifyRemindersCommand($repository, $telegramClient, '12345');
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:notify-reminders'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        $earlyPos = strpos($sentMessage, '08:30 — Por la mañana');
        $latePos = strpos($sentMessage, '16:00 — Por la tarde');
        $untimedPos = strpos($sentMessage, '• Sin hora');
        self::assertNotFalse($earlyPos);
        self::assertNotFalse($latePos);
        self::assertNotFalse($untimedPos);
        self::assertTrue($earlyPos < $latePos && $latePos < $untimedPos);
    }

    public function testDoesNotSendMessageWhenNoRemindersToday(): void
    {
        $repository = $this->createMock(ReminderRepository::class);
        $repository->expects(self::once())->method('findAllOn')->willReturn([]);

        $telegramClient = $this->createMock(TelegramClient::class);
        $telegramClient->expects(self::never())->method('sendMessage');

        $command = new NotifyRemindersCommand($repository, $telegramClient, '12345');
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:notify-reminders'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }
}
