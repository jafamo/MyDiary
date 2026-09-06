<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Service\AudioRecordingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:audio:retry-transcription',
    description: 'Reencola la transcripción de audios atascados en PENDING o marcados como ERROR',
)]
class RetryAudioTranscriptionCommand extends Command
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly AudioRecordingService $audioRecordingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY, 'IDs de audio_recording a reencolar; si se omite, se buscan los atascados automáticamente')
            ->addOption('pending-minutes', null, InputOption::VALUE_REQUIRED, 'Antigüedad mínima (en minutos) para considerar un PENDING como atascado', '15')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'No pedir confirmación')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ids = array_map('intval', $input->getArgument('ids'));

        if ([] !== $ids) {
            $audioRecordings = array_filter(array_map(
                fn (int $id) => $this->audioRecordingRepository->find($id),
                $ids,
            ));

            if (\count($audioRecordings) !== \count($ids)) {
                $io->warning('Alguno de los IDs indicados no existe y será ignorado.');
            }
        } else {
            $pendingMinutes = (int) $input->getOption('pending-minutes');
            $threshold = new \DateTimeImmutable(sprintf('-%d minutes', $pendingMinutes));
            $audioRecordings = $this->audioRecordingRepository->findStuck($threshold);
        }

        $audioRecordings = array_values(array_filter(
            $audioRecordings,
            static fn ($audioRecording) => AudioRecordingStatus::TRANSCRIBED !== $audioRecording->getStatus(),
        ));

        if ([] === $audioRecordings) {
            $io->success('No hay audios que reencolar.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Estado', 'Recibido', 'error_code'],
            array_map(static fn ($audioRecording) => [
                $audioRecording->getId(),
                $audioRecording->getStatus()->value,
                $audioRecording->getReceivedAt()->format('Y-m-d H:i:s'),
                $audioRecording->getErrorCode() ?? '',
            ], $audioRecordings),
        );

        if (!$input->getOption('yes') && !$io->confirm(sprintf('¿Reencolar %d audio(s)?', \count($audioRecordings)), false)) {
            $io->warning('Cancelado.');

            return Command::SUCCESS;
        }

        foreach ($audioRecordings as $audioRecording) {
            $this->audioRecordingService->retryAfterError($audioRecording);
        }

        $io->success(sprintf('%d audio(s) reencolado(s) para transcripción.', \count($audioRecordings)));

        return Command::SUCCESS;
    }
}
