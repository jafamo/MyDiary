<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Repository\AudioRecordingRepository;
use App\Service\DateRange;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AudioRecordingRepositoryTest extends KernelTestCase
{
    private const HASH_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const HASH_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $repository;
    private \DateTimeImmutable $day;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(AudioRecordingRepository::class);
        $this->day = DateRange::nowInMadrid()->setTime(0, 0, 0);

        $this->cleanUp();

        $this->createAudioRecording('status-filter-pending', AudioRecordingStatus::PENDING, 10);
        $this->createAudioRecording('status-filter-transcribed', AudioRecordingStatus::TRANSCRIBED, 20);
        $this->createAudioRecording('status-filter-error', AudioRecordingStatus::ERROR, 30);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testFindAllReceivedOnWithoutStatusReturnsEverything(): void
    {
        $entries = $this->repository->findAllReceivedOn($this->day);

        self::assertCount(3, array_filter($entries, fn (AudioRecording $a) => str_starts_with($a->getTelegramMessageId(), 'status-filter-')));
    }

    public function testFindAllReceivedOnFiltersByStatus(): void
    {
        $entries = $this->repository->findAllReceivedOn($this->day, AudioRecordingStatus::ERROR);
        $filtered = array_filter($entries, fn (AudioRecording $a) => str_starts_with($a->getTelegramMessageId(), 'status-filter-'));

        self::assertCount(1, $filtered);
        self::assertSame('status-filter-error', reset($filtered)->getTelegramMessageId());
    }

    public function testCountByDateInRangeWithoutStatusCountsEverything(): void
    {
        $counts = $this->repository->countByDateInRange($this->day, $this->day);

        self::assertSame(3, $counts[$this->day->format('Y-m-d')]);
    }

    public function testCountByDateInRangeFiltersByStatus(): void
    {
        $counts = $this->repository->countByDateInRange($this->day, $this->day, AudioRecordingStatus::TRANSCRIBED);

        self::assertSame(1, $counts[$this->day->format('Y-m-d')]);
    }

    public function testAverageDurationInRangeWithoutStatusAveragesEverything(): void
    {
        $average = $this->repository->averageDurationInRange($this->day, $this->day);

        self::assertEqualsWithDelta(20.0, $average, 0.01);
    }

    public function testAverageDurationInRangeFiltersByStatus(): void
    {
        $average = $this->repository->averageDurationInRange($this->day, $this->day, AudioRecordingStatus::ERROR);

        self::assertEqualsWithDelta(30.0, $average, 0.01);
    }

    public function testSeveralAppAudiosCanExistWithoutTelegramIds(): void
    {
        $first = $this->createAppAudioRecording(self::HASH_A);
        $second = $this->createAppAudioRecording(self::HASH_B);

        self::assertNotSame($first->getId(), $second->getId());
        self::assertSame($first->getId(), $this->repository->findOneByContentHash(self::HASH_A)?->getId());
        self::assertSame($second->getId(), $this->repository->findOneByContentHash(self::HASH_B)?->getId());
        self::assertNull($this->repository->findOneByContentHash(str_repeat('c', 64)));
    }

    public function testContentHashIsUnique(): void
    {
        $this->createAppAudioRecording(self::HASH_A);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->getConnection()->insert('audio_recording', [
            'source' => AudioSource::APP->value,
            'content_hash' => self::HASH_A,
            'file_path' => '/data/audio/duplicado.m4a',
            'received_at' => '2020-01-01 10:00:00',
            'status' => AudioRecordingStatus::PENDING->value,
            'duration_seconds' => 5,
        ]);
    }

    public function testExistingAudiosDefaultToTelegramSource(): void
    {
        $audioRecording = $this->repository->findOneByTelegramMessageId('status-filter-pending');

        self::assertSame(AudioSource::TELEGRAM, $audioRecording->getSource());
        self::assertNull($audioRecording->getContentHash());
    }

    private function createAudioRecording(string $telegramMessageId, AudioRecordingStatus $status, int $durationSeconds): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath('/data/audio/'.$telegramMessageId.'.ogg')
            ->setReceivedAt($this->day)
            ->setDurationSeconds($durationSeconds)
            ->setStatus($status)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        foreach (['status-filter-pending', 'status-filter-transcribed', 'status-filter-error'] as $telegramMessageId) {
            $audioRecording = $this->repository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }
        foreach ([self::HASH_A, self::HASH_B] as $contentHash) {
            $audioRecording = $this->repository->findOneByContentHash($contentHash);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }
        $this->entityManager->flush();
    }

    private function createAppAudioRecording(string $contentHash): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setSource(AudioSource::APP)
            ->setContentHash($contentHash)
            ->setFilePath('/data/audio/'.$contentHash.'.m4a')
            // Fecha antigua: no entra en los recuentos del día de los demás tests
            ->setReceivedAt(new \DateTimeImmutable('2020-01-01 10:00:00'))
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        return $audioRecording;
    }
}
