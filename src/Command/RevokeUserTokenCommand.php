<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ApiTokenRepository;
use App\Service\ApiTokenManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:token:revoke',
    description: 'Revoca un token de la API por su id (ver app:user:token:list)',
)]
class RevokeUserTokenCommand extends Command
{
    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly ApiTokenManager $apiTokenManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Id del token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (string) $input->getArgument('id');

        $token = ctype_digit($id) ? $this->apiTokenRepository->find((int) $id) : null;
        if (null === $token) {
            $io->error(sprintf('No existe ningún token con id "%s".', $id));

            return Command::FAILURE;
        }

        $name = $token->getName();
        $username = $token->getUser()->getUserIdentifier();
        $this->apiTokenManager->revoke($token);

        $io->success(sprintf('Token %s ("%s", usuario "%s") revocado.', $id, $name, $username));

        return Command::SUCCESS;
    }
}
