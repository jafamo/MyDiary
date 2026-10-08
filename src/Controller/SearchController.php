<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SearchService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class SearchController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/busqueda', name: 'app_busqueda', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query->get('q', ''));

        if ('' === $query) {
            return new Response($this->twig->render('busqueda/index.html.twig', [
                'query' => '',
                'results' => [],
                'reminders' => [],
                'searched' => false,
            ]));
        }

        return new Response($this->twig->render('busqueda/index.html.twig', [
            ...$this->searchService->search($query),
            'query' => $query,
            'searched' => true,
        ]));
    }
}
