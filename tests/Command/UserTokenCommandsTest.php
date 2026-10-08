<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Service\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UserTokenCommandsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ApiTokenManager $manager;
    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->manager = self::getContainer()->get(ApiTokenManager::class);

        $this->user = (new User())->setUsername('token_cmd_'.bin2hex(random_bytes(4)))->setRoles(['ROLE_USER'])->setPassword('hash');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        $this->entityManager->remove($this->user);
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testListShowsTokensWithoutSecrets(): void
    {
        ['token' => $token, 'apiToken' => $apiToken] = $this->manager->issue($this->user, 'iPhone', new \DateTimeImmutable('-2 days'));
        $this->manager->issue($this->user, 'iPad viejo', new \DateTimeImmutable('-200 days'));

        $tester = $this->tester('app:user:token:list');
        $status = $tester->execute(['username' => $this->user->getUsername()]);
        $output = $tester->getDisplay();

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString((string) $apiToken->getId(), $output);
        self::assertStringContainsString('iPhone', $output);
        self::assertStringContainsString('nunca', $output);
        self::assertStringContainsString('iPad viejo', $output);
        self::assertStringContainsString('(caducado)', $output);
        self::assertStringNotContainsString($token, $output);
        self::assertStringNotContainsString($apiToken->getTokenHash(), $output);
    }

    public function testListWithoutTokens(): void
    {
        $tester = $this->tester('app:user:token:list');

        self::assertSame(Command::SUCCESS, $tester->execute(['username' => $this->user->getUsername()]));
        self::assertStringContainsString('no tiene tokens', $tester->getDisplay());
    }

    public function testListUnknownUserFails(): void
    {
        $tester = $this->tester('app:user:token:list');

        self::assertSame(Command::FAILURE, $tester->execute(['username' => 'no_existe_'.bin2hex(random_bytes(4))]));
    }

    public function testRevokeDeletesTheToken(): void
    {
        $now = new \DateTimeImmutable();
        ['token' => $token, 'apiToken' => $apiToken] = $this->manager->issue($this->user, 'iPhone', $now);
        $other = $this->manager->issue($this->user, 'iPad', $now)['token'];

        $tester = $this->tester('app:user:token:revoke');
        $status = $tester->execute(['id' => (string) $apiToken->getId()]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('revocado', $tester->getDisplay());
        self::assertNull($this->manager->findValid($token, $now));
        self::assertNotNull($this->manager->findValid($other, $now));
    }

    public function testRevokeUnknownIdFails(): void
    {
        $token = $this->manager->issue($this->user, 'iPhone', new \DateTimeImmutable())['token'];

        foreach (['999999999', 'abc', '-1'] as $id) {
            $tester = $this->tester('app:user:token:revoke');

            self::assertSame(Command::FAILURE, $tester->execute(['id' => $id]));
        }
        self::assertNotNull($this->manager->findValid($token, new \DateTimeImmutable()));
    }

    private function tester(string $command): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find($command));
    }
}
