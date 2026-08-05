<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager, $this->userRepository)) {
            $this->deleteTestUser();
        }

        parent::tearDown();
    }

    public function testAccessingProtectedRouteRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginFormRendersCorrectly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_username"]');
        self::assertSelectorExists('input[name="_password"]');
    }

    public function testSuccessfulLoginRedirectsToDiario(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $this->createTestUser();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Entrar')->form([
            '_username' => 'test_security_user',
            '_password' => 'a-strong-password',
        ]);
        $client->submit($form);

        self::assertResponseRedirects('/');
    }

    public function testFailedLoginShowsError(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $this->createTestUser();

        $crawler = $client->request('GET', '/login');

        $form = $crawler->selectButton('Entrar')->form([
            '_username' => 'test_security_user',
            '_password' => 'wrong-password',
        ]);
        $client->submit($form);
        $client->followRedirect();

        self::assertSelectorTextContains('.login-error', 'incorrectos');
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->deleteTestUser();
    }

    private function createTestUser(): void
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_security_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function deleteTestUser(): void
    {
        $user = $this->userRepository->findOneByUsername('test_security_user');

        if (null !== $user) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }
    }
}
