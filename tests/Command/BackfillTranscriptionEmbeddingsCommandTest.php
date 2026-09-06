<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BackfillTranscriptionEmbeddingsCommand;
use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\AudioRecording;
use App\Entity\Transcription;
use App\Repository\AudioRecordingRepository;
use App\Repository\TranscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class BackfillTranscriptionEmbeddingsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private TranscriptionRepository $transcriptionRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->transcriptionRepository = $container->get(TranscriptionRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testBackfillsMissingEmbeddingsAndReportsFailures(): void
    {
        $success = $this->createTranscription('backfill-trans-1', 'contenido con éxito');
        $failure = $this->createTranscription('backfill-trans-2', 'contenido con fallo');

        $embeddingGenerator = new class ($this->unitVector(0)) implements EmbeddingGeneratorInterface {
            public function __construct(private readonly array $successEmbedding)
            {
            }

            public function generate(string $text): array
            {
                if (str_contains($text, 'fallo')) {
                    throw new EmbeddingGenerationException('TIMEOUT', 'fallo simulado');
                }

                return $this->successEmbedding;
            }
        };

        $application = new Application();
        $application->addCommand(new BackfillTranscriptionEmbeddingsCommand(
            $this->transcriptionRepository,
            $embeddingGenerator,
            $this->entityManager,
        ));
        $commandTester = new CommandTester($application->find('app:transcription:backfill-embeddings'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 embedding(s) generado(s), 1 fallo(s)', $commandTester->getDisplay());

        $this->entityManager->refresh($success);
        $this->entityManager->refresh($failure);
        self::assertSame($this->unitVector(0), array_map('floatval', $success->getEmbedding()->toArray()));
        self::assertNull($failure->getEmbedding());
    }

    public function testReportsSuccessWhenNothingToBackfill(): void
    {
        $application = new Application();
        $application->addCommand(new BackfillTranscriptionEmbeddingsCommand(
            $this->transcriptionRepository,
            new class () implements EmbeddingGeneratorInterface {
                public function generate(string $text): array
                {
                    throw new \LogicException('No debería llamarse.');
                }
            },
            $this->entityManager,
        ));
        $commandTester = new CommandTester($application->find('app:transcription:backfill-embeddings'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('No hay transcripciones sin embedding', $commandTester->getDisplay());
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

    private function createTranscription(string $telegramMessageId, string $content): Transcription
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath('/data/audio/'.$telegramMessageId.'.ogg')
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($content)
            ->setFilePath('/data/transcriptions/'.$telegramMessageId.'.txt')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        return $transcription;
    }

    private function cleanUp(): void
    {
        foreach (['backfill-trans-1', 'backfill-trans-2'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();
    }
}
