<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AudioRecording;
use App\Entity\Transcription;
use App\Repository\AudioRecordingRepository;
use App\Repository\TranscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TranscriptionRepositoryTest extends KernelTestCase
{
    private const TELEGRAM_MESSAGE_IDS = ['trans-repo-1', 'trans-repo-2', 'trans-repo-3'];

    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private TranscriptionRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->repository = $container->get(TranscriptionRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testSearchBySimilarityOrdersByCosineDistanceAscending(): void
    {
        $this->createTranscription('trans-repo-1', 'presupuesto del mes', $this->unitVector(0));
        $this->createTranscription('trans-repo-2', 'paseo por el parque', $this->unitVector(1));
        $this->createTranscription('trans-repo-3', 'sin embedding disponible', null);

        $results = $this->repository->searchBySimilarity($this->unitVector(0), 10);

        self::assertCount(2, $results);
        self::assertSame('presupuesto del mes', $results[0]['transcription']->getContent());
        self::assertSame('paseo por el parque', $results[1]['transcription']->getContent());
        self::assertLessThan($results[1]['distance'], $results[0]['distance']);
    }

    public function testSearchBySimilarityExcludesTranscriptionsWithoutEmbedding(): void
    {
        $this->createTranscription('trans-repo-3', 'sin embedding disponible', null);

        $results = $this->repository->searchBySimilarity($this->unitVector(0), 10);

        self::assertSame([], $results);
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
     * @param list<float>|null $embedding
     */
    private function createTranscription(string $telegramMessageId, string $content, ?array $embedding): Transcription
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
            ->setEmbedding($embedding)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        return $transcription;
    }

    private function cleanUp(): void
    {
        foreach (self::TELEGRAM_MESSAGE_IDS as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $this->entityManager->flush();
    }
}
