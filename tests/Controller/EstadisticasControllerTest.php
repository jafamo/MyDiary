<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\DailySummary;
use App\Entity\Reminder;
use App\Entity\Transcription;
use App\Entity\User;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
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

    public function testStatusFilterRecalculatesChartButNotStatusBreakdown(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createAudio('estadisticas-ctrl-pending', 'estadisticas-ctrl-file-pending', $today, AudioRecordingStatus::PENDING);
        $this->createAudio('estadisticas-ctrl-error', 'estadisticas-ctrl-file-error', $today, AudioRecordingStatus::ERROR);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?status=PENDING');

        self::assertResponseIsSuccessful();

        $chartData = json_decode($crawler->filter('#chart-data')->text(), true);
        $todayEntry = current(array_filter($chartData, fn (array $point) => $point['date'] === $today->format('Y-m-d')));
        self::assertSame(1, $todayEntry['value'], 'El gráfico debe contar solo los audios PENDING');

        $errorsTile = $crawler->filter('.stat-tile')->eq(3)->filter('.stat-tile__value')->text();
        self::assertStringContainsString('1', $errorsTile, 'El desglose de errores no debe verse afectado por el filtro de estado');

        self::assertSelectorTextContains('[aria-label="Filtrar por estado"] .filter-pill[aria-current="true"]', 'Pendiente');
    }

    public function testInvalidStatusFilterIsIgnored(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createAudio('estadisticas-ctrl-pending', 'estadisticas-ctrl-file-pending', $today, AudioRecordingStatus::PENDING);
        $this->createAudio('estadisticas-ctrl-error', 'estadisticas-ctrl-file-error', $today, AudioRecordingStatus::ERROR);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?status=NOT_A_STATUS');

        self::assertResponseIsSuccessful();

        $chartData = json_decode($crawler->filter('#chart-data')->text(), true);
        $todayEntry = current(array_filter($chartData, fn (array $point) => $point['date'] === $today->format('Y-m-d')));
        self::assertSame(2, $todayEntry['value']);

        self::assertSelectorTextContains('[aria-label="Filtrar por estado"] .filter-pill[aria-current="true"]', 'Todos');
    }

    public function testCurrentStreakCountsConsecutiveDaysEndingAtRangeEnd(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createAudio('estadisticas-ctrl-streak-1', 'estadisticas-ctrl-streak-file-1', new \DateTimeImmutable('2026-06-07'));
        $this->createAudio('estadisticas-ctrl-streak-2', 'estadisticas-ctrl-streak-file-2', new \DateTimeImmutable('2026-06-08'));
        $this->createAudio('estadisticas-ctrl-streak-3', 'estadisticas-ctrl-streak-file-3', new \DateTimeImmutable('2026-06-09'));
        $this->createAudio('estadisticas-ctrl-streak-4', 'estadisticas-ctrl-streak-file-4', new \DateTimeImmutable('2026-06-10'));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-06-01&to=2026-06-10');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('4', $crawler->filter('.stat-tile')->eq(6)->filter('.stat-tile__value')->text());
    }

    public function testCurrentStreakIsZeroWhenLastDayHasNoAudio(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createAudio('estadisticas-ctrl-streak-zero-1', 'estadisticas-ctrl-streak-zero-file-1', new \DateTimeImmutable('2026-06-08'));
        $this->createAudio('estadisticas-ctrl-streak-zero-2', 'estadisticas-ctrl-streak-zero-file-2', new \DateTimeImmutable('2026-06-09'));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-06-01&to=2026-06-10');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('0', $crawler->filter('.stat-tile')->eq(6)->filter('.stat-tile__value')->text());
    }

    public function testRecordDayPicksEarliestOnTie(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        foreach (range(1, 3) as $i) {
            $this->createAudio("estadisticas-ctrl-record-a-{$i}", "estadisticas-ctrl-record-a-file-{$i}", new \DateTimeImmutable('2026-06-02'));
        }
        foreach (range(1, 3) as $i) {
            $this->createAudio("estadisticas-ctrl-record-b-{$i}", "estadisticas-ctrl-record-b-file-{$i}", new \DateTimeImmutable('2026-06-05'));
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-06-01&to=2026-06-10');

        self::assertResponseIsSuccessful();
        $tile = $crawler->filter('.stat-tile')->eq(7);
        self::assertStringContainsString('3', $tile->filter('.stat-tile__value')->text());
        self::assertStringContainsString('2 de jun', $tile->filter('.stat-tile__hint')->text());
    }

    public function testPreviousPeriodComparisonWhenPreviousHasData(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createAudio('estadisticas-ctrl-prev-cur-1', 'estadisticas-ctrl-prev-cur-file-1', new \DateTimeImmutable('2026-07-01'));
        $this->createAudio('estadisticas-ctrl-prev-cur-2', 'estadisticas-ctrl-prev-cur-file-2', new \DateTimeImmutable('2026-07-02'));
        $this->createAudio('estadisticas-ctrl-prev-old-1', 'estadisticas-ctrl-prev-old-file-1', new \DateTimeImmutable('2026-06-29'));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-07-01&to=2026-07-02');

        self::assertResponseIsSuccessful();
        $tileText = $crawler->filter('.stat-tile')->eq(8)->filter('.stat-tile__value')->text();
        self::assertStringContainsString('↑', $tileText);
        self::assertStringContainsString('100', $tileText);
    }

    public function testPreviousPeriodComparisonWhenPreviousHasNoData(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createAudio('estadisticas-ctrl-prev-none-1', 'estadisticas-ctrl-prev-none-file-1', new \DateTimeImmutable('2026-08-01'));

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-08-01&to=2026-08-02');

        self::assertResponseIsSuccessful();
        $tile = $crawler->filter('.stat-tile')->eq(8);
        self::assertStringContainsString('Sin datos del periodo anterior', $tile->text());
    }

    public function testRemindersTileAndChartSeriesShowRangeTotal(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $reminder1 = new Reminder();
        $reminder1->setDate(new \DateTimeImmutable('2026-06-03'))->setText('Cita médica');
        $reminder2 = new Reminder();
        $reminder2->setDate(new \DateTimeImmutable('2026-06-03'))->setText('Entrenamiento');
        $this->entityManager->persist($reminder1);
        $this->entityManager->persist($reminder2);
        $this->entityManager->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-06-01&to=2026-06-10');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2', $crawler->filter('.stat-tile')->eq(5)->filter('.stat-tile__value')->text());

        $remindersData = json_decode($crawler->filter('#chart-data-reminders')->text(), true);
        $entry = current(array_filter($remindersData, fn (array $point) => '2026-06-03' === $point['date']));
        self::assertSame(2, $entry['value']);

        $this->entityManager->remove($reminder1);
        $this->entityManager->remove($reminder2);
        $this->entityManager->flush();
    }

    public function testNewStatTilesRespectStatusFilter(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createAudio('estadisticas-ctrl-newtiles-pending', 'estadisticas-ctrl-newtiles-pending-file', new \DateTimeImmutable('2026-09-01'), AudioRecordingStatus::PENDING);
        $this->createAudio('estadisticas-ctrl-newtiles-error', 'estadisticas-ctrl-newtiles-error-file', new \DateTimeImmutable('2026-09-02'), AudioRecordingStatus::ERROR);

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2026-09-01&to=2026-09-02&status=ERROR');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1', $crawler->filter('.stat-tile')->eq(4)->filter('.stat-tile__value')->text());
        self::assertStringContainsString('1', $crawler->filter('.stat-tile')->eq(7)->filter('.stat-tile__value')->text());
    }

    public function testAiUsageTilesSumRangeAndIgnoreMissingMetrics(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();
        $this->createAiUsageFixtures();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2021-03-01&to=2021-03-03');

        self::assertResponseIsSuccessful();
        $tiles = $crawler->filter('.ai-usage .stat-tile');
        self::assertStringContainsString('7.000', $tiles->eq(0)->text());
        self::assertStringContainsString('6.100 entrada · 900 salida', $tiles->eq(0)->text());
        self::assertStringContainsString('3.500', $tiles->eq(1)->text());
        self::assertStringContainsString('2 resúmenes con tokens', $tiles->eq(1)->text());
        self::assertStringContainsString('1 min 42 s', $tiles->eq(2)->text());
        self::assertStringContainsString('Whisper 14 s · Ollama 1 min 10 s', $tiles->eq(3)->text());
    }

    public function testAiUsageIgnoresStatusFilter(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();
        $this->createAiUsageFixtures();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2021-03-01&to=2021-03-03&status=ERROR');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('7.000', $crawler->filter('.ai-usage .stat-tile')->eq(0)->text());
        self::assertStringContainsString('1 min 42 s', $crawler->filter('.ai-usage .stat-tile')->eq(2)->text());
    }

    public function testAiUsageTableListsDaysNewestFirstWithDashWhenNoSummary(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();
        $this->createAiUsageFixtures();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/estadisticas?range=custom&from=2021-03-01&to=2021-03-03');

        self::assertResponseIsSuccessful();
        $rows = $crawler->filter('.usage-table tbody tr');
        self::assertCount(3, $rows);

        $newest = $rows->eq(0)->filter('td');
        self::assertSame('3 mar', $newest->eq(0)->text());
        self::assertSame('—', $newest->eq(6)->text());

        $oldest = $rows->eq(2)->filter('td');
        self::assertSame('1 mar', $oldest->eq(0)->text());
        self::assertSame('1', $oldest->eq(1)->text());
        self::assertSame('1:42', $oldest->eq(2)->text());
        self::assertSame('14 s', $oldest->eq(3)->text());
        self::assertSame('3.000', $oldest->eq(6)->text());

        self::assertCount(4, $crawler->filter('.bar-chart rect'));
    }

    private function createAiUsageFixtures(): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('estadisticas-ctrl-usage-1')
            ->setTelegramFileUniqueId('estadisticas-ctrl-usage-file-1')
            ->setFilePath('/data/audio/estadisticas-ctrl-usage-file-1.ogg')
            ->setReceivedAt(new \DateTimeImmutable('2021-03-01 11:00:00', new \DateTimeZone('Europe/Madrid')))
            ->setDurationSeconds(102)
            ->setStatus(AudioRecordingStatus::TRANSCRIBED)
        ;
        $this->entityManager->persist($audioRecording);

        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent('audio con métricas')
            ->setFilePath('/data/transcriptions/estadisticas-ctrl-usage-file-1.txt')
            ->setProcessingMs(14000)
            ->setModel('whisper-1')
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->persist($transcription);

        foreach ([
            ['2021-03-01', 2600, 400, 30000],
            ['2021-03-02', 3500, 500, 40000],
            ['2021-03-03', null, null, null],
        ] as [$date, $promptTokens, $completionTokens, $generationMs]) {
            $dailySummary = new DailySummary();
            $dailySummary
                ->setDate(new \DateTimeImmutable($date))
                ->setSummaryText('Resumen de '.$date)
                ->setGeneratedAt(new \DateTimeImmutable())
                ->setPromptTokens($promptTokens)
                ->setCompletionTokens($completionTokens)
                ->setGenerationMs($generationMs)
            ;
            $this->entityManager->persist($dailySummary);
        }

        $this->entityManager->flush();
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

    private function createAudio(string $messageId, string $fileUniqueId, \DateTimeImmutable $date, ?AudioRecordingStatus $status = null): void
    {
        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId($messageId)
            ->setTelegramFileUniqueId($fileUniqueId)
            ->setFilePath('/data/audio/'.$fileUniqueId.'.ogg')
            ->setReceivedAt($date->setTime(11, 0, 0))
            ->setDurationSeconds(30)
        ;

        if (null !== $status) {
            $audioRecording->setStatus($status);
            if (AudioRecordingStatus::ERROR === $status) {
                $audioRecording->setErrorCode('TIMEOUT')->setErrorMessage('algo falló');
            }
        }

        $this->entityManager->persist($audioRecording);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        $messageIds = [
            'estadisticas-ctrl-msg-1', 'estadisticas-ctrl-pending', 'estadisticas-ctrl-error',
            'estadisticas-ctrl-streak-1', 'estadisticas-ctrl-streak-2', 'estadisticas-ctrl-streak-3', 'estadisticas-ctrl-streak-4',
            'estadisticas-ctrl-streak-zero-1', 'estadisticas-ctrl-streak-zero-2',
            'estadisticas-ctrl-record-a-1', 'estadisticas-ctrl-record-a-2', 'estadisticas-ctrl-record-a-3',
            'estadisticas-ctrl-record-b-1', 'estadisticas-ctrl-record-b-2', 'estadisticas-ctrl-record-b-3',
            'estadisticas-ctrl-prev-cur-1', 'estadisticas-ctrl-prev-cur-2', 'estadisticas-ctrl-prev-old-1',
            'estadisticas-ctrl-prev-none-1',
            'estadisticas-ctrl-newtiles-pending', 'estadisticas-ctrl-newtiles-error',
            'estadisticas-ctrl-usage-1',
        ];

        $dailySummaryRepository = self::getContainer()->get(DailySummaryRepository::class);
        foreach (['2021-03-01', '2021-03-02', '2021-03-03'] as $date) {
            $dailySummary = $dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }

        foreach ($messageIds as $messageId) {
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
