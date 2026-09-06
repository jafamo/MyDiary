<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\BackfillDailySummaryEmbeddingsCommand;
use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use App\Entity\DailySummary;
use App\Repository\DailySummaryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class BackfillDailySummaryEmbeddingsCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private DailySummaryRepository $dailySummaryRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->dailySummaryRepository = $container->get(DailySummaryRepository::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function testBackfillsMissingEmbeddingsAndReportsFailures(): void
    {
        $success = $this->createDailySummary('2020-03-01', 'resumen con éxito');
        $failure = $this->createDailySummary('2020-03-02', 'resumen con fallo');

        $embeddingGenerator = new class ($this->unitVector(0)) implements EmbeddingGeneratorInterface {
            public function __construct(private readonly array $successEmbedding)
            {
            }

            public function generate(string $text): array
            {
                if (str_contains($text, 'fallo')) {
                    throw new EmbeddingGenerationException('TIMEOUT', 'fallo simulado');
                }

                return $this->successEmbedding;
            }
        };

        $application = new Application();
        $application->addCommand(new BackfillDailySummaryEmbeddingsCommand(
            $this->dailySummaryRepository,
            $embeddingGenerator,
            $this->entityManager,
        ));
        $commandTester = new CommandTester($application->find('app:daily-summary:backfill-embeddings'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 embedding(s) generado(s), 1 fallo(s)', $commandTester->getDisplay());

        $this->entityManager->refresh($success);
        $this->entityManager->refresh($failure);
        self::assertSame($this->unitVector(0), array_map('floatval', $success->getEmbedding()->toArray()));
        self::assertNull($failure->getEmbedding());
    }

    public function testReportsSuccessWhenNothingToBackfill(): void
    {
        $application = new Application();
        $application->addCommand(new BackfillDailySummaryEmbeddingsCommand(
            $this->dailySummaryRepository,
            new class () implements EmbeddingGeneratorInterface {
                public function generate(string $text): array
                {
                    throw new \LogicException('No debería llamarse.');
                }
            },
            $this->entityManager,
        ));
        $commandTester = new CommandTester($application->find('app:daily-summary:backfill-embeddings'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('No hay resúmenes diarios sin embedding', $commandTester->getDisplay());
    }

    /**
     * @return list<float> vector de 768 dimensiones (una embedding de nomic-embed-text) con un único 1.0 en $activeIndex
     */
    private function unitVector(int $activeIndex): array
    {
        $vector = array_fill(0, 768, 0.0);
        $vector[$activeIndex] = 1.0;

        return $vector;
    }

    private function createDailySummary(string $date, string $summaryText): DailySummary
    {
        $dailySummary = new DailySummary();
        $dailySummary
            ->setDate(new \DateTimeImmutable($date))
            ->setSummaryText($summaryText)
            ->setGeneratedAt(new \DateTimeImmutable())
        ;
        $this->entityManager->persist($dailySummary);
        $this->entityManager->flush();

        return $dailySummary;
    }

    private function cleanUp(): void
    {
        foreach (['2020-03-01', '2020-03-02'] as $date) {
            $dailySummary = $this->dailySummaryRepository->findOneByDate(new \DateTimeImmutable($date));
            if (null !== $dailySummary) {
                $this->entityManager->remove($dailySummary);
            }
        }

        $this->entityManager->flush();
    }
}
