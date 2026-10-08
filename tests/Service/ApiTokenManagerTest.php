<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Service\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ApiTokenManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ApiTokenRepository $repository;
    private ApiTokenManager $manager;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ApiTokenRepository::class);
        $this->manager = $container->get(ApiTokenManager::class);

        $this->user = (new User())->setUsername('token_manager_'.bin2hex(random_bytes(4)))->setRoles(['ROLE_USER'])->setPassword('hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $user = $this->entityManager->find(User::class, $this->user->getId());
        if (null !== $user) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testIssueStoresOnlyTheHash(): void
    {
        $now = new \DateTimeImmutable('2026-10-08 10:00:00');

        ['token' => $token, 'apiToken' => $apiToken] = $this->manager->issue($this->user, 'iPhone', $now);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        self::assertNotNull($apiToken->getId());
        self::assertSame(hash('sha256', $token), $apiToken->getTokenHash());
        self::assertSame('iPhone', $apiToken->getName());
        self::assertSame($this->user, $apiToken->getUser());
        self::assertEquals($now, $apiToken->getCreatedAt());
        self::assertNull($apiToken->getLastUsedAt());
        self::assertSame('2027-01-06 10:00:00', $this->manager->expiresAt($apiToken)->format('Y-m-d H:i:s'));
    }

    public function testEachIssuedTokenIsDifferent(): void
    {
        $now = new \DateTimeImmutable();

        $first = $this->manager->issue($this->user, 'iPhone', $now)['token'];
        $second = $this->manager->issue($this->user, 'iPhone', $now)['token'];

        self::assertNotSame($first, $second);
        self::assertCount(2, $this->repository->findByUser($this->user));
    }

    public function testUnknownTokenIsNotValid(): void
    {
        self::assertNull($this->manager->findValid(str_repeat('0', 64), new \DateTimeImmutable()));
        self::assertNull($this->manager->findValid('', new \DateTimeImmutable()));
    }

    public function testValidTokenRecordsItsUse(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-08 10:00:00');
        $token = $this->manager->issue($this->user, 'iPhone', $issuedAt)['token'];
        $usedAt = $issuedAt->modify('+89 days');

        $found = $this->manager->findValid($token, $usedAt);

        self::assertNotNull($found);
        self::assertEquals($usedAt, $found->getLastUsedAt());
        self::assertEquals($usedAt->modify('+90 days'), $this->manager->expiresAt($found));
    }

    public function testLastUsedIsRewrittenAtMostOncePerHour(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-08 10:00:00');
        $token = $this->manager->issue($this->user, 'iPhone', $issuedAt)['token'];

        $this->manager->findValid($token, $issuedAt->modify('+1 minute'));
        $found = $this->manager->findValid($token, $issuedAt->modify('+30 minutes'));
        self::assertNotNull($found);
        self::assertSame('10:01:00', $found->getLastUsedAt()?->format('H:i:s'));

        $found = $this->manager->findValid($token, $issuedAt->modify('+61 minutes'));
        self::assertNotNull($found);
        self::assertSame('11:01:00', $found->getLastUsedAt()?->format('H:i:s'));
    }

    public function testExpiredTokenIsDeleted(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-08 10:00:00');
        $token = $this->manager->issue($this->user, 'iPhone', $issuedAt)['token'];

        self::assertNull($this->manager->findValid($token, $issuedAt->modify('+90 days')));
        self::assertSame([], $this->repository->findByUser($this->user));
    }

    public function testInactivityIsCountedFromTheLastUse(): void
    {
        $issuedAt = new \DateTimeImmutable('2026-10-08 10:00:00');
        $token = $this->manager->issue($this->user, 'iPhone', $issuedAt)['token'];

        self::assertNotNull($this->manager->findValid($token, $issuedAt->modify('+60 days')));
        self::assertNotNull($this->manager->findValid($token, $issuedAt->modify('+120 days')));
        self::assertNull($this->manager->findValid($token, $issuedAt->modify('+211 days')));
    }

    public function testRevoke(): void
    {
        $now = new \DateTimeImmutable();
        ['token' => $token, 'apiToken' => $apiToken] = $this->manager->issue($this->user, 'iPhone', $now);

        $this->manager->revoke($apiToken);

        self::assertNull($this->manager->findValid($token, $now));
    }

    public function testTokensAreDeletedWithTheirUser(): void
    {
        $this->manager->issue($this->user, 'iPhone', new \DateTimeImmutable());
        $connection = $this->entityManager->getConnection();
        $userId = $this->user->getId();

        $connection->executeStatement('DELETE FROM app_user WHERE id = ?', [$userId]);

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM api_token WHERE user_id = ?', [$userId]));
        $this->entityManager->clear();
    }
}
