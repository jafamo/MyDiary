<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DailySummaryGeneration;
use App\Entity\DailySummaryGenerationStatus;
use App\Message\GenerateDailySummaryMessage;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryGenerationRepository;
use App\Service\DailySummaryGenerationRequester;
use App\Service\NoTranscriptionsToSummarizeException;
use App\Tests\Controller\Api\ApiTestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class DailySummaryGenerationRequesterTest extends ApiTestCase
{
    private const DAY = '2019-07-01';

    /** @var list<array{message: string, context: array<string, mixed>}> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->removeGeneration();
    }

    protected function tearDown(): void
    {
        $this->removeGeneration();
        parent::tearDown();
    }

    public function testQueuesTheGenerationAndLogsTheRequest(): void
    {
        $this->createAudio(self::DAY.' 10:00:00', content: 'Texto');

        $generation = $this->requester()->request(new \DateTimeImmutable(self::DAY.' 18:30:00'), DailySummaryGenerationRequester::SOURCE_API);

        self::assertSame(DailySummaryGenerationStatus::QUEUED, $generation->getStatus());
        self::assertSame(self::DAY, $generation->getDate()->format('Y-m-d'));
        self::assertSame([self::DAY], $this->dispatchedDates());
        self::assertCount(1, $this->logs);
        self::assertSame([
            'event' => 'daily_summary.generation_requested',
            'daily_summary_date' => self::DAY,
            'daily_summary_request_source' => 'api',
        ], $this->logs[0]['context']);
    }

    public function testRepeatedRequestReturnsTheGenerationInProgress(): void
    {
        $this->createAudio(self::DAY.' 10:00:00', content: 'Texto');
        $requester = $this->requester();

        $first = $requester->request(new \DateTimeImmutable(self::DAY), DailySummaryGenerationRequester::SOURCE_WEB);
        $second = $requester->request(new \DateTimeImmutable(self::DAY), DailySummaryGenerationRequester::SOURCE_WEB);

        self::assertSame($first->getId(), $second->getId());
        self::assertSame($first->getRequestedAt(), $second->getRequestedAt());
        self::assertCount(1, $this->dispatchedDates());
        self::assertCount(1, $this->logs);
    }

    public function testStalledGenerationCanBeRequestedAgain(): void
    {
        $this->createAudio(self::DAY.' 10:00:00', content: 'Texto');
        $stalled = new DailySummaryGeneration(new \DateTimeImmutable(self::DAY));
        $stalled->markQueued(new \DateTimeImmutable(sprintf('-%d minutes', DailySummaryGeneration::STALL_AFTER_MINUTES + 1)));
        $this->entityManager->persist($stalled);
        $this->entityManager->flush();

        $generation = $this->requester()->request(new \DateTimeImmutable(self::DAY), DailySummaryGenerationRequester::SOURCE_WEB);

        self::assertFalse($generation->isStalled(new \DateTimeImmutable()));
        self::assertSame([self::DAY], $this->dispatchedDates());
    }

    public function testDayWithoutTranscriptionsIsRejected(): void
    {
        $this->createAudio(self::DAY.' 10:00:00');

        try {
            $this->requester()->request(new \DateTimeImmutable(self::DAY), DailySummaryGenerationRequester::SOURCE_API);
            self::fail('Se esperaba NoTranscriptionsToSummarizeException.');
        } catch (NoTranscriptionsToSummarizeException) {
        }

        self::assertNull(self::getContainer()->get(DailySummaryGenerationRepository::class)->findOneByDate(new \DateTimeImmutable(self::DAY)));
        self::assertSame([], $this->dispatchedDates());
        self::assertSame([], $this->logs);
    }

    private function requester(): DailySummaryGenerationRequester
    {
        $this->logs = [];
        $logger = new class (function (array $record): void {
            $this->logs[] = $record;
        }) extends AbstractLogger {
            public function __construct(private readonly \Closure $sink)
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                ($this->sink)(['message' => (string) $message, 'context' => $context]);
            }
        };

        return new DailySummaryGenerationRequester(
            self::getContainer()->get(AudioRecordingRepository::class),
            self::getContainer()->get(DailySummaryGenerationRepository::class),
            $this->entityManager,
            self::getContainer()->get(MessageBusInterface::class),
            $logger,
        );
    }

    /**
     * @return list<string>
     */
    private function dispatchedDates(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_values(array_map(
            static function ($envelope): string {
                $message = $envelope->getMessage();
                self::assertInstanceOf(GenerateDailySummaryMessage::class, $message);

                return $message->date;
            },
            $transport->getSent(),
        ));
    }

    private function removeGeneration(): void
    {
        $generation = self::getContainer()->get(DailySummaryGenerationRepository::class)->findOneByDate(new \DateTimeImmutable(self::DAY));
        if (null !== $generation) {
            $this->entityManager->remove($generation);
            $this->entityManager->flush();
        }
    }
}
