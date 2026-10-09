<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\TopicPresenter;
use App\Repository\TopicRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Temas')]
class TopicsController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly TopicPresenter $topicPresenter,
    ) {
    }

    #[Route('/api/v1/topics', name: 'api_topics', methods: ['GET'])]
    #[OA\Get(summary: 'Temas', description: 'Todos los temas, sin paginar, del más usado al menos usado y después por nombre.')]
    #[OA\Response(response: 200, description: 'Temas', content: new OA\JsonContent(
        required: ['items', 'total'],
        properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(allOf: [
                new OA\Schema(ref: '#/components/schemas/Topic'),
                new OA\Schema(required: ['usage_count', 'last_used'], properties: [
                    new OA\Property(property: 'usage_count', type: 'integer', description: 'Resúmenes que usan el tema.', example: 6),
                    new OA\Property(property: 'last_used', type: 'string', format: 'date', nullable: true, description: 'Día del resumen más reciente que lo usa.'),
                ]),
            ])),
            new OA\Property(property: 'total', type: 'integer', example: 42),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function __invoke(): JsonResponse
    {
        $topics = $this->topicRepository->findAllWithUsageCount();

        return new JsonResponse([
            'items' => array_map(fn (array $row): array => $this->topicPresenter->present($row['topic']) + [
                'usage_count' => $row['count'],
                'last_used' => ApiFormatter::date($row['lastUsed']),
            ], $topics),
            'total' => \count($topics),
        ]);
    }
}
