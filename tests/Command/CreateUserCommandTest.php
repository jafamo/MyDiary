<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);

        $this->deleteTestUsers();
    }

    protected function tearDown(): void
    {
        $this->deleteTestUsers();

        parent::tearDown();
    }

    public function testCreateUserPersistsHashedPassword(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['s3cr3t-password']);
        $commandTester->execute(['username' => 'test_create_user']);

        $commandTester->assertCommandIsSuccessful();

        $user = $this->userRepository->findOneByUsername('test_create_user');

        self::assertNotNull($user);
        self::assertNotSame('s3cr3t-password', $user->getPassword());
    }

    public function testCreateUserFailsIfUsernameAlreadyExists(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create');

        $first = new CommandTester($command);
        $first->setInputs(['s3cr3t-password']);
        $first->execute(['username' => 'test_create_user']);

        $second = new CommandTester($command);
        $second->setInputs(['other-password']);
        $second->execute(['username' => 'test_create_user']);

        self::assertSame(1, $second->getStatusCode());
    }

    private function deleteTestUsers(): void
    {
        $user = $this->userRepository->findOneByUsername('test_create_user');

        if (null !== $user) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }
}
