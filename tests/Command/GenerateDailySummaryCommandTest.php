<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Service\DailySummaryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateDailySummaryCommandTest extends TestCase
{
    public function testCallsServiceWithGivenDate(): void
    {
        $receivedDate = null;

        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::once())
            ->method('generateForDate')
            ->willReturnCallback(function (\DateTimeImmutable $date) use (&$receivedDate): void {
                $receivedDate = $date;
            })
        ;

        $command = new \App\Command\GenerateDailySummaryCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:generate-daily-summary'));
        $commandTester->execute(['--date' => '2026-08-01']);

        $commandTester->assertCommandIsSuccessful();
        self::assertSame('2026-08-01', $receivedDate->format('Y-m-d'));
    }

    public function testDefaultsToToday(): void
    {
        $receivedDate = null;

        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::once())
            ->method('generateForDate')
            ->willReturnCallback(function (\DateTimeImmutable $date) use (&$receivedDate): void {
                $receivedDate = $date;
            })
        ;

        $command = new \App\Command\GenerateDailySummaryCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:generate-daily-summary'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), $receivedDate->format('Y-m-d'));
    }

    public function testInvalidDateFails(): void
    {
        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::never())->method('generateForDate');

        $command = new \App\Command\GenerateDailySummaryCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:generate-daily-summary'));
        $commandTester->execute(['--date' => 'not-a-date']);

        self::assertSame(1, $commandTester->getStatusCode());
    }
}
