<?php

declare(strict_types=1);

namespace App\Service;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\Transcription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TranscriptionEditor
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly LoggerInterface $logger,
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

        $this->regenerateEmbedding($transcription);
    }

    private function regenerateEmbedding(Transcription $transcription): void
    {
        try {
            $embedding = $this->embeddingGenerator->generate($transcription->getContent());
        } catch (EmbeddingGenerationException $exception) {
            $this->logger->warning('Fallo al regenerar el embedding de una transcripción editada', [
                'event' => 'transcription.embedding_generation_failed',
                'transcription_id' => $transcription->getId(),
                'error_code' => $exception->getErrorCode(),
                'error_message' => $exception->getErrorMessage(),
            ]);

            return;
        }

        $transcription->setEmbedding($embedding);
        $this->entityManager->flush();
    }
}
