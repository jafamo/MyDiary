<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DailySummaryService;
use App\Service\DateRange;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:recheck-daily-summary',
    description: 'Regenera y reenvía el resumen diario de hoy si han llegado transcripciones nuevas desde la última generación',
)]
class RecheckDailySummaryCommand extends Command
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = DateRange::nowInMadrid();

        if (!$this->dailySummaryService->hasNewTranscriptionsSince($date)) {
            $this->logger->info('Recheck del resumen diario: sin transcripciones nuevas', [
                'event' => 'daily_summary.recheck_no_new_transcriptions',
                'date' => $date->format('Y-m-d'),
            ]);

            $io->success('Sin transcripciones nuevas, no se regenera el resumen.');

            return Command::SUCCESS;
        }

        $this->dailySummaryService->generateForDate($date, waitForPending: false);

        $io->success(sprintf('Resumen diario regenerado para %s.', $date->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
