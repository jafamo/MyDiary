<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Reminder;
use App\Entity\User;
use App\Repository\ReminderRepository;
use App\Repository\UserRepository;
use App\Service\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RecordatoriosControllerTest extends WebTestCase
{
    private const TEST_DATES = ['2020-02-01', '2020-02-02', '2020-02-03', '2020-02-04'];

    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private ReminderRepository $reminderRepository;

    /** @var int[] */
    private array $createdReminderIds = [];

    protected function tearDown(): void
    {
        if (isset($this->entityManager)) {
            $this->cleanUp();
        }
        parent::tearDown();
    }

    public function testCreateReminder(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('POST', '/recordatorios', [
            'reminder' => [
                'date' => '2020-02-01',
                'text' => 'Entrenamiento de pádel',
                '_token' => $this->csrfToken($client, 'reminder'),
            ],
        ]);

        self::assertResponseRedirects();

        $reminders = $this->reminderRepository->findAllOn(new \DateTimeImmutable('2020-02-01'));
        self::assertCount(1, $reminders);
        self::assertSame('Entrenamiento de pádel', $reminders[0]->getText());
        self::assertNull($reminders[0]->getTime());
    }

    public function testCreateReminderWithTime(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('POST', '/recordatorios', [
            'reminder' => [
                'date' => '2020-02-01',
                'time' => '10:00',
                'text' => 'Cita médica',
                '_token' => $this->csrfToken($client, 'reminder'),
            ],
        ]);

        self::assertResponseRedirects();

        $reminders = $this->reminderRepository->findAllOn(new \DateTimeImmutable('2020-02-01'));
        self::assertCount(1, $reminders);
        self::assertSame('10:00', $reminders[0]->getTime()->format('H:i'));
    }

    public function testCreateWithoutTextIsRejected(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('POST', '/recordatorios', [
            'reminder' => [
                'date' => '2020-02-01',
                'text' => '',
                '_token' => $this->csrfToken($client, 'reminder'),
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->reminderRepository->findAllOn(new \DateTimeImmutable('2020-02-01')));
    }

    public function testEditUpdatesTextAndDate(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $reminder = $this->createReminder('2020-02-01', 'Texto original');
        $reminderId = $reminder->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/recordatorios/%d/editar', $reminderId), [
            'reminder' => [
                'date' => '2020-02-02',
                'text' => 'Texto corregido',
                '_token' => $this->csrfToken($client, 'reminder'),
            ],
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        $updated = $this->reminderRepository->find($reminderId);
        self::assertSame('Texto corregido', $updated->getText());
        self::assertSame('2020-02-02', $updated->getDate()->format('Y-m-d'));
    }

    public function testDeleteRemovesReminder(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $reminder = $this->createReminder('2020-02-01', 'A borrar');
        $reminderId = $reminder->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/recordatorios/%d/eliminar', $reminderId), [
            '_token' => $this->csrfToken($client, 'reminder_delete'),
        ]);

        self::assertResponseRedirects();

        $this->entityManager->clear();
        self::assertNull($this->reminderRepository->find($reminderId));
    }

    public function testDeleteWithInvalidCsrfIsForbidden(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $this->bootServices();
        $user = $this->createTestUser();

        $reminder = $this->createReminder('2020-02-01', 'No debe borrarse');
        $reminderId = $reminder->getId();

        $client->loginUser($user);
        $client->request('POST', sprintf('/recordatorios/%d/eliminar', $reminderId), [
            '_token' => 'token-invalido',
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->reminderRepository->find($reminderId));
    }

    public function testCalendarMarksDayWithReminders(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createReminder($today->format('Y-m-d'), 'Recordatorio de hoy');

        $client->loginUser($user);
        $client->request('GET', '/recordatorios');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.calendar__day--has-entries');
    }

    public function testSelectingDayShowsReminderList(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $this->createReminder('2020-02-01', 'Primero');
        $this->createReminder('2020-02-01', 'Segundo');

        $client->loginUser($user);
        $client->request('GET', '/recordatorios?year=2020&month=2&date=2020-02-01');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.day-preview .reminder-grid');
        self::assertSelectorCount(2, '.day-preview .reminder-card');
    }

    public function testAdjacentMonthDaysAreMutedAndNavigationWorks(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $client->loginUser($user);
        $client->request('GET', '/recordatorios?year=2026&month=8');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.calendar__day--muted');
        self::assertSelectorTextContains('.calendar-nav__month', 'Agosto');
    }

    public function testStatTilesShowTotalsAndNextReminder(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createReminder($today->modify('-1 day')->format('Y-m-d'), 'Pasado');
        $this->createReminder($today->modify('+2 days')->format('Y-m-d'), 'El más cercano');
        $this->createReminder($today->modify('+40 days')->format('Y-m-d'), 'Lejano');

        $client->loginUser($user);
        $client->request('GET', '/recordatorios');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.stat-grid', '3');
        self::assertSelectorTextContains('.stat-grid', 'En 2 días');
    }

    public function testUpcomingListShowsRemindersFromOtherMonths(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createReminder($today->modify('+40 days')->format('Y-m-d'), 'Recordatorio de otro mes');

        $client->loginUser($user);
        $client->request('GET', '/recordatorios');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.upcoming-reminders', 'Recordatorio de otro mes');
    }

    public function testUpcomingListPaginates(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        for ($i = 1; $i <= 21; ++$i) {
            $this->createReminder($today->modify(sprintf('+%d days', $i))->format('Y-m-d'), 'Recordatorio '.$i);
        }

        $client->loginUser($user);
        $client->request('GET', '/recordatorios?page=2');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.upcoming-reminders .reminder-card');
        self::assertSelectorTextContains('.upcoming-reminders', 'Página 2 de 2');
    }

    public function testPastReminderAppearsInHistoryNotUpcoming(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        $this->createReminder($today->modify('-3 days')->format('Y-m-d'), 'Recordatorio pasado');
        $this->createReminder($today->modify('+3 days')->format('Y-m-d'), 'Recordatorio futuro');

        $client->loginUser($user);
        $client->request('GET', '/recordatorios');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.reminder-history', 'Recordatorio pasado');
        self::assertSelectorTextNotContains('.reminder-history', 'Recordatorio futuro');
        self::assertSelectorTextContains('.upcoming-reminders', 'Recordatorio futuro');
        self::assertSelectorTextNotContains('.upcoming-reminders', 'Recordatorio pasado');
    }

    public function testHistoryPaginatesIndependentlyFromUpcoming(): void
    {
        $client = static::createClient();
        $this->bootServices();
        $user = $this->createTestUser();

        $today = DateRange::nowInMadrid();
        for ($i = 1; $i <= 21; ++$i) {
            $this->createReminder($today->modify(sprintf('-%d days', $i))->format('Y-m-d'), 'Recordatorio pasado '.$i);
        }

        $client->loginUser($user);
        $client->request('GET', '/recordatorios?history_page=2');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.reminder-history .reminder-card');
        self::assertSelectorTextContains('.reminder-history', 'Página 2 de 2');
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

    private function createReminder(string $date, string $text): Reminder
    {
        $reminder = new Reminder();
        $reminder->setDate(new \DateTimeImmutable($date))->setText($text);

        $this->entityManager->persist($reminder);
        $this->entityManager->flush();

        $this->createdReminderIds[] = $reminder->getId();

        return $reminder;
    }

    private function bootServices(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->userRepository = self::getContainer()->get(UserRepository::class);
        $this->reminderRepository = self::getContainer()->get(ReminderRepository::class);
        $this->cleanUp();
    }

    private function createTestUser(): User
    {
        $passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('test_recordatorios_user');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasher->hashPassword($user, 'a-strong-password'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function cleanUp(): void
    {
        $dates = array_merge(self::TEST_DATES, [DateRange::nowInMadrid()->format('Y-m-d')]);
        foreach ($dates as $date) {
            foreach ($this->reminderRepository->findAllOn(new \DateTimeImmutable($date)) as $reminder) {
                $this->entityManager->remove($reminder);
            }
        }

        foreach ($this->createdReminderIds as $reminderId) {
            $reminder = $this->reminderRepository->find($reminderId);
            if (null !== $reminder) {
                $this->entityManager->remove($reminder);
            }
        }
        $this->createdReminderIds = [];

        $user = $this->userRepository->findOneByUsername('test_recordatorios_user');
        if (null !== $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();
    }
}
