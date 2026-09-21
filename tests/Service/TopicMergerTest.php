<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\DailySummary;
use App\Entity\Topic;
use App\Repository\DailySummaryRepository;
use App\Repository\TopicRepository;
use App\Service\TopicMerger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TopicMergerTest extends KernelTestCase
{
    private const DATES = ['2021-03-01', '2021-03-02', '2021-03-03'];
    private const NAMES = ['topic-merger-trabajo', 'topic-merger-curro'];

    private EntityManagerInterface $entityManager;
    private TopicRepository $topicRepository;
    private DailySummaryRepository $dailySummaryRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->topicRepository = $container->get(TopicRepository::class);
        $this->dailySummaryRepository = $container->get(DailySummaryRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testMergeReassignsDailySummariesAndRemovesOrigin(): void
    {
        $trabajoId = $this->createTopic('topic-merger-trabajo')->getId();
        $curroId = $this->createTopic('topic-merger-curro')->getId();
        $this->createDailySummary('2021-03-01', [$this->topicRepository->find($curroId)]);
        $this->createDailySummary('2021-03-02', [$this->topicRepository->find($curroId)]);
        $this->createDailySummary('2021-03-03', [$this->topicRepository->find($trabajoId)]);

        // Recarga desde BD, como haría el controlador al resolver los IDs enviados en el formulario.
        $this->entityManager->clear();
        $this->merger()->merge([$this->topicRepository->find($curroId)], $this->topicRepository->find($trabajoId));

        $this->entityManager->clear();
        self::assertNull($this->topicRepository->findOneByName('topic-merger-curro'));

        $trabajo = $this->topicRepository->findOneByName('topic-merger-trabajo');
        $summaryDates = array_map(
            static fn (DailySummary $d) => $d->getDate()->format('Y-m-d'),
            iterator_to_array($trabajo->getDailySummaries()),
        );
        sort($summaryDates);
        self::assertSame(['2021-03-01', '2021-03-02', '2021-03-03'], $summaryDates);
    }

    public function testMergeDoesNotDuplicateWhenDailySummaryAlreadyHasDestination(): void
    {
        $trabajoId = $this->createTopic('topic-merger-trabajo')->getId();
        $curroId = $this->createTopic('topic-merger-curro')->getId();
        $this->createDailySummary('2021-03-01', [$this->topicRepository->find($trabajoId), $this->topicRepository->find($curroId)]);

        // Recarga desde BD, como haría el controlador al resolver los IDs enviados en el formulario.
        $this->entityManager->clear();
        $this->merger()->merge([$this->topicRepository->find($curroId)], $this->topicRepository->find($trabajoId));

        $this->entityManager->clear();
        $shared = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable('2021-03-01'));
        self::assertCount(1, $shared->getTopics());
        self::assertSame('topic-merger-trabajo', $shared->getTopics()->first()->getName());
    }

    private function merger(): TopicMerger
    {
        return self::getContainer()->get(TopicMerger::class);
    }

    private function createTopic(string $name): Topic
    {
        $topic = new Topic();
        $topic->setName($name);
        $this->entityManager->persist($topic);
        $this->entityManager->flush();

        return $topic;
    }

    /**
     * @param list<Topic> $topics
     */
    private function createDailySummary(string $date, array $topics): DailySummary
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText('Resumen de '.$date)
            ->setGeneratedAt(new \DateTimeImmutable())
        ;
        foreach ($topics as $topic) {
            $dailySummary->addTopic($topic);
        }
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();

        return $dailySummary;
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
            $topic = $this->topicRepository->findOneByName($name);
            if (null !== $topic) {
                $this->entityManager->remove($topic);
            }
        }
        $this->entityManager->flush();
    }
}
