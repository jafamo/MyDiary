<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Controller\Api\Presenter\SummaryPresenter;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Service\DateRange;
use App\Service\HistorialService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Historial')]
class HistorialController
{
    public function __construct(
        private readonly HistorialService $historialService,
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly AudioPresenter $audioPresenter,
        private readonly SummaryPresenter $summaryPresenter,
    ) {
    }

    #[Route('/api/v1/historial', name: 'api_historial', methods: ['GET'])]
    #[OA\Get(summary: 'Días con actividad de un mes', description: 'Devuelve solo los días del mes que tienen audios o resumen, para marcarlos en un calendario. El detalle de un día se pide con `GET /api/v1/historial/{fecha}`.')]
    #[OA\Parameter(name: 'month', in: 'query', description: 'Mes como `AAAA-MM`. Por defecto, el mes actual.', schema: new OA\Schema(type: 'string', example: '2026-10'))]
    #[OA\Response(response: 200, description: 'Días con actividad', content: new OA\JsonContent(
        required: ['month', 'previous_month', 'next_month', 'days'],
        properties: [
            new OA\Property(property: 'month', type: 'string', example: '2026-10'),
            new OA\Property(property: 'previous_month', type: 'string', example: '2026-09'),
            new OA\Property(property: 'next_month', type: 'string', example: '2026-11'),
            new OA\Property(property: 'days', type: 'array', items: new OA\Items(type: 'object', required: ['date', 'audio_count', 'has_summary'], properties: [
                new OA\Property(property: 'date', type: 'string', format: 'date'),
                new OA\Property(property: 'audio_count', type: 'integer', example: 3),
                new OA\Property(property: 'has_summary', type: 'boolean'),
            ])),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Parámetro de consulta inválido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function month(Request $request): JsonResponse
    {
        $firstOfMonth = ApiQuery::month($request) ?? new \DateTimeImmutable(DateRange::nowInMadrid()->format('Y-m-01'));

        return new JsonResponse([
            'month' => $firstOfMonth->format('Y-m'),
            'previous_month' => $firstOfMonth->modify('-1 month')->format('Y-m'),
            'next_month' => $firstOfMonth->modify('+1 month')->format('Y-m'),
            'days' => $this->historialService->monthDays($firstOfMonth),
        ]);
    }

    #[Route('/api/v1/historial/{fecha}', name: 'api_historial_day', requirements: ['fecha' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    #[OA\Get(summary: 'Audios y resumen de un día', description: 'Un día sin audios ni resumen responde `200` con `entries` vacío y `summary` a `null`.')]
    #[OA\Parameter(name: 'fecha', in: 'path', description: 'Día como `AAAA-MM-DD`.', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Solo los audios en ese estado.', schema: new OA\Schema(type: 'string', enum: ['PENDING', 'TRANSCRIBED', 'ERROR']))]
    #[OA\Response(response: 200, description: 'Detalle del día', content: new OA\JsonContent(
        required: ['date', 'entries', 'summary'],
        properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date'),
            new OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: '#/components/schemas/Audio')),
            new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'La fecha no existe o el estado no es válido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function day(string $fecha, Request $request): JsonResponse
    {
        $date = ApiQuery::parseDay($fecha, 'fecha');
        $status = ApiQuery::status($request);
        $dailySummary = $this->dailySummaryRepository->findOneByDate($date);

        return new JsonResponse([
            'date' => $date->format('Y-m-d'),
            'entries' => array_map($this->audioPresenter->detail(...), $this->audioRecordingRepository->findAllReceivedOn($date, $status)),
            'summary' => null !== $dailySummary ? $this->summaryPresenter->present($dailySummary) : null,
        ]);
    }
}
