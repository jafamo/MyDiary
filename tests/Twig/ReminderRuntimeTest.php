<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use App\Service\DateRange;
use App\Twig\ReminderRuntime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReminderRuntimeTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ReminderRepository $reminderRepository;
    private ReminderRuntime $runtime;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->reminderRepository = $container->get(ReminderRepository::class);
        $this->runtime = new ReminderRuntime($this->reminderRepository);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testNoBadgeWhenNoUpcomingReminders(): void
    {
        $result = $this->runtime->upcomingReminders();

        self::assertSame(0, $result['count']);
        self::assertNull($result['level']);
    }

    public function testUpcomingLevelWhenClosestReminderIsSeveralDaysAway(): void
    {
        $this->createReminder(DateRange::nowInMadrid()->modify('+3 days')->format('Y-m-d'));

        $result = $this->runtime->upcomingReminders();

        self::assertSame(1, $result['count']);
        self::assertSame('upcoming', $result['level']);
    }

    public function testUrgentLevelWhenClosestReminderIsTodayOrTomorrow(): void
    {
        $this->createReminder(DateRange::nowInMadrid()->modify('+1 day')->format('Y-m-d'));
        $this->createReminder(DateRange::nowInMadrid()->modify('+4 days')->format('Y-m-d'));

        $result = $this->runtime->upcomingReminders();

        self::assertSame(2, $result['count']);
        self::assertSame('urgent', $result['level']);
    }

    public function testNearestRemindersGroupsAllOnTheClosestDate(): void
    {
        $closest = DateRange::nowInMadrid()->modify('+1 day')->format('Y-m-d');
        $this->createReminder($closest, 'Primero de ese día');
        $this->createReminder($closest, 'Segundo de ese día');
        $this->createReminder(DateRange::nowInMadrid()->modify('+3 days')->format('Y-m-d'), 'Un día después, no debe salir');

        $result = $this->runtime->upcomingReminders();

        self::assertCount(2, $result['nearest_reminders']);
        self::assertSame($closest, $result['nearest_date']->format('Y-m-d'));
        self::assertSame(
            ['Primero de ese día', 'Segundo de ese día'],
            array_map(fn (Reminder $r) => $r->getText(), $result['nearest_reminders']),
        );
    }

    private function createReminder(string $date, string $text = 'Recordatorio de prueba'): void
    {
        $reminder = new Reminder();
        $reminder->setDate(new \DateTimeImmutable($date))->setText($text);

        $this->entityManager->persist($reminder);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        foreach ($this->reminderRepository->findUpcoming($today, 6) as $reminder) {
            $this->entityManager->remove($reminder);
        }
        $this->entityManager->flush();
    }
}
