<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\AudioRecording;
use App\Entity\DailySummary;
use App\Entity\Reminder;
use App\Entity\Transcription;
use App\Repository\DailySummaryRepository;
use App\Repository\ReminderRepository;
use App\Repository\TranscriptionRepository;
use App\Service\SearchService;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class SearchServiceTest extends TestCase
{
    private TranscriptionRepository&Stub $transcriptionRepository;
    private DailySummaryRepository&Stub $dailySummaryRepository;
    private ReminderRepository&Stub $reminderRepository;
    private EmbeddingGeneratorInterface&Stub $embeddingGenerator;

    protected function setUp(): void
    {
        $this->transcriptionRepository = $this->createStub(TranscriptionRepository::class);
        $this->dailySummaryRepository = $this->createStub(DailySummaryRepository::class);
        $this->reminderRepository = $this->createStub(ReminderRepository::class);
        $this->embeddingGenerator = $this->createStub(EmbeddingGeneratorInterface::class);
    }

    public function testMergesTranscriptionsAndSummariesByDistance(): void
    {
        $this->embeddingGenerator->method('generate')->willReturn([0.1, 0.2]);
        $far = $this->transcription('2026-10-01 09:00:00');
        $near = $this->transcription('2026-10-02 09:00:00');
        $summary = (new DailySummary())->setDate(new \DateTimeImmutable('2026-10-03 00:00:00'));
        $this->transcriptionRepository->method('searchBySimilarity')->willReturn([
            ['transcription' => $near, 'distance' => 0.10],
            ['transcription' => $far, 'distance' => 0.40],
        ]);
        $this->dailySummaryRepository->method('searchBySimilarity')->willReturn([
            ['dailySummary' => $summary, 'distance' => 0.25],
        ]);

        $results = $this->service()->search('vacaciones')['results'];

        self::assertSame([0.10, 0.25, 0.40], array_column($results, 'distance'));
        self::assertSame(['transcription', 'daily_summary', 'transcription'], array_column($results, 'type'));
        self::assertSame($near, $results[0]['transcription']);
        self::assertNull($results[0]['dailySummary']);
        self::assertSame('2026-10-02', $results[0]['date']->format('Y-m-d'));
        self::assertSame($summary, $results[1]['dailySummary']);
        self::assertNull($results[1]['transcription']);
        self::assertSame('2026-10-03', $results[1]['date']->format('Y-m-d'));
    }

    public function testResultsAreLimited(): void
    {
        $this->embeddingGenerator->method('generate')->willReturn([0.1]);
        $transcriptionRows = [];
        $summaryRows = [];
        for ($i = 0; $i < 15; ++$i) {
            $transcriptionRows[] = ['transcription' => $this->transcription('2026-10-01 09:00:00'), 'distance' => 0.01 * $i];
            $summaryRows[] = ['dailySummary' => (new DailySummary())->setDate(new \DateTimeImmutable('2026-10-01')), 'distance' => 0.01 * $i + 0.005];
        }
        $this->transcriptionRepository->method('searchBySimilarity')->willReturn($transcriptionRows);
        $this->dailySummaryRepository->method('searchBySimilarity')->willReturn($summaryRows);

        $results = $this->service()->search('vacaciones')['results'];

        self::assertCount(20, $results);
        self::assertSame(0.0, $results[0]['distance']);
        self::assertEqualsWithDelta(0.095, $results[19]['distance'], 0.0001);
    }

    public function testRemindersAreSearchedByText(): void
    {
        $this->embeddingGenerator->method('generate')->willReturn([0.1]);
        $reminders = [new Reminder()];
        $reminderRepository = $this->createMock(ReminderRepository::class);
        $reminderRepository->expects(self::once())->method('searchByText')->with('dentista', 20)->willReturn($reminders);
        $this->reminderRepository = $reminderRepository;

        $found = $this->service()->search('dentista');

        self::assertSame($reminders, $found['reminders']);
        self::assertSame([], $found['results']);
    }

    public function testEmbeddingFailureKeepsRemindersAndLogsWarning(): void
    {
        $this->embeddingGenerator->method('generate')->willThrowException(new EmbeddingGenerationException('ollama_unreachable', 'Sin conexión'));
        $reminders = [new Reminder()];
        $this->reminderRepository->method('searchByText')->willReturn($reminders);
        $transcriptionRepository = $this->createMock(TranscriptionRepository::class);
        $transcriptionRepository->expects(self::never())->method('searchBySimilarity');
        $this->transcriptionRepository = $transcriptionRepository;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('Fallo al generar el embedding de una búsqueda', [
            'event' => 'search.embedding_generation_failed',
            'error_code' => 'ollama_unreachable',
            'error_message' => 'Sin conexión',
        ]);

        $found = $this->service($logger)->search('dentista');

        self::assertSame([], $found['results']);
        self::assertSame($reminders, $found['reminders']);
    }

    private function service(?LoggerInterface $logger = null): SearchService
    {
        return new SearchService(
            $this->transcriptionRepository,
            $this->dailySummaryRepository,
            $this->reminderRepository,
            $this->embeddingGenerator,
            $logger ?? new NullLogger(),
        );
    }

    private function transcription(string $receivedAt): Transcription
    {
        $audioRecording = (new AudioRecording())->setReceivedAt(new \DateTimeImmutable($receivedAt));

        return (new Transcription())->setAudioRecording($audioRecording);
    }
}
