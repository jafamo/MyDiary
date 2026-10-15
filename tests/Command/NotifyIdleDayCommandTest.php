<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\NotifyIdleDayCommand;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class NotifyIdleDayCommandTest extends TestCase
{
    public function testSendsConfiguredMessageWhenNoAudioToday(): void
    {
        $repository = $this->createMock(AudioRecordingRepository::class);
        $repository->expects(self::once())->method('countReceivedBetween')->willReturn(0);

        $telegramClient = $this->createMock(TelegramClient::class);
        $telegramClient->expects(self::once())
            ->method('sendMessage')
            ->with(12345, 'No has hecho nada hoy?')
        ;

        $commandTester = $this->commandTester($repository, $telegramClient, 'No has hecho nada hoy?');
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testDoesNotSendWhenThereIsAnyAudioToday(): void
    {
        $repository = $this->createMock(AudioRecordingRepository::class);
        $repository->expects(self::once())->method('countReceivedBetween')->willReturn(1);

        $telegramClient = $this->createMock(TelegramClient::class);
        $telegramClient->expects(self::never())->method('sendMessage');

        $commandTester = $this->commandTester($repository, $telegramClient, 'No has hecho nada hoy?');
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testCountsAudiosOfTheWholeLocalDay(): void
    {
        $repository = $this->createMock(AudioRecordingRepository::class);
        $repository->expects(self::once())
            ->method('countReceivedBetween')
            ->willReturnCallback(function (\DateTimeImmutable $start, \DateTimeImmutable $end): int {
                $localStart = $start->setTimezone(new \DateTimeZone('Europe/Madrid'));
                self::assertSame('00:00:00', $localStart->format('H:i:s'));
                self::assertSame(
                    (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d'),
                    $localStart->format('Y-m-d'),
                );
                self::assertSame('00:00:00', $end->setTimezone(new \DateTimeZone('Europe/Madrid'))->format('H:i:s'));

                return 0;
            })
        ;

        $telegramClient = $this->createStub(TelegramClient::class);

        $this->commandTester($repository, $telegramClient, 'x')->execute([]);
    }

    private function commandTester(AudioRecordingRepository $repository, TelegramClient $telegramClient, string $message): CommandTester
    {
        $command = new NotifyIdleDayCommand($repository, $telegramClient, new NullLogger(), '12345', $message);
        $application = new Application();
        $application->addCommand($command);

        return new CommandTester($application->find('app:notify-idle-day'));
    }
}
