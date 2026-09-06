<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\AudioRecording;
use App\Entity\Transcription;
use App\Repository\AudioRecordingRepository;
use App\Service\TranscriptionEditor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TranscriptionEditorTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private string $exportFilePath;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
        $this->exportFilePath = tempnam(sys_get_temp_dir(), 'transcription-editor-test-');
    }

    protected function tearDown(): void
    {
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('editor-msg-1');
        if (null !== $audioRecording) {
            $this->entityManager->remove($audioRecording);
            $this->entityManager->flush();
        }

        if (file_exists($this->exportFilePath)) {
            unlink($this->exportFilePath);
        }

        parent::tearDown();
    }

    public function testApplyManualEditRegeneratesEmbedding(): void
    {
        $transcription = $this->createTranscription();

        $editor = new TranscriptionEditor(
            $this->entityManager,
            $this->fakeEmbeddingGenerator($this->unitVector(0)),
            self::getContainer()->get('logger'),
        );

        $transcription->setContent('texto editado a mano');
        $editor->applyManualEdit($transcription);

        self::assertSame($this->unitVector(0), array_map('floatval', $transcription->getEmbedding()->toArray()));
    }

    public function testApplyManualEditKeepsPreviousEmbeddingWhenGenerationFails(): void
    {
        $transcription = $this->createTranscription();
        $transcription->setEmbedding($this->unitVector(1));
        $this->entityManager->flush();

        $failingGenerator = new class () implements EmbeddingGeneratorInterface {
            public function generate(string $text): array
            {
                throw new EmbeddingGenerationException('TIMEOUT', 'fallo simulado');
            }
        };

        $editor = new TranscriptionEditor($this->entityManager, $failingGenerator, self::getContainer()->get('logger'));

        $transcription->setContent('texto editado a mano');
        $editor->applyManualEdit($transcription);

        self::assertTrue($transcription->isEditedManually());
        self::assertSame($this->unitVector(1), array_map('floatval', $transcription->getEmbedding()->toArray()));
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

    private function createTranscription(): Transcription
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('editor-msg-1')
            ->setTelegramFileUniqueId('editor-file-1')
            ->setFilePath('/data/audio/editor-file-1.ogg')
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent('texto original')
            ->setFilePath($this->exportFilePath)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        return $transcription;
    }
}
