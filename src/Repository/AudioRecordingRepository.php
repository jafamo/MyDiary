<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AudioRecording>
 */
class AudioRecordingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AudioRecording::class);
    }

    public function findOneByTelegramMessageId(string $telegramMessageId): ?AudioRecording
    {
        return $this->findOneBy(['telegramMessageId' => $telegramMessageId]);
    }

    public function findOneByTelegramFileUniqueId(string $telegramFileUniqueId): ?AudioRecording
    {
        return $this->findOneBy(['telegramFileUniqueId' => $telegramFileUniqueId]);
    }

    /**
     * @return list<AudioRecording>
     */
    public function findPendingReceivedOn(\DateTimeImmutable $date): array
    {
        return $this->findByStatusReceivedOn(AudioRecordingStatus::PENDING, $date);
    }

    /**
     * @return list<AudioRecording>
     */
    public function findTranscribedReceivedOn(\DateTimeImmutable $date): array
    {
        return $this->findByStatusReceivedOn(AudioRecordingStatus::TRANSCRIBED, $date);
    }

    /**
     * @return list<AudioRecording>
     */
    private function findByStatusReceivedOn(AudioRecordingStatus $status, \DateTimeImmutable $date): array
    {
        [$start, $end] = DateRange::dayBoundaries($date);

        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->setParameter('status', $status)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult()
        ;
    }
}
