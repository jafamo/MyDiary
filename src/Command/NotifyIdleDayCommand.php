<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AudioRecordingRepository;
use App\Service\DateRange;
use App\Service\Telegram\TelegramClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-idle-day',
    description: 'Envía un aviso por Telegram si hoy no se ha recibido ningún audio',
)]
class NotifyIdleDayCommand extends Command
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly TelegramClient $telegramClient,
        private readonly LoggerInterface $logger,
        private readonly string $authorizedChatId,
        private readonly string $idleDayReminderMessage,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = DateRange::nowInMadrid();
        [$start, $end] = DateRange::dayBoundaries($now);

        // Cualquier audio cuenta, venga de Telegram o de la API y esté en el estado que esté.
        $audioCount = $this->audioRecordingRepository->countReceivedBetween($start, $end);

        if ($audioCount > 0) {
            $this->logger->info('Aviso de día sin audios omitido: ya hay audios hoy', [
                'event' => 'idle_day.skipped',
                'date' => $now->format('Y-m-d'),
                'idle_day_audio_count' => $audioCount,
            ]);

            $io->success(sprintf('Hoy ya hay %d audio(s), no se envía el aviso.', $audioCount));

            return Command::SUCCESS;
        }

        $this->telegramClient->sendMessage((int) $this->authorizedChatId, $this->idleDayReminderMessage);

        $this->logger->info('Aviso de día sin audios enviado', [
            'event' => 'idle_day.notified',
            'date' => $now->format('Y-m-d'),
        ]);

        $io->success('Sin audios hoy, aviso enviado.');

        return Command::SUCCESS;
    }
}
