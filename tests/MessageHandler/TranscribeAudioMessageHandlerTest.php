<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Contract\TranscriberInterface;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Message\TranscribeAudioMessage;
use App\MessageHandler\TranscribeAudioMessageHandler;
use App\Repository\AudioRecordingRepository;
use App\Service\Telegram\TelegramClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranscribeAudioMessageHandlerTest extends KernelTestCase
{
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
        foreach (['handler-msg-1', 'handler-msg-2'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
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

    private function createAudioRecording(string $telegramMessageId, string $telegramFileUniqueId): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath($this->audioFilePath)
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
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
                return $this->content;
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
            $this->entityManager,
            self::getContainer()->get('logger'),
            sys_get_temp_dir(),
            $_ENV['TELEGRAM_AUTHORIZED_CHAT_ID'],
        );
    }
}
