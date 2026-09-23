<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
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
use App\Service\UsageFormatter;
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

    public function testGeneratesAndSavesEmbeddingForNewSummary(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-8', 'summary-file-8', 'transcripción del día');

        $service = $this->createService(
            $this->fakeGenerator(['summary' => 'Un resumen', 'topics' => []]),
            embeddingGenerator: $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );
        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);

        self::assertNotNull($dailySummary);
        self::assertSame($this->unitVector(0), array_map('floatval', $dailySummary->getEmbedding()->toArray()));
    }

    public function testRegeneratesEmbeddingWhenSummaryIsRegenerated(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-9', 'summary-file-9', 'transcripción');

        $service = $this->createService(
            $this->fakeGenerator(['summary' => 'Primera versión', 'topics' => []]),
            embeddingGenerator: $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );
        $service->generateForDate($this->testDate);

        $service2 = $this->createService(
            $this->fakeGenerator(['summary' => 'Segunda versión', 'topics' => []]),
            embeddingGenerator: $this->fakeEmbeddingGenerator($this->unitVector(1)),
        );
        $service2->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        self::assertSame($this->unitVector(1), array_map('floatval', $dailySummary->getEmbedding()->toArray()));
    }

    public function testEmbeddingGenerationFailureDoesNotPreventSummaryFromBeingSaved(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-10', 'summary-file-10', 'transcripción');

        $failingEmbeddingGenerator = new class () implements EmbeddingGeneratorInterface {
            public function generate(string $text): array
            {
                throw new EmbeddingGenerationException('TIMEOUT', 'fallo simulado');
            }
        };

        $service = $this->createService(
            $this->fakeGenerator(['summary' => 'Resumen pese al fallo de embedding', 'topics' => []]),
            embeddingGenerator: $failingEmbeddingGenerator,
        );
        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);

        self::assertNotNull($dailySummary);
        self::assertSame('Resumen pese al fallo de embedding', $dailySummary->getSummaryText());
        self::assertNull($dailySummary->getEmbedding());
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
        self::assertSame("📔 Resumen día: 4 de agosto de 2026\n\nUn resumen para Telegram\n\n🧮 1 audio · ⏱️ 0 s · 🤖 modelo-falso", $body['text']);
    }

    public function testSavesUsageMetricsAndReportsThemOnTelegram(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-20', 'summary-file-20', 'primera transcripción');
        $this->createTranscribedAudioRecording('summary-msg-21', 'summary-file-21', 'segunda transcripción');

        $service = $this->createService($this->fakeGenerator([
            'summary' => 'Resumen con consumo',
            'topics' => [],
            'usage' => ['promptTokens' => 2980, 'completionTokens' => 432, 'model' => 'qwen2.5:7b'],
        ]));
        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        self::assertSame(2980, $dailySummary->getPromptTokens());
        self::assertSame(432, $dailySummary->getCompletionTokens());
        self::assertSame('qwen2.5:7b', $dailySummary->getModel());
        self::assertNotNull($dailySummary->getGenerationMs());

        $body = json_decode((string) $this->sendMessageRequests()[0]['body'], true);
        self::assertStringEndsWith("\n\n🧮 2 audios · 3.412 tokens (2.980 entrada + 432 salida) · ⏱️ 0 s · 🤖 qwen2.5:7b", $body['text']);
    }

    public function testRegenerationReplacesUsageMetrics(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-22', 'summary-file-22', 'transcripción');

        $this->createService($this->fakeGenerator([
            'summary' => 'Primera versión',
            'topics' => [],
            'usage' => ['promptTokens' => 1000, 'completionTokens' => 100, 'model' => 'modelo-a'],
        ]))->generateForDate($this->testDate);

        $this->createService($this->fakeGenerator([
            'summary' => 'Segunda versión',
            'topics' => [],
        ]))->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        self::assertNull($dailySummary->getPromptTokens());
        self::assertNull($dailySummary->getCompletionTokens());
        self::assertSame('modelo-falso', $dailySummary->getModel());
    }

    public function testNotificationEndsWithTopicsAndEmojiLegend(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-15', 'summary-file-15', 'transcripción del día');

        $service = $this->createService($this->fakeGenerator([
            'summary' => "💼 Informe entregado\n\n✅ Llamar al fontanero",
            'topics' => ['Informe de ventas', 'Fuga del baño'],
            'legend' => [['emoji' => '💼', 'meaning' => 'Trabajo'], ['emoji' => '✅', 'meaning' => 'Pendientes']],
        ]));
        $service->generateForDate($this->testDate);

        $body = json_decode((string) $this->sendMessageRequests()[0]['body'], true);
        self::assertSame(
            "📔 Resumen día: 4 de agosto de 2026\n\n💼 Informe entregado\n\n✅ Llamar al fontanero\n\n🏷️ Informe de ventas · Fuga del baño\n\n💼 Trabajo · ✅ Pendientes\n\n🧮 1 audio · ⏱️ 0 s · 🤖 modelo-falso",
            $body['text'],
        );
    }

    public function testNotificationWithTopicsButWithoutLegend(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-16', 'summary-file-16', 'transcripción del día');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Resumen', 'topics' => ['Cine']]));
        $service->generateForDate($this->testDate);

        $body = json_decode((string) $this->sendMessageRequests()[0]['body'], true);
        self::assertSame("📔 Resumen día: 4 de agosto de 2026\n\nResumen\n\n🏷️ Cine\n\n🧮 1 audio · ⏱️ 0 s · 🤖 modelo-falso", $body['text']);
    }

    public function testEmojiLegendIsPersistedAndReplacedOnRegeneration(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-17', 'summary-file-17', 'transcripción');

        $service = $this->createService($this->fakeGenerator([
            'summary' => '💼 Primera versión',
            'topics' => [],
            'legend' => [['emoji' => '💼', 'meaning' => 'Trabajo']],
        ]));
        $service->generateForDate($this->testDate);

        self::assertSame([['emoji' => '💼', 'meaning' => 'Trabajo']], $this->dailySummaryRepository->findOneByDate($this->testDate)->getEmojiLegend());

        $service2 = $this->createService($this->fakeGenerator(['summary' => 'Segunda versión sin emojis', 'topics' => []]));
        $service2->generateForDate($this->testDate);

        $this->entityManager->clear();
        self::assertSame([], $this->dailySummaryRepository->findOneByDate($this->testDate)->getEmojiLegend());
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
        self::assertSame("📔 Resumen día: 4 de agosto de 2026\n\nSegunda versión\n\n🧮 1 audio · ⏱️ 0 s · 🤖 modelo-falso", $lastBody['text']);
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

    public function testHasNewTranscriptionsSinceIsTrueWhenAudioIsNewerThanSummary(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-11', 'summary-file-11', 'primera transcripción');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Resumen', 'topics' => []]));
        $service->generateForDate($this->testDate);

        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->testDate);
        $dailySummary->setGeneratedAt($this->testDate);
        $this->entityManager->flush();

        $this->createTranscribedAudioRecording('summary-msg-12', 'summary-file-12', 'segunda transcripción', $this->testDate->modify('+2 hours'));

        self::assertTrue($service->hasNewTranscriptionsSince($this->testDate));
    }

    public function testHasNewTranscriptionsSinceIsFalseWhenSummaryIsAlreadyUpToDate(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-13', 'summary-file-13', 'transcripción');

        $service = $this->createService($this->fakeGenerator(['summary' => 'Resumen', 'topics' => []]));
        $service->generateForDate($this->testDate);

        self::assertFalse($service->hasNewTranscriptionsSince($this->testDate));
    }

    public function testHasNewTranscriptionsSinceIsTrueWhenNoSummaryExistsButThereIsTranscribedAudio(): void
    {
        $this->createTranscribedAudioRecording('summary-msg-14', 'summary-file-14', 'transcripción');

        $service = $this->createService($this->fakeGenerator(['summary' => 'no debería llamarse', 'topics' => []]));

        self::assertTrue($service->hasNewTranscriptionsSince($this->testDate));
    }

    public function testHasNewTranscriptionsSinceIsFalseWhenNoSummaryAndNoAudio(): void
    {
        $service = $this->createService($this->fakeGenerator(['summary' => 'no debería llamarse', 'topics' => []]));

        self::assertFalse($service->hasNewTranscriptionsSince($this->testDate));
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
        ?EmbeddingGeneratorInterface $embeddingGenerator = null,
    ): DailySummaryService {
        return new DailySummaryService(
            $this->audioRecordingRepository,
            $this->dailySummaryRepository,
            $this->topicRepository,
            $generator,
            $embeddingGenerator ?? $this->fakeEmbeddingGenerator($this->unitVector(0)),
            $this->entityManager,
            self::getContainer()->get(TelegramClient::class),
            new UsageFormatter(),
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
            private readonly array $result;

            public function __construct(array $result)
            {
                $this->result = $result + [
                    'legend' => [],
                    'usage' => ['promptTokens' => null, 'completionTokens' => null, 'model' => 'modelo-falso'],
                ];
            }

            public function generate(array $transcriptions): array
            {
                return $this->result;
            }
        };
    }

    /**
     * @return list<float> vector de 768 dimensiones (una embedding de nomic-embed-text) con un único 1.0 en $activeIndex
     */
    private function unitVector(int $activeIndex): array
    {
        $vector = array_fill(0, 768, 0.0);
        $vector[$activeIndex] = 1.0;

        return $vector;
    }

    /**
     * @param list<float> $embedding
     */
    private function fakeEmbeddingGenerator(array $embedding): EmbeddingGeneratorInterface
    {
        return new class ($embedding) implements EmbeddingGeneratorInterface {
            public function __construct(private readonly array $embedding)
            {
            }

            public function generate(string $text): array
            {
                return $this->embedding;
            }
        };
    }

    private function createTranscribedAudioRecording(string $telegramMessageId, string $telegramFileUniqueId, string $transcriptionContent, ?\DateTimeImmutable $receivedAt = null): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath('/data/audio/'.$telegramFileUniqueId.'.ogg')
            ->setReceivedAt($receivedAt ?? $this->testDate)
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

        foreach (['summary-msg-1', 'summary-msg-2', 'summary-msg-3', 'summary-msg-4', 'summary-msg-4b', 'summary-msg-5', 'summary-msg-6', 'summary-msg-7', 'summary-msg-8', 'summary-msg-9', 'summary-msg-10', 'summary-msg-11', 'summary-msg-12', 'summary-msg-13', 'summary-msg-14', 'summary-msg-15', 'summary-msg-16', 'summary-msg-17', 'summary-msg-20', 'summary-msg-21', 'summary-msg-22'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();

        foreach (['Trabajo', 'Ocio', 'Informe de ventas', 'Fuga del baño', 'Cine'] as $topicName) {
            $topic = $this->topicRepository->findOneByName($topicName);
            if (null !== $topic) {
                $this->entityManager->remove($topic);
            }
        }
        $this->entityManager->flush();
    }
}
