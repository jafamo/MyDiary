<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Controller\Api\Presenter\SummaryPresenter;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Service\DateRange;
use App\Service\DiarioDashboardService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Diario')]
class DiarioController
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly DiarioDashboardService $dashboardService,
        private readonly AudioPresenter $audioPresenter,
        private readonly SummaryPresenter $summaryPresenter,
    ) {
    }

    #[Route('/api/v1/diario', name: 'api_diario', methods: ['GET'])]
    #[OA\Get(summary: 'Diario de hoy', description: 'Audios recibidos hoy (zona horaria de la aplicación) con su transcripción, el resumen del día si ya existe y los indicadores de la portada: rachas, total de la semana y tema del mes.')]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Solo los audios en ese estado.', schema: new OA\Schema(type: 'string', enum: ['PENDING', 'TRANSCRIBED', 'ERROR']))]
    #[OA\Response(response: 200, description: 'Diario de hoy', content: new OA\JsonContent(
        required: ['date', 'entries', 'summary', 'streak', 'week', 'top_topic'],
        properties: [
            new OA\Property(property: 'date', type: 'string', format: 'date'),
            new OA\Property(property: 'entries', type: 'array', items: new OA\Items(ref: '#/components/schemas/Audio')),
            new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
            new OA\Property(property: 'streak', type: 'object', required: ['current', 'best'], properties: [
                new OA\Property(property: 'current', type: 'integer', description: 'Días seguidos con audio hasta hoy o ayer.', example: 4),
                new OA\Property(property: 'best', type: 'integer', description: 'Mejor racha histórica.', example: 12),
            ]),
            new OA\Property(property: 'week', type: 'object', required: ['total', 'delta'], properties: [
                new OA\Property(property: 'total', type: 'integer', description: 'Audios de esta semana.', example: 9),
                new OA\Property(property: 'delta', type: 'integer', description: 'Diferencia con la semana anterior.', example: -2),
            ]),
            new OA\Property(property: 'top_topic', type: 'object', nullable: true, description: 'Tema más frecuente del mes.', required: ['name', 'count'], properties: [
                new OA\Property(property: 'name', type: 'string', example: 'trabajo'),
                new OA\Property(property: 'count', type: 'integer', example: 6),
            ]),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Parámetro de consulta inválido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function __invoke(Request $request): JsonResponse
    {
        $status = ApiQuery::status($request);
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $dailySummary = $this->dailySummaryRepository->findOneByDate($today);
        $week = $this->dashboardService->getWeekTotalAndTrend();

        return new JsonResponse([
            'date' => $today->format('Y-m-d'),
            'entries' => array_map($this->audioPresenter->detail(...), $this->audioRecordingRepository->findAllReceivedOn($today, $status)),
            'summary' => null !== $dailySummary ? $this->summaryPresenter->present($dailySummary) : null,
            'streak' => [
                'current' => $this->dashboardService->getCurrentStreak(),
                'best' => $this->dashboardService->getBestStreak(),
            ],
            'week' => ['total' => $week['total'], 'delta' => $week['delta']],
            'top_topic' => $this->dashboardService->getTopTopicOfMonth(),
        ]);
    }
}
