<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Service\DateRange;
use App\Service\DiarioDashboardService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class DiarioController
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
        private readonly DiarioDashboardService $dashboardService,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/', name: 'app_diario', methods: ['GET'])]
    public function __invoke(): Response
    {
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);

        $entries = $this->audioRecordingRepository->findAllReceivedOn($today);
        $dailySummary = $this->dailySummaryRepository->findOneByDate($today);

        return new Response($this->twig->render('diario/index.html.twig', [
            'today' => $today,
            'entries' => $entries,
            'daily_summary' => $dailySummary,
            'streak' => $this->dashboardService->getCurrentStreak(),
            'best_streak' => $this->dashboardService->getBestStreak(),
            'week' => $this->dashboardService->getWeekTotalAndTrend(),
            'top_topic' => $this->dashboardService->getTopTopicOfMonth(),
        ]));
    }
}
