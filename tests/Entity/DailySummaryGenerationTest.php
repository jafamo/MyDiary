<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\DailySummaryGeneration;
use App\Entity\DailySummaryGenerationStatus;
use PHPUnit\Framework\TestCase;

class DailySummaryGenerationTest extends TestCase
{
    public function testRecentQueuedGenerationIsInProgress(): void
    {
        $generation = $this->generation();
        $generation->markQueued(new \DateTimeImmutable('2026-10-14 10:00:00'));

        $now = new \DateTimeImmutable('2026-10-14 10:14:00');
        self::assertTrue($generation->isInProgress($now));
        self::assertFalse($generation->isStalled($now));
        self::assertSame(DailySummaryGenerationStatus::QUEUED, $generation->effectiveStatus($now));
    }

    public function testQueuedOrRunningGenerationIsStalledAfterTheThreshold(): void
    {
        $now = new \DateTimeImmutable('2026-10-14 10:16:00');

        $queued = $this->generation();
        $queued->markQueued(new \DateTimeImmutable('2026-10-14 10:00:00'));
        $running = $this->generation();
        $running->markQueued(new \DateTimeImmutable('2026-10-14 10:00:00'));
        $running->markRunning(new \DateTimeImmutable('2026-10-14 10:10:00'));

        foreach ([$queued, $running] as $generation) {
            self::assertTrue($generation->isStalled($now));
            self::assertFalse($generation->isInProgress($now));
            self::assertSame(DailySummaryGenerationStatus::FAILED, $generation->effectiveStatus($now));
        }
    }

    public function testFinishedGenerationIsNeverStalled(): void
    {
        $generation = $this->generation();
        $generation->markRunning(new \DateTimeImmutable('2026-10-14 10:00:00'));
        $generation->markDone(new \DateTimeImmutable('2026-10-14 10:05:00'));

        $tomorrow = new \DateTimeImmutable('2026-10-15 10:00:00');
        self::assertFalse($generation->isStalled($tomorrow));
        self::assertSame(DailySummaryGenerationStatus::DONE, $generation->effectiveStatus($tomorrow));
    }

    public function testRunningWithoutARequestStartsNowAndClearsThePreviousError(): void
    {
        $generation = $this->generation();
        $generation->markRunning(new \DateTimeImmutable('2026-10-14 09:00:00'));
        $generation->markFailed(new \DateTimeImmutable('2026-10-14 09:05:00'), 'TIMEOUT', 'fallo');

        $generation->markRunning(new \DateTimeImmutable('2026-10-14 21:00:00'));

        self::assertSame('2026-10-14 21:00:00', $generation->getRequestedAt()->format('Y-m-d H:i:s'));
        self::assertNull($generation->getFinishedAt());
        self::assertNull($generation->getErrorCode());
    }

    private function generation(): DailySummaryGeneration
    {
        return new DailySummaryGeneration(new \DateTimeImmutable('2026-10-14'));
    }
}
