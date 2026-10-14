<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\DateRange;
use App\Service\EstadisticasService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Estadísticas')]
class EstadisticasController
{
    private const RANGE_CUSTOM = 'custom';
    private const RANGES = ['15', '30', '90', '365', self::RANGE_CUSTOM];
    private const DEFAULT_RANGE = '30';

    public function __construct(
        private readonly EstadisticasService $estadisticasService,
    ) {
    }

    #[Route('/api/v1/estadisticas', name: 'api_estadisticas', methods: ['GET'])]
    #[OA\Get(summary: 'Estadísticas y consumo de IA', description: 'Métricas del rango elegido, con las series diarias en crudo. `status` afecta a las series y medias de audios y a la comparativa; no a recordatorios, resúmenes, recuento por estado, temas ni consumo de IA.')]
    #[OA\Parameter(name: 'range', in: 'query', description: 'Días hasta hoy, o `custom` para un rango con `from` y `to`.', schema: new OA\Schema(type: 'string', enum: ['15', '30', '90', '365', 'custom'], default: '30'))]
    #[OA\Parameter(name: 'from', in: 'query', description: 'Primer día (`AAAA-MM-DD`). Obligatorio con `range=custom`.', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'Último día (`AAAA-MM-DD`). Obligatorio con `range=custom`.', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Solo los audios en ese estado.', schema: new OA\Schema(type: 'string', enum: ['PENDING', 'TRANSCRIBED', 'ERROR']))]
    #[OA\Response(response: 200, description: 'Estadísticas del rango', content: new OA\JsonContent(ref: '#/components/schemas/Statistics'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Rango desconocido, `custom` sin fechas válidas o estado inválido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function __invoke(Request $request): JsonResponse
    {
        $range = (string) ApiQuery::choice($request, 'range', self::RANGES, self::DEFAULT_RANGE);
        $status = ApiQuery::status($request);
        $fromParam = ApiQuery::day($request, 'from');
        $toParam = ApiQuery::day($request, 'to');

        if (self::RANGE_CUSTOM === $range) {
            if (null === $fromParam || null === $toParam) {
                throw ApiException::validationFailed('Con "range=custom" son obligatorios "from" y "to".');
            }
            if ($fromParam > $toParam) {
                throw ApiException::validationFailed('"from" no puede ser posterior a "to".');
            }
        }

        [$from, $to] = $this->estadisticasService->resolveRange(
            $range,
            $fromParam?->format('Y-m-d'),
            $toParam?->format('Y-m-d'),
            DateRange::nowInMadrid()->setTime(0, 0, 0),
        );

        $overview = $this->estadisticasService->overview($from, $to, $status);
        // Solo sirve para escalar las barras de la vista web.
        unset($overview['max_topic_count']);
        $aiUsage = $this->estadisticasService->aiUsage($from, $to);

        return new JsonResponse([
            'range' => $range,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            ...$overview,
            'ai_usage' => [
                'totals' => $this->snakeCaseKeys($aiUsage['tiles']),
                'days' => array_map($this->snakeCaseKeys(...), $aiUsage['days']),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function snakeCaseKeys(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key))] = $value;
        }

        return $result;
    }
}
