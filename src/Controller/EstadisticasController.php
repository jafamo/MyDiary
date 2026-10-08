<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;
use App\Service\EstadisticasService;
use App\Twig\TokensChartBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class EstadisticasController
{
    public function __construct(
        private readonly EstadisticasService $estadisticasService,
        private readonly TokensChartBuilder $tokensChartBuilder,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/estadisticas', name: 'app_estadisticas', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $range = $request->query->get('range', '30');
        $status = AudioRecordingStatus::tryFrom((string) $request->query->get('status'));

        [$from, $to] = $this->estadisticasService->resolveRange($range, $request->query->get('from'), $request->query->get('to'), $today);

        $overview = $this->estadisticasService->overview($from, $to, $status);
        $series = $overview['series'];
        $remindersSeries = $overview['reminders_series'];
        unset($overview['series'], $overview['reminders_series']);

        $aiUsage = $this->estadisticasService->aiUsage($from, $to);

        return new Response($this->twig->render('estadisticas/index.html.twig', [
            ...$overview,
            'range' => $range,
            'status_filter' => $status,
            'from' => $from,
            'to' => $to,
            'series_json' => json_encode($series, \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            'reminders_series_json' => json_encode($remindersSeries, \JSON_HEX_TAG | \JSON_THROW_ON_ERROR),
            'ai_usage' => [...$aiUsage, 'chart' => $this->tokensChartBuilder->build($aiUsage['days'])],
        ]));
    }
}
