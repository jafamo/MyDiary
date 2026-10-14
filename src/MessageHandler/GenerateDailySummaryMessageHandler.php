<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateDailySummaryMessage;
use App\Service\DailySummaryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Genera el resumen pedido bajo demanda desde la web o la API. Los fallos de Ollama los gestiona
 * DailySummaryService (reintentos, estado y aviso por Telegram); uno inesperado deja la generación en
 * FAILED y no se reintenta, porque cada reintento repetiría los intentos contra Ollama y el aviso.
 */
#[AsMessageHandler]
class GenerateDailySummaryMessageHandler
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
    ) {
    }

    public function __invoke(GenerateDailySummaryMessage $message): void
    {
        $date = new \DateTimeImmutable($message->date);

        try {
            $this->dailySummaryService->generateForDate($date, waitForPending: false);
        } catch (\Throwable $exception) {
            $this->dailySummaryService->markUnexpectedFailure($date, 'Error inesperado al generar el resumen.');

            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        }
    }
}
