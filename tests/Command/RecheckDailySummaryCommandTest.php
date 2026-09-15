<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RecheckDailySummaryCommand;
use App\Service\DailySummaryService;
use Psr\Log\NullLogger;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RecheckDailySummaryCommandTest extends TestCase
{
    public function testRegeneratesWhenThereAreNewTranscriptions(): void
    {
        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::once())
            ->method('hasNewTranscriptionsSince')
            ->willReturn(true)
        ;
        $service->expects(self::once())
            ->method('generateForDate')
            ->with(self::isInstanceOf(\DateTimeImmutable::class), false)
        ;

        $commandTester = $this->execute($service);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testDoesNothingWhenThereAreNoNewTranscriptions(): void
    {
        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::once())
            ->method('hasNewTranscriptionsSince')
            ->willReturn(false)
        ;
        $service->expects(self::never())->method('generateForDate');

        $commandTester = $this->execute($service);

        $commandTester->assertCommandIsSuccessful();
    }

    private function execute(DailySummaryService $service): CommandTester
    {
        $command = new RecheckDailySummaryCommand($service, new NullLogger());
        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:recheck-daily-summary'));
        $commandTester->execute([]);

        return $commandTester;
    }
}
