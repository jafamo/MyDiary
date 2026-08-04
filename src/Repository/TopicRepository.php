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
}
