<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Api\Presenter\TopicPresenter;
use App\Entity\Topic;
use App\Form\TopicMergeType;
use App\Form\TopicRenameType;
use App\Repository\TopicRepository;
use App\Service\TopicMerger;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Temas')]
class TopicsController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly TopicPresenter $topicPresenter,
        private readonly ApiForm $apiForm,
        private readonly EntityManagerInterface $entityManager,
        private readonly TopicMerger $topicMerger,
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
    public function index(): JsonResponse
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

    #[Route('/api/v1/topics/{id}', name: 'api_topics_rename', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    #[OA\Patch(summary: 'Renombrar un tema', description: 'El nombre no puede coincidir, sin distinguir mayúsculas, con el de otro tema: en ese caso responde `409` y lo indicado es fusionarlos.')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'Identificador del tema', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name'],
        properties: [
            new OA\Property(property: 'name', type: 'string', maxLength: TopicRenameType::NAME_MAX_LENGTH, example: 'trabajo'),
        ],
    ))]
    #[OA\Response(response: 200, description: 'Tema renombrado', content: new OA\JsonContent(ref: '#/components/schemas/Topic'))]
    #[OA\Response(response: 400, description: 'El cuerpo no es un objeto JSON', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 404, description: 'El tema no existe', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 409, description: 'Ya existe otro tema con ese nombre', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta `name`, está en blanco, es demasiado largo o hay campos no admitidos', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function rename(Topic $topic, Request $request): JsonResponse
    {
        $this->apiForm->submit(TopicRenameType::class, $topic, $request);

        $duplicate = $this->topicRepository->findOneByNameCaseInsensitive($topic->getName(), $topic->getId());
        if (null !== $duplicate) {
            throw ApiException::conflict(sprintf('Ya existe un tema llamado "%s". Prueba a fusionarlos en su lugar.', $duplicate->getName()));
        }

        $this->entityManager->flush();

        return new JsonResponse($this->topicPresenter->present($topic));
    }

    #[Route('/api/v1/topics/fusionar', name: 'api_topics_merge', methods: ['POST'])]
    #[OA\Post(summary: 'Fusionar temas', description: 'Asocia a `destination` todos los resúmenes de los temas de `origins` y elimina estos. No pide confirmación y no se puede deshacer: la app debe confirmarlo antes. Si `destination` aparece en `origins`, se ignora.')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['origins', 'destination'],
        properties: [
            new OA\Property(property: 'origins', type: 'array', items: new OA\Items(type: 'integer'), example: [4, 7]),
            new OA\Property(property: 'destination', type: 'integer', example: 2),
        ],
    ))]
    #[OA\Response(response: 200, description: 'Tema destino tras la fusión', content: new OA\JsonContent(ref: '#/components/schemas/Topic'))]
    #[OA\Response(response: 400, description: 'El cuerpo no es un objeto JSON', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta algún campo, algún tema no existe o no queda ningún origen distinto del destino', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function merge(Request $request): JsonResponse
    {
        $form = $this->apiForm->submit(TopicMergeType::class, null, $request);

        /** @var Topic $destination */
        $destination = $form->get('destination')->getData();

        /** @var list<Topic> $origins */
        $origins = array_values(array_filter(
            iterator_to_array($form->get('origins')->getData()),
            static fn (Topic $topic): bool => $topic !== $destination,
        ));

        if ([] === $origins) {
            throw ApiException::validationFailed('El campo "origins" debe incluir algún tema distinto de "destination".');
        }

        $this->topicMerger->merge($origins, $destination);

        return new JsonResponse($this->topicPresenter->present($destination));
    }
}
