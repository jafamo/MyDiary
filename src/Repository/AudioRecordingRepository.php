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

    /**
     * Entradas recibidas ese día, ordenadas cronológicamente. Sin `$status`, incluye cualquier estado.
     *
     * @return list<AudioRecording>
     */
    public function findAllReceivedOn(\DateTimeImmutable $date, ?AudioRecordingStatus $status = null): array
    {
        [$start, $end] = DateRange::dayBoundaries($date);

        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->orderBy('a.receivedAt', 'ASC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        if (null !== $status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Fechas distintas (Y-m-d, Europe/Madrid) en las que hay al menos un audio, más recientes primero.
     *
     * @return list<string>
     */
    public function findDistinctDatesWithAudio(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.receivedAt')
            ->orderBy('a.receivedAt', 'DESC')
            ->getQuery()
            ->getArrayResult()
        ;

        $tz = new \DateTimeZone('Europe/Madrid');
        $dates = [];
        foreach ($rows as $row) {
            $local = $row['receivedAt']->setTimezone($tz)->format('Y-m-d');
            $dates[$local] = true;
        }

        return array_keys($dates);
    }

    public function averageDurationInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?AudioRecordingStatus $status = null): float
    {
        [$start, $end] = DateRange::boundariesForDates($from, $to);

        $qb = $this->createQueryBuilder('a')
            ->select('AVG(a.durationSeconds)')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        if (null !== $status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return null !== $result ? (float) $result : 0.0;
    }

    public function countReceivedBetween(\DateTimeImmutable $start, \DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Número de audios por día (Y-m-d, Europe/Madrid) dentro del rango, incluyendo días sin audios.
     * Sin `$status`, cuenta cualquier estado.
     *
     * @return array<string, int>
     */
    public function countByDateInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?AudioRecordingStatus $status = null): array
    {
        [$start, $end] = DateRange::boundariesForDates($from, $to);

        $qb = $this->createQueryBuilder('a')
            ->select('a.receivedAt')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        if (null !== $status) {
            $qb->andWhere('a.status = :status')->setParameter('status', $status);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $tz = new \DateTimeZone('Europe/Madrid');
        $counts = [];
        foreach ($rows as $row) {
            $local = $row['receivedAt']->setTimezone($tz)->format('Y-m-d');
            $counts[$local] = ($counts[$local] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Desglose por estado dentro del rango.
     *
     * @return array<string, int> claves: PENDING, TRANSCRIBED, ERROR
     */
    public function countByStatusInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$start, $end] = DateRange::boundariesForDates($from, $to);

        $rows = $this->createQueryBuilder('a')
            ->select('a.status AS status, COUNT(a.id) AS cnt')
            ->andWhere('a.receivedAt >= :start')
            ->andWhere('a.receivedAt < :end')
            ->groupBy('a.status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult()
        ;

        $counts = ['PENDING' => 0, 'TRANSCRIBED' => 0, 'ERROR' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] instanceof AudioRecordingStatus ? $row['status']->value : $row['status'];
            $counts[$status] = (int) $row['cnt'];
        }

        return $counts;
    }
}
