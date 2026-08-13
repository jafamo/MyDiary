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
     * @return list<DailySummary> los $limit DailySummary más recientes, de más reciente a más antiguo
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('d')
            ->orderBy('d.date', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return list<DailySummary> página de DailySummary dentro de [$from, $to], de más reciente a más antiguo
     */
    public function findPageInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, int $page, int $pageSize): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.date >= :from')
            ->andWhere('d.date <= :to')
            ->orderBy('d.date', 'DESC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult()
        ;
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
