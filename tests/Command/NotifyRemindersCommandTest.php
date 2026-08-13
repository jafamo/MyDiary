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
