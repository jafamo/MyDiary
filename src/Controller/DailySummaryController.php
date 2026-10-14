<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\DailySummaryGenerationRepository;
use App\Service\DailySummaryGenerationRequester;
use App\Service\DateRange;
use App\Service\NoTranscriptionsToSummarizeException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DailySummaryController
{
    public const FLASH_NOTICE = 'summary_notice';

    public function __construct(
        private readonly DailySummaryGenerationRequester $dailySummaryGenerationRequester,
        private readonly DailySummaryGenerationRepository $dailySummaryGenerationRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/diario/resumen', name: 'app_daily_summary_generate', methods: ['POST'])]
    public function generate(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('daily_summary_generate', (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        try {
            $this->dailySummaryGenerationRequester->request(DateRange::nowInMadrid(), DailySummaryGenerationRequester::SOURCE_WEB);
        } catch (NoTranscriptionsToSummarizeException) {
            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add(self::FLASH_NOTICE, 'Hoy todavía no hay transcripciones que resumir.');
            }
        }

        return new RedirectResponse($request->headers->get('Referer', '/'));
    }

    /**
     * Estado de la generación de hoy, para que Diario se recargue sola al terminar.
     */
    #[Route('/diario/resumen/estado', name: 'app_daily_summary_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $generation = $this->dailySummaryGenerationRepository->findOneByDate(DateRange::nowInMadrid());

        return new JsonResponse([
            'status' => $generation?->effectiveStatus(new \DateTimeImmutable())->value,
        ]);
    }
}
