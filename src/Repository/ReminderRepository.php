<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Reminder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    /**
     * @return list<Reminder>
     */
    public function findAllOn(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.date = :date')
            ->orderBy('r.createdAt', 'ASC')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Número de recordatorios por día (Y-m-d) dentro del rango.
     *
     * @return array<string, int>
     */
    public function countByDateInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.date AS date, COUNT(r.id) AS cnt')
            ->andWhere('r.date >= :from')
            ->andWhere('r.date <= :to')
            ->groupBy('r.date')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getArrayResult()
        ;

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['date']->format('Y-m-d')] = (int) $row['cnt'];
        }

        return $counts;
    }

    /**
     * Recordatorios entre $today y $today + $days (ambos inclusive), ordenados por fecha.
     *
     * @return list<Reminder>
     */
    public function findUpcoming(\DateTimeImmutable $today, int $days = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.date >= :from')
            ->andWhere('r.date <= :to')
            ->orderBy('r.date', 'ASC')
            ->setParameter('from', $today)
            ->setParameter('to', $today->modify(sprintf('+%d days', $days)))
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Número de recordatorios con fecha igual o posterior a $from.
     */
    public function countFromDate(\DateTimeImmutable $from): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.date >= :from')
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Página de recordatorios con fecha igual o posterior a $from, ordenados por fecha ascendente.
     *
     * @return list<Reminder>
     */
    public function findPageFromDate(\DateTimeImmutable $from, int $page, int $pageSize): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.date >= :from')
            ->orderBy('r.date', 'ASC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->setParameter('from', $from)
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Número de recordatorios con fecha anterior a $before.
     */
    public function countBeforeDate(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.date < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Página de recordatorios con fecha anterior a $before, ordenados por fecha descendente (más reciente primero).
     *
     * @return list<Reminder>
     */
    public function findPageBeforeDate(\DateTimeImmutable $before, int $page, int $pageSize): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.date < :before')
            ->orderBy('r.date', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->setParameter('before', $before)
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize)
            ->getQuery()
            ->getResult()
        ;
    }
}
