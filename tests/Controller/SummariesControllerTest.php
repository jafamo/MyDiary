<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\DailySummary;
use App\Entity\Topic;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SummariesControllerTest extends WebTestCase
{
    private const DATES = ['2021-02-01', '2021-02-02', '2021-02-03', '2021-02-04', '2021-02-05', '2021-02-06', '2021-02-07'];

    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private DailySummaryRepository $dailySummaryRepository;
    private TopicRepository $topicRepository;
    private AudioRecordingRepository $audioRecordingRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }
        parent::tearDown();
    }

    public function testDefaultViewShowsLatestFiveSummaries(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes');

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('.summary-card'));
        self::assertSelectorTextContains('.summary-list .summary-card:first-child', '7');
    }

    public function testSummaryShowsTopicChips(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createDailySummary('2021-02-01', ['Trabajo']);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.topic-chip', 'Trabajo');
    }

    public function testSummaryLinksToHistorialForItsDate(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createDailySummary('2021-02-01');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes');

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('.summary-card a.btn-action')->attr('href');
        self::assertSame('/historial?date=2021-02-01', $link);
    }

    public function testValidRangeFiltersAndPaginates(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes?from=2021-02-01&to=2021-02-07');

        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('.summary-card'));
    }

    public function testInvalidRangeFallsBackToLatest(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes?from=not-a-date&to=2021-02-07');

        self::assertResponseIsSuccessful();
        self::assertCount(5, $crawler->filter('.summary-card'));
    }

    public function testPageBeyondTotalFallsBackToLastPage(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes?from=2021-02-01&to=2021-02-07&page=99');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('.summary-card')->count());
    }

    public function testSummaryShowsTotalAudioCountForItsDay(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createDailySummary('2021-02-01');
        $this->createAudioRecording('resumenes-ctrl-audio-1', '2021-02-01');
        $this->createAudioRecording('resumenes-ctrl-audio-2', '2021-02-01');
        $this->createAudioRecording('resumenes-ctrl-audio-3', '2021-02-02');

        $client->loginUser($user);
        $crawler = $client->request('GET', '/resumenes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.summary-card__audio-count', '2 audios');
    }

    public function testSummaryWithoutTopicsRendersWithoutError(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createDailySummary('2021-02-01');

        $client->loginUser($user);
        $client->request('GET', '/resumenes');

        self::assertResponseIsSuccessful();
    }

    private function createDailySummary(string $date, array $topicNames = []): void
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText('Resumen de '.$date)
            ->setGeneratedAt(new \DateTimeImmutable())
        ;

        foreach ($topicNames as $topicName) {
            $topic = $this->topicRepository->findOneByName($topicName);
            if (null === $topic) {
                $topic = new Topic();
                $topic->setName($topicName);
                $this->entityManager->persist($topic);
            }
            $dailySummary->addTopic($topic);
        }

        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();
    }

    private function createAudioRecording(string $telegramMessageId, string $date): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($telegramMessageId)
            ->setTelegramFileUniqueId($telegramMessageId.'-file')
            ->setFilePath('/data/audio/'.$telegramMessageId.'.ogg')
            ->setReceivedAt((new \DateTimeImmutable($date))->setTime(11, 0, 0))
            ->setDurationSeconds(10)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->dailySummaryRepository = self::getContainer()->get(DailySummaryRepository::class);
        $this->topicRepository = self::getContainer()->get(TopicRepository::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_resumenes_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        foreach (self::DATES as $date) {
            $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }

        $topic = $this->topicRepository->findOneByName('Trabajo');
        if (null !== $topic) {
            $this->entityManager->remove($topic);
        }

        foreach (['resumenes-ctrl-audio-1', 'resumenes-ctrl-audio-2', 'resumenes-ctrl-audio-3'] as $telegramMessageId) {
            $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);
            if (null !== $audioRecording) {
                $this->entityManager->remove($audioRecording);
            }
        }

        $user = $this->userRepository->findOneByUsername('test_resumenes_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
