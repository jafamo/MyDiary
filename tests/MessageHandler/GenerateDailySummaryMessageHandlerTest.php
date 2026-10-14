<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\GenerateDailySummaryMessage;
use App\MessageHandler\GenerateDailySummaryMessageHandler;
use App\Service\DailySummaryService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class GenerateDailySummaryMessageHandlerTest extends TestCase
{
    public function testGeneratesWithoutWaitingForPendingAudios(): void
    {
        $service = $this->createMock(DailySummaryService::class);
        $service->expects(self::once())->method('generateForDate')->with(
            self::callback(static fn (\DateTimeImmutable $date): bool => '2026-10-14' === $date->format('Y-m-d')),
            false,
        );
        $service->expects(self::never())->method('markUnexpectedFailure');

        (new GenerateDailySummaryMessageHandler($service))(new GenerateDailySummaryMessage('2026-10-14'));
    }

    public function testUnexpectedErrorMarksTheGenerationAsFailedAndIsNotRetried(): void
    {
        $service = $this->createMock(DailySummaryService::class);
        $service->method('generateForDate')->willThrowException(new \RuntimeException('BD caída'));
        $service->expects(self::once())->method('markUnexpectedFailure');

        $this->expectException(UnrecoverableMessageHandlingException::class);

        (new GenerateDailySummaryMessageHandler($service))(new GenerateDailySummaryMessage('2026-10-14'));
    }
}
