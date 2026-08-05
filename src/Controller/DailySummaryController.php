<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\DailySummaryService;
use App\Service\DateRange;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DailySummaryController
{
    public function __construct(
        private readonly DailySummaryService $dailySummaryService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/diario/resumen', name: 'app_daily_summary_generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('daily_summary_generate', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        $this->dailySummaryService->generateForDate(DateRange::nowInMadrid()->setTime(0, 0, 0), waitForPending: false);

        return new RedirectResponse($request->headers->get('Referer', '/'));
    }
}
