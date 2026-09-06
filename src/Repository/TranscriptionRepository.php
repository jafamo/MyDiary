<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Transcription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;

/**
 * @extends ServiceEntityRepository<Transcription>
 */
class TranscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transcription::class);
    }

    /**
     * @return list<Transcription>
     */
    public function findAllWithoutEmbedding(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.embedding IS NULL')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param list<float> $queryEmbedding
     *
     * @return list<array{transcription: Transcription, distance: float}>
     */
    public function searchBySimilarity(array $queryEmbedding, int $limit): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t')
            ->addSelect('cosine_distance(t.embedding, :queryEmbedding) AS distance')
            ->andWhere('t.embedding IS NOT NULL')
            ->orderBy('distance', 'ASC')
            ->setParameter('queryEmbedding', new Vector($queryEmbedding))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;

        return array_map(static fn (array $row) => [
            'transcription' => $row[0],
            'distance' => (float) $row['distance'],
        ], $rows);
    }
}
