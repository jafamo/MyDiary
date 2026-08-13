<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Reminder;
use App\Repository\ReminderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ReminderRepositoryTest extends KernelTestCase
{
    private const DATES = ['2020-01-01', '2020-01-02', '2020-01-03', '2020-01-04', '2020-01-05', '2020-01-06', '2020-01-10'];

    private EntityManagerInterface $entityManager;
    private ReminderRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(ReminderRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testFindAllOnReturnsSeveralRemindersForTheSameDay(): void
    {
        $this->createReminder('2020-01-01', 'Primero');
        $this->createReminder('2020-01-01', 'Segundo');
        $this->createReminder('2020-01-02', 'Otro día');

        $reminders = $this->repository->findAllOn(new \DateTimeImmutable('2020-01-01'));

        self::assertCount(2, $reminders);
        self::assertSame(['Primero', 'Segundo'], array_map(fn (Reminder $r) => $r->getText(), $reminders));
    }

    public function testCountByDateInRangeGroupsByDate(): void
    {
        $this->createReminder('2020-01-01', 'A');
        $this->createReminder('2020-01-01', 'B');
        $this->createReminder('2020-01-03', 'C');

        $counts = $this->repository->countByDateInRange(new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-01-06'));

        self::assertSame(2, $counts['2020-01-01']);
        self::assertSame(1, $counts['2020-01-03']);
        self::assertArrayNotHasKey('2020-01-10', $counts);
    }

    public function testFindUpcomingIncludesWithinWindowExcludesBeyondAndPast(): void
    {
        $today = new \DateTimeImmutable('2020-01-01');
        $this->createReminder('2019-12-31', 'Ayer, no debe salir');
        $this->createReminder('2020-01-01', 'Hoy');
        $this->createReminder('2020-01-05', 'En 4 días, dentro de la ventana');
        $this->createReminder('2020-01-06', 'En 5 días, límite de la ventana');
        $this->createReminder('2020-01-10', 'Muy lejos, no debe salir');

        $upcoming = $this->repository->findUpcoming($today, 5);

        self::assertSame(['Hoy', 'En 4 días, dentro de la ventana', 'En 5 días, límite de la ventana'], array_map(fn (Reminder $r) => $r->getText(), $upcoming));
    }

    public function testCountFromDateExcludesPast(): void
    {
        $this->createReminder('2020-01-01', 'Ayer, fuera de cuenta');
        $this->createReminder('2020-01-02', 'Hoy');
        $this->createReminder('2020-01-05', 'Futuro');

        self::assertSame(2, $this->repository->countFromDate(new \DateTimeImmutable('2020-01-02')));
    }

    public function testFindPageFromDateOrdersAscendingAndPaginates(): void
    {
        foreach (self::DATES as $date) {
            $this->createReminder($date, 'Recordatorio '.$date);
        }

        $today = new \DateTimeImmutable('2020-01-01');

        $firstPage = $this->repository->findPageFromDate($today, 1, 3);
        $secondPage = $this->repository->findPageFromDate($today, 2, 3);

        self::assertSame(['2020-01-01', '2020-01-02', '2020-01-03'], array_map(fn (Reminder $r) => $r->getDate()->format('Y-m-d'), $firstPage));
        self::assertSame(['2020-01-04', '2020-01-05', '2020-01-06'], array_map(fn (Reminder $r) => $r->getDate()->format('Y-m-d'), $secondPage));
    }

    public function testCountBeforeDateExcludesFuture(): void
    {
        $this->createReminder('2020-01-01', 'Pasado');
        $this->createReminder('2020-01-02', 'Hoy, fuera de cuenta');
        $this->createReminder('2020-01-05', 'Futuro, fuera de cuenta');

        self::assertSame(1, $this->repository->countBeforeDate(new \DateTimeImmutable('2020-01-02')));
    }

    public function testFindPageBeforeDateOrdersDescendingAndPaginates(): void
    {
        foreach (self::DATES as $date) {
            $this->createReminder($date, 'Recordatorio '.$date);
        }

        $before = new \DateTimeImmutable('2020-01-11');

        $firstPage = $this->repository->findPageBeforeDate($before, 1, 3);
        $secondPage = $this->repository->findPageBeforeDate($before, 2, 3);

        self::assertSame(['2020-01-10', '2020-01-06', '2020-01-05'], array_map(fn (Reminder $r) => $r->getDate()->format('Y-m-d'), $firstPage));
        self::assertSame(['2020-01-04', '2020-01-03', '2020-01-02'], array_map(fn (Reminder $r) => $r->getDate()->format('Y-m-d'), $secondPage));
    }

    private function createReminder(string $date, string $text): void
    {
        $reminder = new Reminder();
        $reminder->setDate(new \DateTimeImmutable($date))->setText($text);
        $this->entityManager->persist($reminder);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        foreach (array_merge(self::DATES, ['2019-12-31']) as $date) {
            foreach ($this->repository->findAllOn(new \DateTimeImmutable($date)) as $reminder) {
                $this->entityManager->remove($reminder);
            }
        }
        $this->entityManager->flush();
    }
}
