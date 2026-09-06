<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\DailySummary;
use App\Entity\Transcription;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SearchControllerTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private AudioRecordingRepository $audioRecordingRepository;
    private DailySummaryRepository $dailySummaryRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }

        parent::tearDown();
    }

    public function testSearchWithoutQueryShowsEmptyState(): void
    {
        $client = static::createClient();
        $this->bootServices($client);
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/busqueda');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Escribe una consulta');
    }

    public function testSearchWithNoStoredEmbeddingsShowsNoResults(): void
    {
        $client = static::createClient();
        $this->bootServices($client);
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/busqueda', ['q' => 'dinero']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Sin resultados');
    }

    public function testSearchMergesTranscriptionsAndDailySummariesByDistance(): void
    {
        $client = static::createClient();
        $this->bootServices($client);
        $user = $this->createTestUser();

        $this->createTranscription('search-ctrl-1', 'presupuesto del mes', $this->unitVector(0));
        $this->createDailySummary('2020-02-02', 'resumen sobre dinero', $this->unitVector(0, 0.9));
        $this->createTranscription('search-ctrl-2', 'paseo por el parque', $this->unitVector(1));

        $client->loginUser($user);
        $client->request('GET', '/busqueda', ['q' => 'dinero']);

        self::assertResponseIsSuccessful();
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('presupuesto del mes', $content);
        self::assertStringContainsString('resumen sobre dinero', $content);
        self::assertStringContainsString('paseo por el parque', $content);

        $positionPresupuesto = strpos($content, 'presupuesto del mes');
        $positionResumen = strpos($content, 'resumen sobre dinero');
        $positionPaseo = strpos($content, 'paseo por el parque');

        self::assertLessThan($positionResumen, $positionPresupuesto, 'La transcripción más similar debe aparecer antes que el resumen menos similar');
        self::assertLessThan($positionPaseo, $positionResumen, 'El resultado menos similar (sin relación semántica) debe aparecer último');
    }

    private function bootServices(KernelBrowser $client): void
    {
        $client->disableReboot();
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient(new MockResponse(json_encode(['embedding' => $this->unitVector(0)]))));

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
        $this->dailySummaryRepository = self::getContainer()->get(DailySummaryRepository::class);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_search_ctrl_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return list<float> vector de 768 dimensiones (una embedding de nomic-embed-text) con un único valor en $activeIndex
     */
    private function unitVector(int $activeIndex, float $activeValue = 1.0): array
    {
        $vector = array_fill(0, 768, 0.0);
        $vector[$activeIndex] = $activeValue;

        return $vector;
    }

    /**
     * @param list<float> $embedding
     */
    private function createTranscription(string $telegramMessageId, string $content, array $embedding): Transcription
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath('/data/audio/'.$telegramMessageId.'.ogg')
            ->setReceivedAt(new \DateTimeImmutable())
            ->setDurationSeconds(5)
        ;
        $this->entityManager->persist($audioRecording);

        $now = new \DateTimeImmutable();
        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent($content)
            ->setFilePath('/data/transcriptions/'.$telegramMessageId.'.txt')
            ->setCreatedAt($now)
            ->setUpdatedAt($now)
            ->setEmbedding($embedding)
        ;
        $this->entityManager->persist($transcription);
        $this->entityManager->flush();

        return $transcription;
    }

    /**
     * @param list<float> $embedding
     */
    private function createDailySummary(string $date, string $summaryText, array $embedding): DailySummary
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText($summaryText)
            ->setGeneratedAt(new \DateTimeImmutable())
            ->setEmbedding($embedding)
        ;
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();

        return $dailySummary;
    }

    private function cleanUp(): void
    {
        foreach (['search-ctrl-1', 'search-ctrl-2'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable('2020-02-02'));
        if (null !== $dailySummary) {
            $this->entityManager->remove($dailySummary);
        }

        $user = $this->userRepository->findOneByUsername('test_search_ctrl_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
