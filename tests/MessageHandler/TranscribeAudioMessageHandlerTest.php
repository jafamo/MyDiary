<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Contract\TranscriberInterface;
use App\Contract\TranscriptionException;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Message\TranscribeAudioMessage;
use App\MessageHandler\TranscribeAudioMessageHandler;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use App\Service\UsageFormatter;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranscribeAudioMessageHandlerTest extends KernelTestCase
{
    private const APP_CONTENT_HASH = 'dddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddddd';

    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private string $audioFilePath;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);

        $this->audioFilePath = tempnam(sys_get_temp_dir(), 'handler-test-');
        file_put_contents($this->audioFilePath, 'fake-audio-bytes');
    }

    protected function tearDown(): void
    {
        foreach (['handler-msg-1', 'handler-msg-2', 'handler-msg-3', 'handler-msg-log'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }
        $appAudioRecording = $this->audioRecordingRepository->findOneByContentHash(self::APP_CONTENT_HASH);
        if (null !== $appAudioRecording) {
            $this->entityManager->remove($appAudioRecording);
        }
        $this->entityManager->flush();

        if (file_exists($this->audioFilePath)) {
            unlink($this->audioFilePath);
        }

        parent::tearDown();
    }

    public function testSuccessfulTranscriptionUpdatesRecordAndSavesEmbedding(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = $this->createAudioRecording('handler-msg-1', 'handler-file-1');

        $handler = $this->createHandler(
            $this->fakeTranscriber('texto transcrito'),
            $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );

        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
        self::assertNotNull($audioRecording->getTranscription());
        self::assertSame('texto transcrito', $audioRecording->getTranscription()->getContent());
        self::assertSame($this->unitVector(0), array_map('floatval', $audioRecording->getTranscription()->getEmbedding()->toArray()));

        $exportPath = $audioRecording->getTranscription()->getFilePath();
        self::assertFileExists($exportPath);
        unlink($exportPath);
    }

    public function testSuccessfulTranscriptionIsLogged(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = $this->createAudioRecording('handler-msg-log', 'handler-file-log');

        $handler = $this->createHandler(
            $this->fakeTranscriber('texto transcrito'),
            $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );

        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);

        /** @var TestHandler $logHandler */
        // Handler "test" definido solo en when@test (PHPStan analiza el contenedor de dev)
        $logHandler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound
        $records = array_values(array_filter(
            $logHandler->getRecords(),
            static fn ($record) => 'transcription.created' === ($record->context['event'] ?? null),
        ));

        self::assertCount(1, $records);
        self::assertSame($audioRecording->getId(), $records[0]->context['audio_recording_id']);
        self::assertSame($audioRecording->getTranscription()->getId(), $records[0]->context['transcription_id']);
        self::assertSame('TRANSCRIBED', $records[0]->context['audio_recording_status']);

        unlink($audioRecording->getTranscription()->getFilePath());
    }

    public function testEmbeddingGenerationFailureDoesNotBlockTranscription(): void
    {
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse('{"ok":true}')));

        $audioRecording = $this->createAudioRecording('handler-msg-2', 'handler-file-2');

        $failingEmbeddingGenerator = new class () implements EmbeddingGeneratorInterface {
            public function generate(string $text): array
            {
                throw new EmbeddingGenerationException('TIMEOUT', 'fallo simulado');
            }
        };

        $handler = $this->createHandler($this->fakeTranscriber('texto transcrito'), $failingEmbeddingGenerator);
        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);

        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
        self::assertNotNull($audioRecording->getTranscription());
        self::assertSame('texto transcrito', $audioRecording->getTranscription()->getContent());
        self::assertNull($audioRecording->getTranscription()->getEmbedding());

        $exportPath = $audioRecording->getTranscription()->getFilePath();
        self::assertFileExists($exportPath);
        unlink($exportPath);
    }

    public function testSuccessfulTranscriptionSavesUsageMetricsAndReportsThemOnTelegram(): void
    {
        $sentTexts = [];
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$sentTexts): MockResponse {
                $sentTexts[] = json_decode($options['body'], true)['text'];

                return new MockResponse('{"ok":true}');
            },
        ));

        $audioRecording = $this->createAudioRecording('handler-msg-3', 'handler-file-3', 102);

        $handler = $this->createHandler(
            $this->fakeTranscriber('texto transcrito'),
            $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );

        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);
        $transcription = $audioRecording->getTranscription();

        self::assertSame('whisper-test', $transcription->getModel());
        self::assertGreaterThanOrEqual(20, $transcription->getProcessingMs());

        self::assertCount(1, $sentTexts);
        self::assertStringStartsWith("Transcripción lista ✅\n\ntexto transcrito\n\n", $sentTexts[0]);
        self::assertStringEndsWith('🎙️ 1:42 de audio · ⏱️ transcrito en 0 s', $sentTexts[0]);

        unlink($transcription->getFilePath());
    }

    public function testAudioFromTheAppIsExportedByItsHashAndNotifiedLikeAnyOther(): void
    {
        $sentTexts = [];
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$sentTexts): MockResponse {
                $sentTexts[] = json_decode($options['body'], true)['text'];

                return new MockResponse('{"ok":true}');
            },
        ));

        $audioRecording = $this->createAppAudioRecording(102);

        $handler = $this->createHandler(
            $this->fakeTranscriber('texto transcrito'),
            $this->fakeEmbeddingGenerator($this->unitVector(0)),
        );

        $handler(new TranscribeAudioMessage($audioRecording->getId()));

        $this->entityManager->refresh($audioRecording);
        $transcription = $audioRecording->getTranscription();

        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
        self::assertSame(sys_get_temp_dir().'/'.self::APP_CONTENT_HASH.'.txt', $transcription->getFilePath());
        self::assertStringEqualsFile($transcription->getFilePath(), 'texto transcrito');

        self::assertCount(1, $sentTexts);
        self::assertStringStartsWith("Transcripción lista ✅\n\ntexto transcrito\n\n", $sentTexts[0]);
        self::assertStringEndsWith('🎙️ 1:42 de audio · ⏱️ transcrito en 0 s', $sentTexts[0]);

        $created = $this->logRecordsWithEvent('transcription.created');
        self::assertCount(1, $created);
        self::assertSame('app', $created[0]->context['audio_source']);

        unlink($transcription->getFilePath());
    }

    public function testFailedAttemptOfAnAppAudioIsLoggedWithoutTelegramFileId(): void
    {
        $audioRecording = $this->createAppAudioRecording();

        try {
            $this->createHandler($this->failingTranscriber(), $this->fakeEmbeddingGenerator($this->unitVector(0)))(new TranscribeAudioMessage($audioRecording->getId()));
            self::fail('Se esperaba TranscriptionException');
        } catch (TranscriptionException) {
        }

        $records = $this->logRecordsWithEvent('transcription.attempt_failed');
        self::assertCount(1, $records);
        self::assertSame('app', $records[0]->context['audio_source']);
        self::assertArrayNotHasKey('telegram_file_unique_id', $records[0]->context);
        self::assertSame('TIMEOUT', $records[0]->context['error_code']);
    }

    public function testFailedAttemptOfATelegramAudioIsLoggedWithItsFileId(): void
    {
        $audioRecording = $this->createAudioRecording('handler-msg-1', 'handler-file-1');

        try {
            $this->createHandler($this->failingTranscriber(), $this->fakeEmbeddingGenerator($this->unitVector(0)))(new TranscribeAudioMessage($audioRecording->getId()));
            self::fail('Se esperaba TranscriptionException');
        } catch (TranscriptionException) {
        }

        $records = $this->logRecordsWithEvent('transcription.attempt_failed');
        self::assertCount(1, $records);
        self::assertSame('telegram', $records[0]->context['audio_source']);
        self::assertSame('handler-file-1', $records[0]->context['telegram_file_unique_id']);
    }

    private function createAppAudioRecording(int $durationSeconds = 5): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setSource(AudioSource::APP)
            ->setContentHash(self::APP_CONTENT_HASH)
            ->setFilePath($this->audioFilePath)
            ->setReceivedAt(new \DateTimeImmutable('2020-01-01 10:00:00'))
            ->setDurationSeconds($durationSeconds)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        return $audioRecording;
    }

    private function failingTranscriber(): TranscriberInterface
    {
        return new class () implements TranscriberInterface {
            public function transcribe(string $audioFilePath): string
            {
                throw new TranscriptionException('TIMEOUT', 'fallo simulado');
            }

            public function getModel(): string
            {
                return 'whisper-test';
            }
        };
    }

    /**
     * @return list<\Monolog\LogRecord>
     */
    private function logRecordsWithEvent(string $event): array
    {
        /** @var TestHandler $logHandler */
        // Handler "test" definido solo en when@test (PHPStan analiza el contenedor de dev)
        $logHandler = self::getContainer()->get('monolog.handler.test'); // @phpstan-ignore symfonyContainer.serviceNotFound

        return array_values(array_filter(
            $logHandler->getRecords(),
            static fn ($record) => $event === ($record->context['event'] ?? null),
        ));
    }

    private function createAudioRecording(string $telegramMessageId, string $telegramFileUniqueId, int $durationSeconds = 5): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath($this->audioFilePath)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds($durationSeconds)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        return $audioRecording;
    }

    private function fakeTranscriber(string $content): TranscriberInterface
    {
        return new class ($content) implements TranscriberInterface {
            public function __construct(private readonly string $content)
            {
            }

            public function transcribe(string $audioFilePath): string
            {
                usleep(20_000);

                return $this->content;
            }

            public function getModel(): string
            {
                return 'whisper-test';
            }
        };
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

    /**
     * @return list<float> vector de 768 dimensiones (una embedding de nomic-embed-text) con un único 1.0 en $activeIndex
     */
    private function unitVector(int $activeIndex): array
    {
        $vector = array_fill(0, 768, 0.0);
        $vector[$activeIndex] = 1.0;

        return $vector;
    }

    private function createHandler(TranscriberInterface $transcriber, EmbeddingGeneratorInterface $embeddingGenerator): TranscribeAudioMessageHandler
    {
        return new TranscribeAudioMessageHandler(
            $this->audioRecordingRepository,
            $transcriber,
            $embeddingGenerator,
            self::getContainer()->get(TelegramClient::class),
            new UsageFormatter(),
            $this->entityManager,
            self::getContainer()->get('logger'),
            sys_get_temp_dir(),
            $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'],
        );
    }
}
