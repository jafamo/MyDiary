<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\SummaryPresenter;
use App\Entity\DailySummary;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Resúmenes')]
class ResumenesController
{
    public function __construct(
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly SummaryPresenter $summaryPresenter,
    ) {
    }

    #[Route('/api/v1/resumenes', name: 'api_resumenes', methods: ['GET'])]
    #[OA\Get(summary: 'Resúmenes diarios', description: 'Listado paginado del más reciente al más antiguo. `from` y `to` limitan el rango y se envían los dos o ninguno.')]
    #[OA\Parameter(name: 'from', in: 'query', description: 'Primer día del rango (`AAAA-MM-DD`, inclusive).', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'Último día del rango (`AAAA-MM-DD`, inclusive).', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/per_page')]
    #[OA\Response(response: 200, description: 'Página de resúmenes', content: new OA\JsonContent(
        required: ['items', 'page', 'per_page', 'total'],
        properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(allOf: [
                new OA\Schema(ref: '#/components/schemas/Summary'),
                new OA\Schema(required: ['audio_count'], properties: [
                    new OA\Property(property: 'audio_count', type: 'integer', description: 'Audios recibidos ese día.', example: 4),
                ]),
            ])),
            new OA\Property(property: 'page', type: 'integer', example: 1),
            new OA\Property(property: 'per_page', type: 'integer', example: 20),
            new OA\Property(property: 'total', type: 'integer', example: 57),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Rango incompleto o invertido, o paginación inválida', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function __invoke(Request $request): JsonResponse
    {
        $from = ApiQuery::day($request, 'from');
        $to = ApiQuery::day($request, 'to');
        $page = ApiQuery::page($request);
        $perPage = ApiQuery::perPage($request);

        if ((null === $from) !== (null === $to)) {
            throw ApiException::validationFailed('Los parámetros "from" y "to" se envían los dos o ninguno.');
        }

        if (null !== $from && null !== $to) {
            if ($from > $to) {
                throw ApiException::validationFailed('"from" no puede ser posterior a "to".');
            }
            $total = $this->dailySummaryRepository->countInRange($from, $to);
            $summaries = $this->dailySummaryRepository->findPageInRange($from, $to, $page, $perPage);
        } else {
            $total = $this->dailySummaryRepository->count([]);
            $summaries = $this->dailySummaryRepository->findBy([], ['date' => 'DESC'], $perPage, ($page - 1) * $perPage);
        }

        $audioCounts = $this->audioCountsFor($summaries);

        return new JsonResponse([
            'items' => array_map(
                fn (DailySummary $summary): array => $this->summaryPresenter->present($summary) + [
                    'audio_count' => $audioCounts[$summary->getDate()->format('Y-m-d')] ?? 0,
                ],
                $summaries,
            ),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
        ]);
    }

    /**
     * @param array<DailySummary> $summaries
     *
     * @return array<string, int> número de audios por fecha (Y-m-d) de los resúmenes dados
     */
    private function audioCountsFor(array $summaries): array
    {
        if ([] === $summaries) {
            return [];
        }

        $dates = array_map(static fn (DailySummary $summary) => $summary->getDate(), $summaries);

        return $this->audioRecordingRepository->countByDateInRange(min($dates), max($dates));
    }
}
