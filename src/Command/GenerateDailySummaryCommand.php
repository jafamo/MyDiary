<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DailySummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-daily-summary',
    description: 'Genera (o regenera) el resumen diario a partir de las transcripciones del día',
)]
class GenerateDailySummaryCommand extends Command
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'date',
            null,
            InputOption::VALUE_REQUIRED,
            'Fecha para la que generar el resumen (YYYY-MM-DD). Por defecto, hoy.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateOption = $input->getOption('date');
        $date = null !== $dateOption
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $dateOption)
            : new \DateTimeImmutable('today');

        if (false === $date) {
            $io->error(sprintf('Fecha inválida: "%s". Usa el formato YYYY-MM-DD.', $dateOption));

            return Command::FAILURE;
        }

        $this->dailySummaryService->generateForDate($date);

        $io->success(sprintf('Resumen diario procesado para %s.', $date->format('Y-m-d')));

        return Command::SUCCESS;
    }
}
