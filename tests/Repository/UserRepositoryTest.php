<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\UiTheme;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private const USERNAME = 'user-repo-theme';

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testRowInsertedWithoutUiThemeDefaultsToModerno(): void
    {
        // Simula un usuario creado antes de la migración: la columna se rellena con su DEFAULT.
        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO app_user (username, password_hash, roles) VALUES (?, ?, ?)',
            [self::USERNAME, 'hash', '[]'],
        );

        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => self::USERNAME]);

        self::assertNotNull($user);
        self::assertSame(UiTheme::MODERNO, $user->getUiTheme());
    }

    private function cleanUp(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM app_user WHERE username = ?',
            [self::USERNAME],
        );
        $this->entityManager->clear();
    }
}
