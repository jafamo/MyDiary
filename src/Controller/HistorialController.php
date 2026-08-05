<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AudioRecordingStatus;
use App\Repository\AudioRecordingRepository;
use App\Repository\DailySummaryRepository;
use App\Service\DateRange;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

class HistorialController
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly DailySummaryRepository $dailySummaryRepository,
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

        $firstOfMonth = (new \DateTimeImmutable())->setTimezone(new \DateTimeZone('Europe/Madrid'))->setDate($year, $month, 1)->setTime(0, 0, 0);
        $lastOfMonth = $firstOfMonth->modify('last day of this month');

        $leadingDays = ((int) $firstOfMonth->format('N')) - 1;
        $trailingDays = 7 - ((int) $lastOfMonth->format('N'));
        $gridStart = $firstOfMonth->modify(sprintf('-%d days', $leadingDays));
        $gridEnd = $lastOfMonth->modify(sprintf('+%d days', $trailingDays));

        $entryCounts = $this->audioRecordingRepository->countByDateInRange($gridStart, $gridEnd);
        $summaryDates = array_flip($this->dailySummaryRepository->findDatesWithSummaryInRange($gridStart, $gridEnd));

        $weeks = [];
        $week = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $key = $cursor->format('Y-m-d');
            $week[] = [
                'date' => $cursor,
                'day' => (int) $cursor->format('j'),
                'muted' => ((int) $cursor->format('n')) !== $month,
                'has_entries' => isset($entryCounts[$key]),
                'has_summary' => isset($summaryDates[$key]),
                'count' => $entryCounts[$key] ?? 0,
            ];
            if (7 === \count($week)) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->modify('+1 day');
        }

        $selectedDateParam = $request->query->get('date');
        $selectedDate = null;
        $selectedEntries = [];
        if (null !== $selectedDateParam) {
            $selectedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $selectedDateParam);
            if (false !== $selectedDate) {
                $selectedDate = $selectedDate->setTime(0, 0, 0);
                $selectedEntries = $this->audioRecordingRepository->findAllReceivedOn($selectedDate, $status);
            } else {
                $selectedDate = null;
            }
        }

        $previousMonth = $firstOfMonth->modify('-1 month');
        $nextMonth = $firstOfMonth->modify('+1 month');

        return new Response($this->twig->render('historial/index.html.twig', [
            'month_date' => $firstOfMonth,
            'weeks' => $weeks,
            'previous_month' => $previousMonth,
            'next_month' => $nextMonth,
            'selected_date' => $selectedDate,
            'selected_entries' => $selectedEntries,
            'status_filter' => $status,
        ]));
    }
}
