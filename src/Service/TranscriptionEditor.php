<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Transcription;
use Doctrine\ORM\EntityManagerInterface;

class TranscriptionEditor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function applyManualEdit(Transcription $transcription): void
    {
        $transcription
            ->setEditedManually(true)
            ->setUpdatedAt(new \DateTimeImmutable())
        ;

        $directory = \dirname($transcription->getFilePath());
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($transcription->getFilePath(), $transcription->getContent());

        $this->entityManager->flush();
    }
}
