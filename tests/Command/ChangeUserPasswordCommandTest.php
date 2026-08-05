<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangeUserPasswordCommandTest extends KernelTestCase
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

    public function testChangePasswordWithoutKnowingCurrentOne(): void
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_change_password_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'original-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $originalHash = $user->getPassword();

        $application = new Application(self::$kernel);
        $command = $application->find('app:user:change-password');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['brand-new-password']);
        $commandTester->execute(['username' => 'test_change_password_user']);

        $commandTester->assertCommandIsSuccessful();

        $this->entityManager->refresh($user);

        self::assertNotSame($originalHash, $user->getPassword());
    }

    public function testFailsForUnknownUsername(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:change-password');
        $commandTester = new CommandTester($command);

        $commandTester->setInputs(['whatever']);
        $commandTester->execute(['username' => 'does_not_exist']);

        self::assertSame(1, $commandTester->getStatusCode());
    }

    private function deleteTestUsers(): void
    {
        $user = $this->userRepository->findOneByUsername('test_change_password_user');

        if (null !== $user) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }
}
