<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\DailySummary;
use App\Entity\Transcription;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DailySummaryControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private AudioRecordingRepository $audioRecordingRepository;
    private DailySummaryRepository $dailySummaryRepository;
    private \DateTimeImmutable $today;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }
        parent::tearDown();
    }

    public function testGeneratesSummaryWhenNoneExistsYet(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $this->mockOllamaResponse('Un resumen generado a demanda', ['Trabajo']);
        $user = $this->createTestUser();

        $this->createTranscribedAudioRecording('daily-ctrl-msg-1', 'daily-ctrl-file-1', 'contenido transcrito');

        $client->loginUser($user);
        $client->request('POST', '/diario/resumen', [
            '_token' => $this->csrfToken($client, 'daily_summary_generate'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->today);
        self::assertNotNull($dailySummary);
        self::assertSame('Un resumen generado a demanda', $dailySummary->getSummaryText());
    }

    public function testRegeneratesExistingSummaryWithoutDuplicating(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $this->mockOllamaResponse('Resumen regenerado', []);
        $user = $this->createTestUser();

        $this->createTranscribedAudioRecording('daily-ctrl-msg-2', 'daily-ctrl-file-2', 'contenido transcrito');

        $existing = new DailySummary();
        $existing->setDate($this->today)->setSummaryText('Versión antigua')->setGeneratedAt(new \DateTimeImmutable());
        $this->entityManager->persist($existing);
        $this->entityManager->flush();

        $client->loginUser($user);
        $client->request('POST', '/diario/resumen', [
            '_token' => $this->csrfToken($client, 'daily_summary_generate'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $summaries = $this->entityManager->getRepository(DailySummary::class)->findBy(['date' => $this->today]);
        self::assertCount(1, $summaries);
        self::assertSame('Resumen regenerado', $summaries[0]->getSummaryText());
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('POST', '/diario/resumen', ['_token' => 'invalido']);

        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->dailySummaryRepository->findOneByDate($this->today));
    }

    private function mockOllamaResponse(string $summary, array $topics): void
    {
        $content = json_encode(['summary' => $summary, 'topics' => $topics]);
        $payload = json_encode(['choices' => [['message' => ['content' => $content]]]]);
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse($payload)));
    }

    private function csrfToken(AbstractBrowser $client, string $tokenId): string
    {
        $client->request('GET', '/');
        $session = $client->getRequest()->getSession();

        $token = bin2hex(random_bytes(20));
        $session->set('_csrf/'.$tokenId, $token);
        $session->save();

        return $token;
    }

    private function createTranscribedAudioRecording(string $telegramMessageId, string $telegramFileUniqueId, string $content): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramFileUniqueId)
            ->setFilePath('/data/audio/'.$telegramFileUniqueId.'.ogg')
            ->setReceivedAt($this->today)
            ->setDurationSeconds(5)
            ->setStatus(AudioRecordingStatus::TRANSCRIBED)
        ;
        $this->entityManager->persist($audioRecording);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($content)
            ->setFilePath('/data/transcriptions/'.$telegramFileUniqueId.'.txt')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
        $this->dailySummaryRepository = self::getContainer()->get(DailySummaryRepository::class);
        $this->today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_daily_summary_ctrl_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        $dailySummary = $this->dailySummaryRepository->findOneByDate($this->today);
        if (null !== $dailySummary) {
            $this->entityManager->remove($dailySummary);
        }

        foreach (['daily-ctrl-msg-1', 'daily-ctrl-msg-2'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_daily_summary_ctrl_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
