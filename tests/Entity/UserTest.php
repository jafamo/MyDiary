<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\UiTheme;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $user = new User();
        $user
            ->setUsername('jfarinos')
            ->setPassword('hashed-password')
        ;

        self::assertSame('jfarinos', $user->getUsername());
        self::assertSame('jfarinos', $user->getUserIdentifier());
        self::assertSame('hashed-password', $user->getPassword());
    }

    public function testUiThemeDefaultsToModerno(): void
    {
        self::assertSame(UiTheme::MODERNO, (new User())->getUiTheme());
    }

    public function testSetUiTheme(): void
    {
        $user = (new User())->setUiTheme(UiTheme::CUADERNO);

        self::assertSame(UiTheme::CUADERNO, $user->getUiTheme());
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    public function testGetRolesDeduplicates(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        self::assertContains('ROLE_USER', $roles);
        self::assertContains('ROLE_ADMIN', $roles);
        self::assertCount(2, $roles);
    }
}
