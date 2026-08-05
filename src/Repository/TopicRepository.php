<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Topic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Topic>
 */
class TopicRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Topic::class);
    }

    public function findOneByName(string $name): ?Topic
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return array{name: string, count: int}|null
     */
    public function findTopTopicForMonth(\DateTimeImmutable $month): ?array
    {
        $start = $month->modify('first day of this month');
        $end = $month->modify('first day of next month');

        $result = $this->createQueryBuilder('t')
            ->select('t.name AS name, COUNT(ds.id) AS cnt')
            ->join('t.dailySummaries', 'ds')
            ->andWhere('ds.date >= :start')
            ->andWhere('ds.date < :end')
            ->groupBy('t.id')
            ->orderBy('cnt', 'DESC')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return null !== $result ? ['name' => $result['name'], 'count' => (int) $result['cnt']] : null;
    }

    /**
     * Ranking de temas por número de menciones (días) dentro del rango.
     *
     * @return list<array{name: string, count: int}>
     */
    public function findTopicFrequencyInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, int $limit = 8): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.name AS name, COUNT(ds.id) AS cnt')
            ->join('t.dailySummaries', 'ds')
            ->andWhere('ds.date >= :from')
            ->andWhere('ds.date <= :to')
            ->groupBy('t.id')
            ->orderBy('cnt', 'DESC')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult()
        ;

        return array_map(static fn (array $row) => ['name' => $row['name'], 'count' => (int) $row['cnt']], $rows);
    }
}
