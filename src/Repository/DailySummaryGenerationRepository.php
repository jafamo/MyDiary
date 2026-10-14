<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DailySummaryGeneration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DailySummaryGeneration>
 */
class DailySummaryGenerationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DailySummaryGeneration::class);
    }

    public function findOneByDate(\DateTimeImmutable $date): ?DailySummaryGeneration
    {
        return $this->findOneBy(['date' => $date->setTime(0, 0, 0)]);
    }
}
