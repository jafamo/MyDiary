<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailySummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailySummary>
 */
class DailySummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailySummary::class);
    }

    public function findOneByDate(\DateTimeImmutable $date): ?DailySummary
    {
        return $this->findOneBy(['date' => $date]);
    }

    /**
     * @return list<string> fechas (Y-m-d) con DailySummary generado dentro del rango
     */
    public function findDatesWithSummaryInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('d')
            ->select('d.date AS date')
            ->andWhere('d.date >= :from')
            ->andWhere('d.date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row) => $row['date']->format('Y-m-d'), $rows);
    }

    /**
     * Número de días con DailySummary generado dentro del rango.
     */
    public function countInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->andWhere('d.date >= :from')
            ->andWhere('d.date <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }
}
