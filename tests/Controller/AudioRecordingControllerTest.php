<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AudioRecordingControllerTest extends WebTestCase
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

    public function testRetryOnErrorResetsToPending(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioRecordingId = $this->createAudioRecording('audio-ctrl-retry-1', AudioRecordingStatus::ERROR)->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/audio/%d/reintentar', $audioRecordingId), [
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $audioRecording = $this->audioRecordingRepository->find($audioRecordingId);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
        self::assertNull($audioRecording->getErrorCode());
        self::assertNull($audioRecording->getErrorMessage());
    }

    public function testRetryOnPendingIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioRecordingId = $this->createAudioRecording('audio-ctrl-retry-2', AudioRecordingStatus::PENDING)->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/audio/%d/reintentar', $audioRecordingId), [
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseStatusCodeSame(409);

        $this->entityManager->clear();
        $audioRecording = $this->audioRecordingRepository->find($audioRecordingId);
        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
    }

    public function testRetryOnTranscribedIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioRecordingId = $this->createAudioRecording('audio-ctrl-retry-3', AudioRecordingStatus::TRANSCRIBED)->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/audio/%d/reintentar', $audioRecordingId), [
            '_token' => $this->csrfToken($client),
        ]);

        self::assertResponseStatusCodeSame(409);

        $this->entityManager->clear();
        $audioRecording = $this->audioRecordingRepository->find($audioRecordingId);
        self::assertSame(AudioRecordingStatus::TRANSCRIBED, $audioRecording->getStatus());
    }

    /**
     * Genera y guarda en la sesión activa del cliente un token CSRF válido para
     * 'audio_retry', sin depender de que exista todavía una plantilla que lo renderice.
     */
    private function csrfToken(AbstractBrowser $client): string
    {
        $client->request('GET', '/');
        $session = $client->getRequest()->getSession();

        $token = bin2hex(random_bytes(20));
        $session->set('_csrf/audio_retry', $token);
        $session->save();

        return $token;
    }

    private function createAudioRecording(string $telegramMessageId, AudioRecordingStatus $status): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath('/data/audio/'.$telegramMessageId.'.ogg')
            ->setReceivedAt(DateRange::nowInMadrid())
            ->setDurationSeconds(10)
            ->setStatus($status)
        ;

        if (AudioRecordingStatus::ERROR === $status) {
            $audioRecording->setErrorCode('TIMEOUT')->setErrorMessage('algo falló');
        }

        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();

        return $audioRecording;
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
        $user->setUsername('test_audio_recording_ctrl_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        foreach (['audio-ctrl-retry-1', 'audio-ctrl-retry-2', 'audio-ctrl-retry-3'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_audio_recording_ctrl_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
