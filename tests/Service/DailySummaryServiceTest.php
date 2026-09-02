<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\SummaryGenerationException;
use App\Contract\SummaryGeneratorInterface;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use App\Service\DailySummaryService;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DailySummaryServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private DailySummaryRepository $dailySummaryRepository;
    private TopicRepository $topicRepository;
    private \DateTimeImmutable $testDate;

    /** @var list<array{url: string, body: mixed}> */
    private array $httpRequests = [];
    private \Closure $httpResponder;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->dailySummaryRepository = $container->get(DailySummaryRepository::class);
        $this->topicRepository = $container->get(TopicRepository::class);
        $this->testDate = new \DateTimeImmutable('2026-08-04 12:00:00');

        $this->httpRequests = [];
        $this->httpResponder = static fn (): MockResponse => new MockResponse('{"ok":true}');

        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            function (string $method, string $url, array $options): MockResponse {
                $this->httpRequests[] = ['url' => $url, 'body' => $options['body'] ?? null];

                return ($this->httpResponder)($method, $url, $options);
            },
        ));

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testGeneratesNewSummaryWithTopics(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-1', 'summary-file-1', 'transcripción del día');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Un resumen', 'topics' => ['Trabajo', 'Ocio']]));

        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);

        self::assertNotNull($dailySummary);
        self::assertSame('Un resumen', $dailySummary->getSummaryText());
        self::assertCount(2, $dailySummary->getTopics());
    }

    public function testReExecutionUpdatesInsteadOfDuplicating(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-2', 'summary-file-2', 'transcripción');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Primera versión', 'topics' => ['Trabajo']]));
        $service->generateForDate($this->testDate);

        $service2 = $this->createService($this->fakeGenerator(['summary' => 'Segunda versión', 'topics' => ['Ocio']]));
        $service2->generateForDate($this->testDate);

        $summaries = $this->entityManager->getRepository(\App\Entity\DailySummary::class)->findBy(['date' => $this->testDate]);

        self::assertCount(1, $summaries);
        self::assertSame('Segunda versión', $summaries[0]->getSummaryText());
        self::assertCount(1, $summaries[0]->getTopics());
        self::assertSame('Ocio', $summaries[0]->getTopics()->first()->getName());
    }

    public function testNoTranscriptionsMeansNoSummaryCreated(): void
    {
        $service = $this->createService($this->fakeGenerator(['summary' => 'no debería llamarse', 'topics' => []]));

        $service->generateForDate($this->testDate);

        self::assertNull($this->dailySummaryRepository->findOneByDate($this->testDate));
    }

    public function testWaitForPendingFalseSkipsWaitAndGeneratesImmediately(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-4', 'summary-file-4', 'transcripción disponible');
        $this->createPendingAudioRecording('summary-msg-4b', 'summary-file-4b');

        $service = $this->createService(
            $this->fakeGenerator(['summary' => 'Resumen inmediato', 'topics' => []]),
            pendingWaitIntervalSeconds: 2,
            pendingWaitMaxAttempts: 5,
        );

        $start = microtime(true);
        $service->generateForDate($this->testDate, waitForPending: false);
        $elapsed = microtime(true) - $start;

        self::assertLessThan(1.5, $elapsed, 'No debe esperar por transcripciones pendientes cuando waitForPending es false');

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        self::assertNotNull($dailySummary);
        self::assertSame('Resumen inmediato', $dailySummary->getSummaryText());
    }

    public function testFailureAfterRetriesDoesNotPersistAndNotifies(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-3', 'summary-file-3', 'transcripción');

        $alwaysFailingGenerator = new class () implements SummaryGeneratorInterface {
            public int $calls = 0;

            public function generate(array $transcriptions): array
            {
                ++$this->calls;

                throw new SummaryGenerationException('TIMEOUT', 'fallo simulado');
            }
        };

        $service = $this->createService($alwaysFailingGenerator, generationRetryDelaySeconds: 0);
        $service->generateForDate($this->testDate);

        self::assertNull($this->dailySummaryRepository->findOneByDate($this->testDate));
        self::assertSame(3, $alwaysFailingGenerator->calls);
    }

    public function testSuccessfulGenerationNotifiesByTelegram(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-5', 'summary-file-5', 'transcripción del día');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Un resumen para Telegram', 'topics' => []]));
        $service->generateForDate($this->testDate);

        self::assertNotNull($this->dailySummaryRepository->findOneByDate($this->testDate));

        $sendMessageRequests = $this->sendMessageRequests();
        self::assertCount(1, $sendMessageRequests);

        $body = json_decode((string) $sendMessageRequests[0]['body'], true);
        self::assertSame((int) $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'], $body['chat_id']);
        self::assertSame('Un resumen para Telegram', $body['text']);
    }

    public function testRegenerationNotifiesWithUpdatedText(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-6', 'summary-file-6', 'transcripción');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Primera versión', 'topics' => []]));
        $service->generateForDate($this->testDate);

        $service2 = $this->createService($this->fakeGenerator(['summary' => 'Segunda versión', 'topics' => []]));
        $service2->generateForDate($this->testDate);

        $sendMessageRequests = $this->sendMessageRequests();
        self::assertCount(2, $sendMessageRequests);

        $lastBody = json_decode((string) $sendMessageRequests[1]['body'], true);
        self::assertSame('Segunda versión', $lastBody['text']);
    }

    public function testTelegramNotificationFailureDoesNotPreventPersistence(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-7', 'summary-file-7', 'transcripción');

        $this->httpResponder = static fn (): MockResponse => throw new TransportException('fallo de red simulado');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Resumen pese al fallo', 'topics' => []]));
        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        self::assertNotNull($dailySummary);
        self::assertSame('Resumen pese al fallo', $dailySummary->getSummaryText());
    }

    /**
     * @return list<array{url: string, body: mixed}>
     */
    private function sendMessageRequests(): array
    {
        return array_values(array_filter($this->httpRequests, static fn (array $request) => str_contains($request['url'], '/sendMessage')));
    }

    private function createService(
        SummaryGeneratorInterface $generator,
        int $generationRetryDelaySeconds = 0,
        int $pendingWaitIntervalSeconds = 0,
        int $pendingWaitMaxAttempts = 1,
    ): DailySummaryService {
        return new DailySummaryService(
            $this->audioRecordingRepository,
            $this->dailySummaryRepository,
            $this->topicRepository,
            $generator,
            $this->entityManager,
            self::getContainer()->get(TelegramClient::class),
            self::getContainer()->get('logger'),
            $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'],
            pendingWaitIntervalSeconds: $pendingWaitIntervalSeconds,
            pendingWaitMaxAttempts: $pendingWaitMaxAttempts,
            generationMaxAttempts: 3,
            generationRetryDelaySeconds: $generationRetryDelaySeconds,
        );
    }

    private function fakeGenerator(array $result): SummaryGeneratorInterface
    {
        return new class ($result) implements SummaryGeneratorInterface {
            public function __construct(private readonly array $result)
            {
            }

            public function generate(array $transcriptions): array
            {
                return $this->result;
            }
        };
    }

    private function createTranscribedAudioRecording(string $telegramMessageId, string $telegramFileUniqueId, string $transcriptionContent): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath('/data/audio/'.$telegramFileUniqueId.'.ogg')
            ->setReceivedAt($this->testDate)
            ->setDurationSeconds(5)
            ->setStatus(AudioRecordingStatus::TRANSCRIBED)
        ;
        $this->entityManager->persist($audioRecording);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($transcriptionContent)
            ->setFilePath('/data/transcriptions/'.$telegramFileUniqueId.'.txt')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function createPendingAudioRecording(string $telegramMessageId, string $telegramFileUniqueId): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath('/data/audio/'.$telegramFileUniqueId.'.ogg')
            ->setReceivedAt($this->testDate)
            ->setDurationSeconds(5)
            ->setStatus(AudioRecordingStatus::PENDING)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    private function cleanUp(): void
    {
        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        if (null !== $dailySummary) {
            $this->entityManager->remove($dailySummary);
        }

        foreach (['summary-msg-1', 'summary-msg-2', 'summary-msg-3', 'summary-msg-4', 'summary-msg-4b', 'summary-msg-5', 'summary-msg-6', 'summary-msg-7'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();

        foreach (['Trabajo', 'Ocio'] as $topicName) {
            $topic = $this->topicRepository->findOneByName($topicName);
            if (null !== $topic) {
                $this->entityManager->remove($topic);
            }
        }
        $this->entityManager->flush();
    }
}
