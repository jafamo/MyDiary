<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\DailySummary;
use App\Entity\DailySummaryGeneration;
use App\Entity\DailySummaryGenerationStatus;
use App\Entity\Transcription;
use App\Entity\User;
use App\Message\GenerateDailySummaryMessage;
use App\MessageHandler\GenerateDailySummaryMessageHandler;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryGenerationRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DailySummaryControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private AudioRecordingRepository $audioRecordingRepository;
    private DailySummaryRepository $dailySummaryRepository;
    private DailySummaryGenerationRepository $generationRepository;
    private \DateTimeImmutable $today;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }
        parent::tearDown();
    }

    public function testButtonQueuesTheGenerationWithoutWaitingForIt(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();
        $this->createTranscribedAudioRecording('daily-ctrl-msg-1', 'daily-ctrl-file-1', 'contenido transcrito');

        $client->loginUser($user);
        $client->request('POST', '/diario/resumen', [
            '_token' => $this->csrfToken($client, 'daily_summary_generate'),
        ]);

        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertNull($this->dailySummaryRepository->findOneByDate($this->today));
        self::assertSame(DailySummaryGenerationStatus::QUEUED, $this->generationRepository->findOneByDate($this->today)?->getStatus());
        self::assertSame([$this->today->format('Y-m-d')], $this->dispatchedDates());

        $client->request('GET', '/');
        self::assertSelectorTextContains('.summary-generate', 'Generando el resumen');
        self::assertSelectorExists('[data-summary-generation-status="QUEUED"]');
        self::assertSelectorExists('.summary-generate button[disabled]');
    }

    public function testWorkerGeneratesTheQueuedSummary(): void
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
        self::getContainer()->get(GenerateDailySummaryMessageHandler::class)(new GenerateDailySummaryMessage($this->today->format('Y-m-d')));

        $this->entityManager->clear();
        self::assertSame('Un resumen generado a demanda', $this->dailySummaryRepository->findOneByDate($this->today)?->getSummaryText());
        self::assertSame(DailySummaryGenerationStatus::DONE, $this->generationRepository->findOneByDate($this->today)?->getStatus());

        $client->request('GET', '/diario/resumen/estado');
        self::assertSame(['status' => 'DONE'], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testRegenerationByTheWorkerDoesNotDuplicate(): void
    {
        self::createClient();
        $this->bootServices();
        $this->mockOllamaResponse('Resumen regenerado', []);
        $this->createTranscribedAudioRecording('daily-ctrl-msg-2', 'daily-ctrl-file-2', 'contenido transcrito');

        $existing = new DailySummary();
        $existing->setDate($this->today)->setSummaryText('Versión antigua')->setGeneratedAt(new \DateTimeImmutable());
        $this->entityManager->persist($existing);
        $this->entityManager->flush();
        // Que el worker cargue los audios con su transcripción desde la BD.
        $this->entityManager->clear();

        self::getContainer()->get(GenerateDailySummaryMessageHandler::class)(new GenerateDailySummaryMessage($this->today->format('Y-m-d')));

        $this->entityManager->clear();
        $summaries = $this->entityManager->getRepository(DailySummary::class)->findBy(['date' => $this->today]);
        self::assertCount(1, $summaries);
        self::assertSame('Resumen regenerado', $summaries[0]->getSummaryText());
    }

    public function testWithoutTranscriptionsShowsANoticeAndQueuesNothing(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('POST', '/diario/resumen', [
            '_token' => $this->csrfToken($client, 'daily_summary_generate'),
        ]);

        self::assertResponseRedirects();
        self::assertNull($this->generationRepository->findOneByDate($this->today));
        self::assertSame([], $this->dispatchedDates());

        $client->followRedirect();
        self::assertSelectorTextContains('.summary-generate', 'Hoy todavía no hay transcripciones que resumir.');
    }

    public function testDiarioShowsAFailedGenerationWithRetry(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();
        $generation = new DailySummaryGeneration($this->today);
        $generation->markRunning(new \DateTimeImmutable());
        $generation->markFailed(new \DateTimeImmutable(), 'TIMEOUT', 'fallo simulado');
        $this->entityManager->persist($generation);
        $this->entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/');

        self::assertSelectorTextContains('.summary-generate', 'No se pudo generar el resumen');
        self::assertSelectorTextContains('.summary-generate button', 'Reintentar');
        self::assertSelectorNotExists('[data-summary-generation-status]');
    }

    public function testStalledGenerationIsShownAsFailed(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();
        $generation = new DailySummaryGeneration($this->today);
        $generation->markQueued(new \DateTimeImmutable(sprintf('-%d minutes', DailySummaryGeneration::STALL_AFTER_MINUTES + 5)));
        $this->entityManager->persist($generation);
        $this->entityManager->flush();

        $client->loginUser($user);
        $client->request('GET', '/diario/resumen/estado');
        self::assertSame(['status' => 'FAILED'], json_decode((string) $client->getResponse()->getContent(), true));

        $client->request('GET', '/');
        self::assertSelectorTextContains('.summary-generate button', 'Reintentar');
    }

    public function testStatusRequiresASession(): void
    {
        $client = static::createClient();

        $client->request('GET', '/diario/resumen/estado');

        self::assertResponseRedirects('/login');
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
        $this->generationRepository = self::getContainer()->get(DailySummaryGenerationRepository::class);
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

    /**
     * @return list<string>
     */
    private function dispatchedDates(): array
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return array_values(array_map(
            static function ($envelope): string {
                $message = $envelope->getMessage();
                self::assertInstanceOf(GenerateDailySummaryMessage::class, $message);

                return $message->date;
            },
            $transport->getSent(),
        ));
    }

    private function cleanUp(): void
    {
        $generation = $this->generationRepository->findOneByDate($this->today);
        if (null !== $generation) {
            $this->entityManager->remove($generation);
        }

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
