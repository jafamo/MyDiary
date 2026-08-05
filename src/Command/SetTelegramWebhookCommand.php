<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Telegram\TelegramClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:telegram:set-webhook',
    description: 'Registra la URL del webhook en la API de Telegram',
)]
class SetTelegramWebhookCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $botToken,
        private readonly string $webhookToken,
        private readonly string $publicUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $webhookUrl = sprintf('%s/telegram/webhook/%s', rtrim($this->publicUrl, '/'), $this->webhookToken);

        $response = $this->httpClient->request(
            'POST',
            sprintf('https://api.telegram.org/bot%s/setWebhook', $this->botToken),
            ['json' => ['url' => $webhookUrl]],
        );

        $data = $response->toArray(false);

        if (true !== ($data['ok'] ?? false)) {
            $io->error(sprintf('Telegram rechazó el webhook: %s', $data['description'] ?? 'motivo desconocido'));

            return Command::FAILURE;
        }

        $io->success(sprintf('Webhook registrado en %s', $webhookUrl));

        return Command::SUCCESS;
    }
}
