<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:token:list',
    description: 'Lista los tokens de la API de un usuario (sin mostrar el token)',
)]
class ListUserTokensCommand extends Command
{
    private const DATE_FORMAT = 'Y-m-d H:i';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly ApiTokenManager $apiTokenManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Nombre de usuario');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = (string) $input->getArgument('username');

        $user = $this->userRepository->findOneByUsername($username);
        if (null === $user) {
            $io->error(sprintf('No existe ningún usuario con username "%s".', $username));

            return Command::FAILURE;
        }

        $tokens = $this->apiTokenRepository->findByUser($user);
        if ([] === $tokens) {
            $io->writeln(sprintf('El usuario "%s" no tiene tokens de la API.', $username));

            return Command::SUCCESS;
        }

        $now = new \DateTimeImmutable();
        $rows = [];
        foreach ($tokens as $token) {
            $expiresAt = $this->apiTokenManager->expiresAt($token);
            $rows[] = [
                $token->getId(),
                $token->getName(),
                $token->getCreatedAt()->format(self::DATE_FORMAT),
                $token->getLastUsedAt()?->format(self::DATE_FORMAT) ?? 'nunca',
                $expiresAt->format(self::DATE_FORMAT).($expiresAt <= $now ? ' (caducado)' : ''),
            ];
        }

        $io->table(['Id', 'Dispositivo', 'Creado (UTC)', 'Último uso (UTC)', 'Caduca (UTC)'], $rows);

        return Command::SUCCESS;
    }
}
