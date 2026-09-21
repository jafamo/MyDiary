<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\DailySummary;
use App\Entity\Topic;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TopicRepositoryTest extends KernelTestCase
{
    private const DATES = ['2021-02-01', '2021-02-02', '2021-02-03'];
    private const NAMES = ['topic-repo-trabajo', 'topic-repo-curro', 'topic-repo-ocio'];

    private EntityManagerInterface $entityManager;
    private TopicRepository $repository;
    private DailySummaryRepository $dailySummaryRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->repository = $container->get(TopicRepository::class);
        $this->dailySummaryRepository = $container->get(DailySummaryRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testFindAllWithUsageCountOrdersByCountDescendingAndIncludesUnused(): void
    {
        $trabajo = $this->createTopic('topic-repo-trabajo');
        $curro = $this->createTopic('topic-repo-curro');
        $this->createTopic('topic-repo-ocio');

        $this->attachToDailySummary('2021-02-01', $trabajo);
        $this->attachToDailySummary('2021-02-02', $trabajo);
        $this->attachToDailySummary('2021-02-03', $curro);

        $rows = $this->repository->findAllWithUsageCount();
        $rows = array_values(array_filter($rows, static fn (array $row) => \in_array($row['topic']->getName(), self::NAMES, true)));

        self::assertSame('topic-repo-trabajo', $rows[0]['topic']->getName());
        self::assertSame(2, $rows[0]['count']);
        self::assertSame('topic-repo-curro', $rows[1]['topic']->getName());
        self::assertSame(1, $rows[1]['count']);
        self::assertSame('topic-repo-ocio', $rows[2]['topic']->getName());
        self::assertSame(0, $rows[2]['count']);
    }

    public function testFindOneByNameCaseInsensitiveMatchesRegardlessOfCase(): void
    {
        $this->createTopic('topic-repo-trabajo');

        $found = $this->repository->findOneByNameCaseInsensitive('TOPIC-REPO-TRABAJO');

        self::assertNotNull($found);
        self::assertSame('topic-repo-trabajo', $found->getName());
    }

    public function testFindOneByNameCaseInsensitiveExcludesGivenId(): void
    {
        $topic = $this->createTopic('topic-repo-trabajo');

        $found = $this->repository->findOneByNameCaseInsensitive('topic-repo-trabajo', $topic->getId());

        self::assertNull($found);
    }

    private function createTopic(string $name): Topic
    {
        $topic = new Topic();
        $topic->setName($name);
        $this->entityManager->persist($topic);
        $this->entityManager->flush();

        return $topic;
    }

    private function attachToDailySummary(string $date, Topic $topic): void
    {
        $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
        if (null === $dailySummary) {
            $dailySummary = new DailySummary();
            $dailySummary
                ->setDate(new \DateTimeImmutable($date))
                ->setSummaryText('Resumen de '.$date)
                ->setGeneratedAt(new \DateTimeImmutable())
            ;
        }
        $dailySummary->addTopic($topic);
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();
    }

    private function cleanUp(): void
    {
        foreach (self::DATES as $date) {
            $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }
        $this->entityManager->flush();

        foreach (self::NAMES as $name) {
            $topic = $this->repository->findOneByName($name);
            if (null !== $topic) {
                $this->entityManager->remove($topic);
            }
        }
        $this->entityManager->flush();
    }
}
