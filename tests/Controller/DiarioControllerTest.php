<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\Transcription;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DiarioControllerTest extends WebTestCase
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

    public function testDiarioRendersTodaysEntries(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('diario-ctrl-msg-1')
            ->setTelegramFileUniqueId('diario-ctrl-file-1')
            ->setFilePath('/data/audio/diario-ctrl.ogg')
            ->setReceivedAt(DateRange::nowInMadrid()->setTime(9, 0, 0))
            ->setDurationSeconds(42)
            ->setStatus(AudioRecordingStatus::TRANSCRIBED)
        ;
        $this->entityManager->persist($audioRecording);

        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent('Contenido de prueba del diario')
            ->setFilePath('/data/transcriptions/diario-ctrl.txt')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.entry__text', 'Contenido de prueba del diario');
        self::assertSelectorExists('.mini-stats');
    }

    public function testDiarioWithoutEntriesShowsEmptyState(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.entry-empty');
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
        $user->setUsername('test_diario_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('diario-ctrl-msg-1');
        if (null !== $audioRecording) {
            $this->entityManager->remove($audioRecording);
        }

        $user = $this->userRepository->findOneByUsername('test_diario_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
