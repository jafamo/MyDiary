<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DailySummaryGeneration;
use App\Message\GenerateDailySummaryMessage;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryGenerationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Pide la generación del resumen de un día sin esperarla: la deja en cola para el worker.
 * Compartido por el botón de Diario y `POST /api/v1/diario/resumen`.
 */
class DailySummaryGenerationRequester
{
    public const SOURCE_WEB = 'web';
    public const SOURCE_API = 'api';

    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryGenerationRepository $dailySummaryGenerationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Devuelve la generación encolada o, si ya había una en curso, esa misma sin encolar otra.
     *
     * @throws NoTranscriptionsToSummarizeException si ese día no hay transcripciones
     */
    public function request(\DateTimeImmutable $date, string $source): DailySummaryGeneration
    {
        $date = $date->setTime(0, 0, 0);
        $now = new \DateTimeImmutable();

        $generation = $this->dailySummaryGenerationRepository->findOneByDate($date);
        if (null !== $generation && $generation->isInProgress($now)) {
            return $generation;
        }

        if (!$this->audioRecordingRepository->existsTranscribedReceivedOn($date)) {
            throw new NoTranscriptionsToSummarizeException('No hay transcripciones de ese día.');
        }

        if (null === $generation) {
            $generation = new DailySummaryGeneration($date);
            $this->entityManager->persist($generation);
        }
        $generation->markQueued($now);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new GenerateDailySummaryMessage($date->format('Y-m-d')));

        $this->logger->info('Generación del resumen diario pedida', [
            'event' => 'daily_summary.generation_requested',
            'daily_summary_date' => $date->format('Y-m-d'),
            'daily_summary_request_source' => $source,
        ]);

        return $generation;
    }
}
