<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Service\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base de los tests funcionales de la API: crea usuarios de prueba (se borran al terminar,
 * y con ellos sus tokens) y emite tokens sin pasar por el login.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected const PASSWORD = 'a-strong-password';

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    /** @var list<string> */
    private array $usernames = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Mismo contenedor en todas las peticiones del test, para que el limitador de intentos
        // (en memoria en el entorno de test) cuente los logins de una petición a la siguiente.
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->usernames = [];
    }

    protected function tearDown(): void
    {
        try {
            $userRepository = self::getContainer()->get(UserRepository::class);
            $this->entityManager->clear();
            foreach ($this->usernames as $username) {
                $user = $userRepository->findOneByUsername($username);
                if (null !== $user) {
                    $this->entityManager->remove($user);
                }
            }
            $this->entityManager->flush();
        } finally {
            parent::tearDown();
        }
    }

    protected function createUser(string $prefix = 'api_test'): User
    {
        $user = new User();
        $user->setUsername($prefix.'_'.bin2hex(random_bytes(4)));
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->usernames[] = $user->getUsername();

        return $user;
    }

    protected function issueToken(User $user, string $name = 'iPhone', ?\DateTimeImmutable $createdAt = null): string
    {
        return self::getContainer()->get(ApiTokenManager::class)->issue($user, $name, $createdAt ?? new \DateTimeImmutable())['token'];
    }

    protected function tokenRepository(): ApiTokenRepository
    {
        return self::getContainer()->get(ApiTokenRepository::class);
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function api(string $method, string $uri, ?string $token = null, ?array $body = null, ?string $rawBody = null): void
    {
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        if (null !== $body || null !== $rawBody) {
            $server['CONTENT_TYPE'] = 'application/json';
        }

        $this->client->request($method, $uri, [], [], $server, $rawBody ?? (null !== $body ? json_encode($body, \JSON_THROW_ON_ERROR) : null));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        self::assertResponseHeaderSame('Content-Type', 'application/json');

        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    protected function assertApiError(int $status, string $code): void
    {
        self::assertResponseStatusCodeSame($status);
        $json = $this->json();
        self::assertSame(['code', 'message'], array_keys($json));
        self::assertSame($code, $json['code']);
        self::assertNotSame('', $json['message']);
    }
}
