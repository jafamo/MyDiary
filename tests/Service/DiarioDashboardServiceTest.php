<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AudioRecording;
use App\Repository\AudioRecordingRepository;
use App\Repository\TopicRepository;
use App\Service\DateRange;
use App\Service\DiarioDashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DiarioDashboardServiceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;
    private DiarioDashboardService $service;
    private array $createdMessageIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = $container->get(AudioRecordingRepository::class);
        $this->service = new DiarioDashboardService($this->audioRecordingRepository, $container->get(TopicRepository::class));
        $this->createdMessageIds = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMessageIds as $messageId) {
            $record = $this->audioRecordingRepository->findOneByTelegramMessageId($messageId);
            if (null !== $record) {
                $this->entityManager->remove($record);
            }
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testStreakIsZeroWithoutRecentAudio(): void
    {
        $this->createAudioOn(DateRange::nowInMadrid()->modify('-5 days'));

        self::assertSame(0, $this->service->getCurrentStreak());
    }

    public function testStreakCountsConsecutiveDaysIncludingToday(): void
    {
        $today = DateRange::nowInMadrid();
        $this->createAudioOn($today);
        $this->createAudioOn($today->modify('-1 day'));
        $this->createAudioOn($today->modify('-2 days'));
        $this->createAudioOn($today->modify('-4 days')); // gap: no cuenta

        self::assertSame(3, $this->service->getCurrentStreak());
    }

    public function testBestStreakFindsLongestRun(): void
    {
        $today = DateRange::nowInMadrid();
        $this->createAudioOn($today);
        $this->createAudioOn($today->modify('-10 days'));
        $this->createAudioOn($today->modify('-11 days'));
        $this->createAudioOn($today->modify('-12 days'));
        $this->createAudioOn($today->modify('-13 days'));

        self::assertSame(4, $this->service->getBestStreak());
    }

    public function testWeekTotalAndTrend(): void
    {
        $today = DateRange::nowInMadrid();
        $this->createAudioOn($today);
        $this->createAudioOn($today);

        $result = $this->service->getWeekTotalAndTrend();

        self::assertGreaterThanOrEqual(2, $result['total']);
        self::assertArrayHasKey('delta', $result);
    }

    public function testDailyCountsCoverFourteenDaysEndingToday(): void
    {
        $today = DateRange::nowInMadrid();
        $before = $this->service->getDailyCounts(14);

        $this->createAudioOn($today);
        $this->createAudioOn($today);
        $this->createAudioOn($today->modify('-3 days'));

        $series = $this->service->getDailyCounts(14);

        self::assertCount(14, $series);
        self::assertSame($today->format('Y-m-d'), $series[13]['date']);
        self::assertSame($today->modify('-13 days')->format('Y-m-d'), $series[0]['date']);
        self::assertSame($before[13]['count'] + 2, $series[13]['count']);
        self::assertSame($before[10]['count'] + 1, $series[10]['count']);
    }

    public function testCurrentWeekRunsMondayToSundayAndMarksToday(): void
    {
        $today = DateRange::nowInMadrid();
        $this->createAudioOn($today);

        $week = $this->service->getCurrentWeek();

        self::assertCount(7, $week);
        self::assertSame('1', $week[0]['date']->format('N'));
        self::assertSame('7', $week[6]['date']->format('N'));
        $todays = array_values(array_filter($week, static fn (array $day) => $day['is_today']));
        self::assertCount(1, $todays);
        self::assertSame($today->format('Y-m-d'), $todays[0]['date']->format('Y-m-d'));
        self::assertTrue($todays[0]['has_audio']);
    }

    private function createAudioOn(\DateTimeImmutable $date): void
    {
        $messageId = 'dashboard-test-'.uniqid();
        $this->createdMessageIds[] = $messageId;

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($messageId)
            ->setTelegramFileUniqueId('dashboard-file-'.uniqid())
            ->setFilePath('/data/audio/dashboard-test.ogg')
            ->setReceivedAt($date->setTime(10, 0, 0))
            ->setDurationSeconds(30)
        ;
        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
    }
}
