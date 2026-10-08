<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;
use App\Service\HistorialService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class HistorialController
{
    public function __construct(
        private readonly HistorialService $historialService,
        private readonly Environment $twig,
    ) {
    }

    #[Route('/historial', name: 'app_historial', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $now = DateRange::nowInMadrid();
        $year = (int) $request->query->get('year', $now->format('Y'));
        $month = (int) $request->query->get('month', $now->format('n'));
        $status = AudioRecordingStatus::tryFrom((string) $request->query->get('status'));

        return new Response($this->twig->render('historial/index.html.twig', [
            ...$this->historialService->month($now, $year, $month),
            ...$this->historialService->selectedDay($request->query->get('date'), $status),
            'status_filter' => $status,
        ]));
    }
}
