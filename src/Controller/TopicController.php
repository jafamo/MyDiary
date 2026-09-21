<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Topic;
use App\Form\TopicMergeType;
use App\Form\TopicRenameType;
use App\Repository\TopicRepository;
use App\Service\TopicMerger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class TopicController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly FormFactoryInterface $formFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly TopicMerger $topicMerger,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/topics', name: 'app_topics', methods: ['GET'])]
    public function index(): Response
    {
        return new Response($this->twig->render('topics/index.html.twig', [
            'topics' => $this->topicRepository->findAllWithUsageCount(),
        ]));
    }

    #[Route('/topics/{topic}/renombrar', name: 'app_topics_rename', methods: ['POST'])]
    public function rename(Topic $topic, Request $request): Response
    {
        $form = $this->formFactory->create(TopicRenameType::class, $topic);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid() || '' === trim($topic->getName())) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $duplicate = $this->topicRepository->findOneByNameCaseInsensitive($topic->getName(), $topic->getId());
        if (null !== $duplicate) {
            return new Response($this->twig->render('topics/index.html.twig', [
                'topics' => $this->topicRepository->findAllWithUsageCount(),
                'rename_error' => sprintf('Ya existe un tema llamado "%s". Prueba a fusionarlos en su lugar.', $duplicate->getName()),
            ]), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return new RedirectResponse($request->headers->get('Referer', '/topics'));
    }

    #[Route('/topics/fusionar/confirmar', name: 'app_topics_merge_confirm', methods: ['POST'])]
    public function mergeConfirm(Request $request): Response
    {
        $form = $this->formFactory->create(TopicMergeType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $destination = $form->get('destination')->getData();

        /** @var list<Topic> $origins */
        $origins = array_values(array_filter(
            iterator_to_array($form->get('origins')->getData()),
            static fn (Topic $topic): bool => $topic !== $destination,
        ));

        if ([] === $origins || null === $destination) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new Response($this->twig->render('topics/confirmar_fusion.html.twig', [
            'origins' => $origins,
            'destination' => $destination,
            'affected_count' => $this->countDistinctDailySummaries($origins, $destination),
        ]));
    }

    #[Route('/topics/fusionar', name: 'app_topics_merge', methods: ['POST'])]
    public function merge(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('topic_merge_confirm', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        $destination = $this->topicRepository->find((int) $request->request->get('destination'));
        $originIds = array_map('intval', (array) $request->request->all('origins'));
        $origins = array_values(array_filter(array_map(
            fn (int $id): ?Topic => $this->topicRepository->find($id),
            $originIds,
        )));

        if (null === $destination || [] === $origins) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->topicMerger->merge($origins, $destination);

        return new RedirectResponse('/topics');
    }

    /**
     * @param list<Topic> $origins
     */
    private function countDistinctDailySummaries(array $origins, Topic $destination): int
    {
        $ids = [];
        foreach ([...$origins, $destination] as $topic) {
            foreach ($topic->getDailySummaries() as $dailySummary) {
                $ids[$dailySummary->getId()] = true;
            }
        }

        return \count($ids);
    }
}
