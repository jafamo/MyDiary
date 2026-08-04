<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AudioRecording;
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
}
