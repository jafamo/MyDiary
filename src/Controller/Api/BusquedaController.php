<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Controller\Api\Presenter\ReminderPresenter;
use App\Controller\Api\Presenter\SummaryPresenter;
use App\Service\SearchService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Búsqueda')]
class BusquedaController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly AudioPresenter $audioPresenter,
        private readonly SummaryPresenter $summaryPresenter,
        private readonly ReminderPresenter $reminderPresenter,
    ) {
    }

    #[Route('/api/v1/busqueda', name: 'api_busqueda', methods: ['GET'])]
    #[OA\Get(summary: 'Buscar en el diario', description: 'Búsqueda semántica en transcripciones y resúmenes (hasta 20 resultados, ordenados del más cercano al más lejano) y búsqueda por texto en recordatorios. Si el servicio de embeddings falla, `results` va vacío y los recordatorios se devuelven igual.')]
    #[OA\Parameter(name: 'q', in: 'query', required: true, description: 'Texto a buscar.', schema: new OA\Schema(type: 'string', example: 'dentista'))]
    #[OA\Response(response: 200, description: 'Resultados', content: new OA\JsonContent(
        required: ['query', 'results', 'reminders'],
        properties: [
            new OA\Property(property: 'query', type: 'string', example: 'dentista'),
            new OA\Property(property: 'results', type: 'array', items: new OA\Items(type: 'object', required: ['type', 'distance', 'date', 'audio', 'summary'], properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['transcription', 'daily_summary']),
                new OA\Property(property: 'distance', type: 'number', format: 'float', description: 'Distancia coseno a la consulta: cuanto menor, más parecido.', example: 0.31),
                new OA\Property(property: 'date', type: 'string', format: 'date', description: 'Día del audio o del resumen.'),
                new OA\Property(property: 'audio', ref: '#/components/schemas/Audio', nullable: true, description: 'Presente si `type` es `transcription`.'),
                new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true, description: 'Presente si `type` es `daily_summary`.'),
            ])),
            new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(ref: '#/components/schemas/Reminder')),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta `q` o está vacío', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function __invoke(Request $request): JsonResponse
    {
        $query = ApiQuery::string($request, 'q') ?? throw ApiException::validationFailed('El parámetro "q" es obligatorio.');

        $search = $this->searchService->search($query);

        return new JsonResponse([
            'query' => $query,
            'results' => array_map(fn (array $result): array => [
                'type' => $result['type'],
                'distance' => round($result['distance'], 4),
                'date' => null !== $result['transcription'] ? ApiFormatter::day($result['date']) : ApiFormatter::date($result['date']),
                'audio' => null !== $result['transcription'] ? $this->audioPresenter->detail($result['transcription']->getAudioRecording()) : null,
                'summary' => null !== $result['dailySummary'] ? $this->summaryPresenter->present($result['dailySummary']) : null,
            ], $search['results']),
            'reminders' => $this->reminderPresenter->presentAll($search['reminders']),
        ]);
    }
}
