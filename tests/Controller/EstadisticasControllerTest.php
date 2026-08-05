<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EstadisticasControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private AudioRecordingRepository $audioRecordingRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }
        parent::tearDown();
    }

    public function testDefaultRangeRendersTilesAndChartData(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createAudio('estadisticas-ctrl-msg-1', 'estadisticas-ctrl-file-1', $today);

        $client->loginUser($user);
        $client->request('GET', '/estadisticas');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#chart-data');
        self::assertSelectorTextContains('.filter-pill[aria-current="true"]', '1 mes');
    }

    public function testPresetRangeChangesActivePill(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/estadisticas?range=90');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.filter-pill[aria-current="true"]', '3 meses');
    }

    public function testCustomRangeAppliesFromAndTo(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-07-01&to=2026-07-10');

        self::assertResponseIsSuccessful();
        $chartData = json_decode($crawler->filter('#chart-data')->text(), true);
        self::assertCount(10, $chartData);
        self::assertSame('2026-07-01', $chartData[0]['date']);
        self::assertSame('2026-07-10', $chartData[9]['date']);
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_estadisticas_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createAudio(string $messageId, string $fileUniqueId, \DateTimeImmutable $date): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($messageId)
            ->setTelegramFileUniqueId($fileUniqueId)
            ->setFilePath('/data/audio/'.$fileUniqueId.'.ogg')
            ->setReceivedAt($date->setTime(11, 0, 0))
            ->setDurationSeconds(30)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        foreach (['estadisticas-ctrl-msg-1'] as $messageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($messageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_estadisticas_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
