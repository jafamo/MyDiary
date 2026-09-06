<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Repository\TranscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:transcription:backfill-embeddings',
    description: 'Regenera el embedding de las Transcription que no lo tienen guardado',
)]
class BackfillTranscriptionEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly TranscriptionRepository $transcriptionRepository,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $transcriptions = $this->transcriptionRepository->findAllWithoutEmbedding();

        if ([] === $transcriptions) {
            $io->success('No hay transcripciones sin embedding.');

            return Command::SUCCESS;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($transcriptions as $transcription) {
            try {
                $embedding = $this->embeddingGenerator->generate($transcription->getContent());
            } catch (EmbeddingGenerationException $exception) {
                $io->warning(sprintf('Transcription #%d: %s', $transcription->getId(), $exception->getErrorMessage()));
                ++$failureCount;

                continue;
            }

            $transcription->setEmbedding($embedding);
            ++$successCount;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d embedding(s) generado(s), %d fallo(s).', $successCount, $failureCount));

        return Command::SUCCESS;
    }
}
