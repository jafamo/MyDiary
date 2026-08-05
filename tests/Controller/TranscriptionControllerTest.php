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
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TranscriptionControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private AudioRecordingRepository $audioRecordingRepository;

    /** @var string[] */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }

        foreach ($this->tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        parent::tearDown();
    }

    public function testEditUpdatesContentMarksEditedManuallyAndRegeneratesFile(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $exportFile = $this->tempFile('transcripcion original');
        $audioRecording = $this->createAudioRecording('trans-ctrl-edit-1', AudioRecordingStatus::TRANSCRIBED);
        $this->createTranscription($audioRecording, 'transcripcion original', $exportFile);
        $audioRecordingId = $audioRecording->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/transcripcion/%d/editar', $audioRecordingId), [
            'transcription_edit' => [
                'content' => 'texto corregido a mano',
                '_token' => $this->csrfToken($client, 'transcription_edit'),
            ],
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $updated = $this->audioRecordingRepository->find($audioRecordingId);
        self::assertSame('texto corregido a mano', $updated->getTranscription()->getContent());
        self::assertTrue($updated->getTranscription()->isEditedManually());
        self::assertSame('texto corregido a mano', file_get_contents($exportFile));
    }

    public function testEditOnAudioWithoutTranscriptionIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioRecording = $this->createAudioRecording('trans-ctrl-edit-2', AudioRecordingStatus::ERROR);
        $audioRecordingId = $audioRecording->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/transcripcion/%d/editar', $audioRecordingId), [
            'transcription_edit' => [
                'content' => 'no debería aplicarse',
                '_token' => $this->csrfToken($client, 'transcription_edit'),
            ],
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    public function testDeleteTranscribedRemovesRecordsAndFiles(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioFile = $this->tempFile('audio binario falso');
        $exportFile = $this->tempFile('transcripcion a borrar');
        $audioRecording = $this->createAudioRecording('trans-ctrl-delete-1', AudioRecordingStatus::TRANSCRIBED, $audioFile);
        $this->createTranscription($audioRecording, 'transcripcion a borrar', $exportFile);
        $audioRecordingId = $audioRecording->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/transcripcion/%d/eliminar', $audioRecordingId), [
            '_token' => $this->csrfToken($client, 'transcription_delete'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertNull($this->audioRecordingRepository->find($audioRecordingId));
        self::assertFileDoesNotExist($audioFile);
        self::assertFileDoesNotExist($exportFile);
    }

    public function testDeleteErrorWithoutTranscriptionRemovesAudioRecord(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $audioFile = $this->tempFile('audio binario falso');
        $audioRecording = $this->createAudioRecording('trans-ctrl-delete-2', AudioRecordingStatus::ERROR, $audioFile);
        $audioRecordingId = $audioRecording->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/transcripcion/%d/eliminar', $audioRecordingId), [
            '_token' => $this->csrfToken($client, 'transcription_delete'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertNull($this->audioRecordingRepository->find($audioRecordingId));
        self::assertFileDoesNotExist($audioFile);
    }

    public function testDeleteWithMissingAudioFileStillDeletesRecord(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $missingAudioFile = sys_get_temp_dir().'/audio-ya-no-existe-'.uniqid().'.ogg';
        $audioRecording = $this->createAudioRecording('trans-ctrl-delete-3', AudioRecordingStatus::ERROR, $missingAudioFile);
        $audioRecordingId = $audioRecording->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/transcripcion/%d/eliminar', $audioRecordingId), [
            '_token' => $this->csrfToken($client, 'transcription_delete'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertNull($this->audioRecordingRepository->find($audioRecordingId));
    }

    private function tempFile(string $content): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('transcription-ctrl-', true);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    /**
     * Genera y guarda en la sesión activa del cliente un token CSRF válido,
     * sin depender de que exista todavía una plantilla que lo renderice.
     */
    private function csrfToken(AbstractBrowser $client, string $tokenId): string
    {
        $client->request('GET', '/');
        $session = $client->getRequest()->getSession();

        $token = bin2hex(random_bytes(20));
        $session->set('_csrf/'.$tokenId, $token);
        $session->save();

        return $token;
    }

    private function createAudioRecording(string $telegramMessageId, AudioRecordingStatus $status, ?string $filePath = null): AudioRecording
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath($filePath ?? '/data/audio/'.$telegramMessageId.'.ogg')
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

    private function createTranscription(AudioRecording $audioRecording, string $content, string $filePath): Transcription
    {
        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($content)
            ->setFilePath($filePath)
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;

        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        return $transcription;
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
        $user->setUsername('test_transcription_ctrl_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        foreach (['trans-ctrl-edit-1', 'trans-ctrl-edit-2', 'trans-ctrl-delete-1', 'trans-ctrl-delete-2', 'trans-ctrl-delete-3'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_transcription_ctrl_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
