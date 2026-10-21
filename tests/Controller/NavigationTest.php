<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\UiTheme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class NavigationTest extends WebTestCase
{
    private const USERNAME = 'test_navigation_user';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testSidebarListsAllSevenDestinations(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/');

        self::assertSame(
            ['Diario', 'Historial', 'Resúmenes', 'Búsqueda', 'Recordatorios', 'Estadísticas', 'Categorías'],
            $crawler->filter('.sidebar__nav .navlink .navlink__label')->each(static fn ($node) => $node->text()),
        );
    }

    public function testBottomNavHasFiveItems(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/');

        self::assertSame(
            ['Diario', 'Historial', 'Buscar', 'Avisos', 'Más'],
            $crawler->filter('.bottom-nav > .bottom-nav__item, .bottom-nav > .menu > .bottom-nav__item')->each(static fn ($node) => $node->text()),
        );
    }

    public function testMorePanelHoldsSecondaryDestinationsAndLogout(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/');
        $panel = $crawler->filter('.more-sheet');

        self::assertSame(
            ['/resumenes', '/estadisticas', '/topics'],
            $panel->filter('.more-sheet__link')->each(static fn ($node) => $node->attr('href')),
        );
        self::assertCount(1, $panel->filter('a[href="/logout"]'));
    }

    public function testMoreIsActiveOnSecondaryDestination(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/estadisticas');

        self::assertSame('page', $crawler->filter('.more-menu > .bottom-nav__item')->attr('aria-current'));
        self::assertSame('page', $crawler->filter('.more-sheet a[href="/estadisticas"]')->attr('aria-current'));
        self::assertNull($crawler->filter('.bottom-nav > a.bottom-nav__item[href="/"]')->attr('aria-current'));
    }

    public function testUserMenuMarksTheActiveTheme(): void
    {
        $this->login(UiTheme::CUADERNO);

        $crawler = $this->client->request('GET', '/');
        $options = $crawler->filter('.user-menu .theme-option');

        self::assertSame(['Moderno', 'Cuaderno'], $options->each(static fn ($node) => trim($node->text())));
        self::assertSame('false', $options->eq(0)->attr('aria-checked'));
        self::assertSame('true', $options->eq(1)->attr('aria-checked'));
    }

    public function testMobileThemeToggleOffersTheOtherTheme(): void
    {
        $this->login();

        $crawler = $this->client->request('GET', '/');
        $toggle = $crawler->filter('.topbar .theme-toggle:not([hidden])');

        self::assertCount(1, $toggle);
        self::assertSame('cuaderno', $toggle->attr('value'));
        self::assertSame('Cambiar al tema Cuaderno', $toggle->attr('aria-label'));
    }

    private function login(UiTheme $theme = UiTheme::MODERNO): void
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername(self::USERNAME);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));
        $user->setUiTheme($theme);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);
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
