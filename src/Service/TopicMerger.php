<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;

class TopicMerger
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Reasigna a `$destination` todos los `DailySummary` de cada `Topic` en `$origins`
     * (sin duplicar si un resumen ya estaba asociado a ambos) y elimina los `$origins`.
     *
     * @param list<Topic> $origins
     */
    public function merge(array $origins, Topic $destination): void
    {
        foreach ($origins as $origin) {
            if ($origin === $destination) {
                continue;
            }

            foreach ($origin->getDailySummaries() as $dailySummary) {
                $dailySummary->addTopic($destination);
                $dailySummary->removeTopic($origin);
            }

            $this->entityManager->remove($origin);
        }

        $this->entityManager->flush();
    }
}
