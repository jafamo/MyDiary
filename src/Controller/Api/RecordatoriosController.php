<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\ReminderPresenter;
use App\Service\DateRange;
use App\Service\RecordatoriosService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Recordatorios')]
class RecordatoriosController
{
    private const SCOPES = [RecordatoriosService::SCOPE_UPCOMING, RecordatoriosService::SCOPE_HISTORY];

    public function __construct(
        private readonly RecordatoriosService $recordatoriosService,
        private readonly ReminderPresenter $reminderPresenter,
    ) {
    }

    #[Route('/api/v1/recordatorios', name: 'api_recordatorios', methods: ['GET'])]
    #[OA\Get(summary: 'Recordatorios', description: 'Listado paginado. Admite un solo filtro: `scope` (por defecto `upcoming`), `month` o `date`; combinarlos es un `422`.')]
    #[OA\Parameter(name: 'scope', in: 'query', description: '`upcoming`: de hoy en adelante, del más cercano al más lejano. `history`: anteriores a hoy, del más reciente al más antiguo.', schema: new OA\Schema(type: 'string', enum: ['upcoming', 'history'], default: 'upcoming'))]
    #[OA\Parameter(name: 'month', in: 'query', description: 'Los de ese mes (`AAAA-MM`), por fecha ascendente.', schema: new OA\Schema(type: 'string', example: '2026-10'))]
    #[OA\Parameter(name: 'date', in: 'query', description: 'Los de ese día (`AAAA-MM-DD`).', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/per_page')]
    #[OA\Response(response: 200, description: 'Página de recordatorios', content: new OA\JsonContent(
        required: ['items', 'page', 'per_page', 'total'],
        properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/Reminder')),
            new OA\Property(property: 'page', type: 'integer', example: 1),
            new OA\Property(property: 'per_page', type: 'integer', example: 20),
            new OA\Property(property: 'total', type: 'integer', example: 8),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Filtro inválido, filtros combinados o paginación inválida', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function index(Request $request): JsonResponse
    {
        $scope = ApiQuery::choice($request, 'scope', self::SCOPES);
        $month = ApiQuery::month($request);
        $date = ApiQuery::day($request, 'date');
        $page = ApiQuery::page($request);
        $perPage = ApiQuery::perPage($request);

        if (\count(array_filter([$scope, $month, $date], static fn (mixed $filter): bool => null !== $filter)) > 1) {
            throw ApiException::validationFailed('Los parámetros "scope", "month" y "date" no se pueden combinar.');
        }

        [$from, $to] = match (true) {
            null !== $date => [$date, $date],
            null !== $month => [$month, $month->modify('last day of this month')],
            default => [null, null],
        };

        $result = $this->recordatoriosService->page(
            DateRange::nowInMadrid()->setTime(0, 0, 0),
            $scope ?? RecordatoriosService::SCOPE_UPCOMING,
            $from,
            $to,
            $page,
            $perPage,
        );

        return new JsonResponse([
            'items' => $this->reminderPresenter->presentAll($result['items']),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $result['total'],
        ]);
    }

    #[Route('/api/v1/recordatorios/proximos', name: 'api_recordatorios_proximos', methods: ['GET'])]
    #[OA\Get(summary: 'Aviso de recordatorios cercanos', description: 'El dato de la campana de la web: recordatorios de los próximos 5 días (hoy incluido) y los del día más cercano.')]
    #[OA\Response(response: 200, description: 'Aviso', content: new OA\JsonContent(
        required: ['count', 'level', 'nearest_date', 'reminders'],
        properties: [
            new OA\Property(property: 'count', type: 'integer', description: 'Recordatorios en los próximos 5 días.', example: 3),
            new OA\Property(property: 'level', type: 'string', enum: ['urgent', 'upcoming'], nullable: true, description: '`urgent` si el más cercano es hoy o mañana; `null` si no hay ninguno.'),
            new OA\Property(property: 'nearest_date', type: 'string', format: 'date', nullable: true),
            new OA\Property(property: 'reminders', type: 'array', description: 'Los del día más cercano.', items: new OA\Items(ref: '#/components/schemas/Reminder')),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function upcoming(): JsonResponse
    {
        $alert = $this->recordatoriosService->upcomingAlert(DateRange::nowInMadrid()->setTime(0, 0, 0));

        return new JsonResponse([
            'count' => $alert['count'],
            'level' => $alert['level'],
            'nearest_date' => ApiFormatter::date($alert['nearest_date']),
            'reminders' => $this->reminderPresenter->presentAll($alert['nearest_reminders']),
        ]);
    }
}
