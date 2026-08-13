<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReminderRepository;
use App\Service\DateRange;
use App\Service\Telegram\TelegramClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:notify-reminders',
    description: 'Envía por Telegram los recordatorios del día actual, si existen',
)]
class NotifyRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderRepository $reminderRepository,
        private readonly TelegramClient $telegramClient,
        private readonly string $authorizedChatId,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $reminders = $this->reminderRepository->findAllOn($today);

        if ([] === $reminders) {
            $io->success('Sin recordatorios para hoy, no se envía ningún mensaje.');

            return Command::SUCCESS;
        }

        usort($reminders, static fn ($a, $b) => ($a->getTime()?->format('H:i') ?? '99:99') <=> ($b->getTime()?->format('H:i') ?? '99:99'));

        $lines = array_map(
            static fn ($reminder) => '• '.(null !== $reminder->getTime() ? $reminder->getTime()->format('H:i').' — ' : '').$reminder->getText(),
            $reminders,
        );
        $message = "Recordatorios de hoy 🔔\n".implode("\n", $lines);

        $this->telegramClient->sendMessage((int) $this->authorizedChatId, $message);

        $io->success(sprintf('Enviado aviso con %d recordatorio(s).', \count($reminders)));

        return Command::SUCCESS;
    }
}
