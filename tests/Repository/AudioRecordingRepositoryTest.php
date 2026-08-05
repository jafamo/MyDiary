<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AudioRecordingRepositoryTest extends KernelTestCase
{
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
        $this->entityManager->flush();
    }
}
