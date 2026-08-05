<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\AudioRecordingRepository;
use App\Repository\TopicRepository;

class DiarioDashboardService
{
    public function __construct(
        private readonly AudioRecordingRepository $audioRecordingRepository,
        private readonly TopicRepository $topicRepository,
    ) {
    }

    public function getCurrentStreak(): int
    {
        $dates = $this->audioRecordingRepository->findDistinctDatesWithAudio();

        if ([] === $dates) {
            return 0;
        }

        $today = DateRange::nowInMadrid()->setTime(0, 0, 0);
        $mostRecent = new \DateTimeImmutable($dates[0]);

        if ($mostRecent < $today->modify('-1 day')) {
            return 0;
        }

        $streak = 0;
        $cursor = $mostRecent;
        $dateSet = array_flip($dates);

        while (isset($dateSet[$cursor->format('Y-m-d')])) {
            ++$streak;
            $cursor = $cursor->modify('-1 day');
        }

        return $streak;
    }

    public function getBestStreak(): int
    {
        $dates = $this->audioRecordingRepository->findDistinctDatesWithAudio();

        if ([] === $dates) {
            return 0;
        }

        sort($dates);

        $best = 1;
        $current = 1;
        $previous = new \DateTimeImmutable($dates[0]);

        for ($i = 1; $i < \count($dates); ++$i) {
            $date = new \DateTimeImmutable($dates[$i]);
            $current = $date == $previous->modify('+1 day') ? $current + 1 : 1;
            $best = max($best, $current);
            $previous = $date;
        }

        return $best;
    }

    /**
     * @return array{total: int, delta: int}
     */
    public function getWeekTotalAndTrend(): array
    {
        $now = DateRange::nowInMadrid();
        [$thisWeekStart, $thisWeekEnd] = DateRange::weekBoundaries($now);
        [$lastWeekStart] = DateRange::weekBoundaries($now->modify('-7 days'));

        $total = $this->audioRecordingRepository->countReceivedBetween($thisWeekStart, $thisWeekEnd);
        $previousTotal = $this->audioRecordingRepository->countReceivedBetween($lastWeekStart, $thisWeekStart);

        return ['total' => $total, 'delta' => $total - $previousTotal];
    }

    /**
     * @return array{name: string, count: int}|null
     */
    public function getTopTopicOfMonth(): ?array
    {
        return $this->topicRepository->findTopTopicForMonth(DateRange::nowInMadrid());
    }
}
