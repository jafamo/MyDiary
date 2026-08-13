<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\DailySummary;
use App\Repository\DailySummaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DailySummaryRepositoryTest extends KernelTestCase
{
    private const DATES = ['2020-01-01', '2020-01-02', '2020-01-03', '2020-01-04', '2020-01-05', '2020-01-06'];

    private EntityManagerInterface $entityManager;
    private DailySummaryRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(DailySummaryRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testFindLatestReturnsMostRecentFirstUpToLimit(): void
    {
        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $latest = $this->repository->findLatest(5);

        self::assertCount(5, $latest);
        self::assertSame('2020-01-06', $latest[0]->getDate()->format('Y-m-d'));
        self::assertSame('2020-01-02', $latest[4]->getDate()->format('Y-m-d'));
    }

    public function testFindLatestReturnsAllWhenFewerThanLimit(): void
    {
        $this->createDailySummary('2020-01-01');
        $this->createDailySummary('2020-01-02');

        $latest = $this->repository->findLatest(5);

        self::assertCount(2, $latest);
    }

    public function testFindPageInRangeOrdersDescendingAndPaginates(): void
    {
        foreach (self::DATES as $date) {
            $this->createDailySummary($date);
        }

        $from = new \DateTimeImmutable('2020-01-01');
        $to = new \DateTimeImmutable('2020-01-06');

        $firstPage = $this->repository->findPageInRange($from, $to, 1, 2);
        $secondPage = $this->repository->findPageInRange($from, $to, 2, 2);

        self::assertSame(['2020-01-06', '2020-01-05'], array_map(fn (DailySummary $d) => $d->getDate()->format('Y-m-d'), $firstPage));
        self::assertSame(['2020-01-04', '2020-01-03'], array_map(fn (DailySummary $d) => $d->getDate()->format('Y-m-d'), $secondPage));
    }

    public function testFindPageInRangeReturnsEmptyWhenNoResultsInRange(): void
    {
        $this->createDailySummary('2020-01-01');

        $from = new \DateTimeImmutable('2019-01-01');
        $to = new \DateTimeImmutable('2019-01-31');

        self::assertSame([], $this->repository->findPageInRange($from, $to, 1, 20));
    }

    private function createDailySummary(string $date): void
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText('Resumen de '.$date)
            ->setGeneratedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        foreach (self::DATES as $date) {
            $dailySummary = $this->repository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }
        $this->entityManager->flush();
    }
}
