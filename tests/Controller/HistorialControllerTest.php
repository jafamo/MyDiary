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

class HistorialControllerTest extends WebTestCase
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

    public function testCalendarMarksDayWithEntries(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('historial-ctrl-msg-1')
            ->setTelegramFileUniqueId('historial-ctrl-file-1')
            ->setFilePath('/data/audio/historial-ctrl.ogg')
            ->setReceivedAt($today->setTime(11, 0, 0))
            ->setDurationSeconds(20)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/historial');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.calendar__day--has-entries');
    }

    public function testSelectingDayShowsEntryLog(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('historial-ctrl-msg-2')
            ->setTelegramFileUniqueId('historial-ctrl-file-2')
            ->setFilePath('/data/audio/historial-ctrl-2.ogg')
            ->setReceivedAt($today->setTime(11, 0, 0))
            ->setDurationSeconds(20)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/historial?date='.$today->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.day-preview');
        self::assertSelectorExists('.day-preview .entry-log');
    }

    public function testAdjacentMonthDaysAreMuted(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/historial?year=2026&month=8');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.calendar__day--muted');
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
        $user->setUsername('test_historial_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        foreach (['historial-ctrl-msg-1', 'historial-ctrl-msg-2'] as $messageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($messageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_historial_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
