<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\UiTheme;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ThemePreferenceControllerTest extends WebTestCase
{
    private const USERNAME = 'test_theme_pref_user';

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

    public function testChangesThemeAndRedirectsToInternalReferer(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'cuaderno',
            '_token' => $this->csrfToken(),
        ], server: ['HTTP_REFERER' => 'http://localhost/estadisticas?desde=2026-10-01']);

        self::assertResponseRedirects('/estadisticas?desde=2026-10-01');
        self::assertSame(UiTheme::CUADERNO, $this->reloadUser()->getUiTheme());
    }

    public function testExternalRefererRedirectsToDiario(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'cuaderno',
            '_token' => $this->csrfToken(),
        ], server: ['HTTP_REFERER' => 'https://evil.example/phish']);

        self::assertResponseRedirects('/');
    }

    public function testJsonRequestGetsNoContent(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'cuaderno',
            '_token' => $this->csrfToken(),
        ], server: ['HTTP_ACCEPT' => 'application/json']);

        self::assertResponseStatusCodeSame(204);
        self::assertSame(UiTheme::CUADERNO, $this->reloadUser()->getUiTheme());
    }

    public function testUnknownThemeIsRejected(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'oscuro',
            '_token' => $this->csrfToken(),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(UiTheme::MODERNO, $this->reloadUser()->getUiTheme());
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'cuaderno',
            '_token' => 'invalid',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(UiTheme::MODERNO, $this->reloadUser()->getUiTheme());
    }

    public function testWithoutSessionRedirectsToLogin(): void
    {
        $this->client->request('POST', '/preferencias/tema', ['theme' => 'cuaderno']);

        self::assertResponseRedirects('/login');
    }

    public function testThemeChangeIsLogged(): void
    {
        $this->loginNewUser();

        $this->client->request('POST', '/preferencias/tema', [
            'theme' => 'cuaderno',
            '_token' => $this->csrfToken(),
        ]);

        $records = array_values(array_filter(
            $this->testHandler()->getRecords(),
            static fn (LogRecord $record) => 'ui.theme_changed' === ($record->context['event'] ?? null),
        ));
        self::assertCount(1, $records);
        self::assertSame('app', $records[0]->channel);
        self::assertSame('cuaderno', $records[0]->context['ui_theme']);
    }

    public function testPagesRenderTheUserTheme(): void
    {
        $user = $this->loginNewUser();
        $user->setUiTheme(UiTheme::CUADERNO);
        $this->entityManager->flush();

        foreach (['/', '/estadisticas'] as $path) {
            $crawler = $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSame('cuaderno', $crawler->filter('html')->attr('data-theme'), $path);
        }
    }

    public function testLoginPageUsesDefaultTheme(): void
    {
        $crawler = $this->client->request('GET', '/login');

        self::assertSame('moderno', $crawler->filter('html')->attr('data-theme'));
    }

    private function loginNewUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername(self::USERNAME);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->loginUser($user);

        return $user;
    }

    private function reloadUser(): User
    {
        $this->entityManager->clear();
        $user = self::getContainer()->get(UserRepository::class)->findOneBy(['username' => self::USERNAME]);
        self::assertNotNull($user);

        return $user;
    }

    private function csrfToken(): string
    {
        $this->client->request('GET', '/');
        $session = $this->client->getRequest()->getSession();

        $token = bin2hex(random_bytes(20));
        $session->set('_csrf/ui_theme', $token);
        $session->save();

        return $token;
    }

    private function testHandler(): TestHandler
    {
        foreach (self::getContainer()->get('monolog.logger')->getHandlers() as $handler) {
            if ($handler instanceof TestHandler) {
                return $handler;
            }
        }

        self::fail('No hay TestHandler de Monolog en el entorno de test.');
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
