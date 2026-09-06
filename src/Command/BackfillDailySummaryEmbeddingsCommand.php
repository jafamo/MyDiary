<?php

declare(strict_types=1);

namespace App\Command;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Repository\DailySummaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:daily-summary:backfill-embeddings',
    description: 'Regenera el embedding de los DailySummary que no lo tienen guardado',
)]
class BackfillDailySummaryEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dailySummaries = $this->dailySummaryRepository->findAllWithoutEmbedding();

        if ([] === $dailySummaries) {
            $io->success('No hay resúmenes diarios sin embedding.');

            return Command::SUCCESS;
        }

        $successCount = 0;
        $failureCount = 0;

        foreach ($dailySummaries as $dailySummary) {
            try {
                $embedding = $this->embeddingGenerator->generate($dailySummary->getSummaryText());
            } catch (EmbeddingGenerationException $exception) {
                $io->warning(sprintf('DailySummary %s: %s', $dailySummary->getDate()->format('Y-m-d'), $exception->getErrorMessage()));
                ++$failureCount;

                continue;
            }

            $dailySummary->setEmbedding($embedding);
            ++$successCount;
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d embedding(s) generado(s), %d fallo(s).', $successCount, $failureCount));

        return Command::SUCCESS;
    }
}
