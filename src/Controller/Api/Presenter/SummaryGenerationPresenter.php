<?php

declare(strict_types=1);

namespace App\Controller\Api\Presenter;

use App\Controller\Api\ApiFormatter;
use App\Entity\DailySummaryGeneration;

/**
 * Estado de la generación del resumen de un día. Una generación atascada se presenta como fallida.
 */
class SummaryGenerationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(DailySummaryGeneration $generation, \DateTimeImmutable $now): array
    {
        $stalled = $generation->isStalled($now);

        return [
            'status' => $generation->effectiveStatus($now)->value,
            'requested_at' => ApiFormatter::instant($generation->getRequestedAt()),
            'started_at' => ApiFormatter::instant($generation->getStartedAt()),
            'finished_at' => ApiFormatter::instant($generation->getFinishedAt()),
            'error_code' => $stalled ? DailySummaryGeneration::ERROR_STALLED : $generation->getErrorCode(),
            'error_message' => $stalled ? 'La generación no terminó a tiempo. Puedes volver a pedirla.' : $generation->getErrorMessage(),
        ];
    }
}
