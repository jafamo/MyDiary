<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\TokensChartBuilder;
use PHPUnit\Framework\TestCase;

class TokensChartBuilderTest extends TestCase
{
    public function testEmptyRangeUsesDefaultMax(): void
    {
        $chart = (new TokensChartBuilder())->build([]);

        self::assertSame(['max' => 1000, 'bars' => [], 'labels' => []], $chart);
    }

    public function testSingleDayGeometry(): void
    {
        $chart = (new TokensChartBuilder())->build([$this->day('2026-10-01', 300, 150)]);

        self::assertSame(450, $chart['max']);
        self::assertCount(1, $chart['bars']);
        $bar = $chart['bars'][0];
        self::assertSame('2026-10-01', $bar['date']);
        self::assertSame(154.96, $bar['x']);
        self::assertSame(362.08, $bar['width']);
        self::assertSame(117.33, $bar['inHeight']);
        self::assertSame(74.67, $bar['inY']);
        self::assertSame(58.67, $bar['outHeight']);
        self::assertSame(16.0, $bar['outY']);
        self::assertSame(300, $bar['promptTokens']);
        self::assertSame(150, $bar['completionTokens']);
        self::assertSame([['x' => 336.0, 'date' => '2026-10-01']], $chart['labels']);
    }

    public function testMaxIsRoundedUpToHalfPowerOfTen(): void
    {
        $chart = (new TokensChartBuilder())->build([$this->day('2026-10-01', 1000, 234)]);

        self::assertSame(1500, $chart['max']);
    }

    public function testDaysWithoutTokensHaveEmptyBars(): void
    {
        $chart = (new TokensChartBuilder())->build([$this->day('2026-10-01', null, null), $this->day('2026-10-02', 200, 100)]);

        self::assertSame(0.0, $chart['bars'][0]['inHeight']);
        self::assertSame(0.0, $chart['bars'][0]['outHeight']);
        self::assertNull($chart['bars'][0]['promptTokens']);
        self::assertSame(300, $chart['max']);
    }

    public function testLabelsOnFirstMiddleAndLastDay(): void
    {
        $days = [];
        for ($i = 1; $i <= 5; ++$i) {
            $days[] = $this->day(sprintf('2026-10-%02d', $i), 10, 10);
        }

        $chart = (new TokensChartBuilder())->build($days);

        self::assertSame(['2026-10-01', '2026-10-03', '2026-10-05'], array_column($chart['labels'], 'date'));
    }

    /**
     * @return array<string, mixed>
     */
    private function day(string $date, ?int $promptTokens, ?int $completionTokens): array
    {
        return ['date' => $date, 'promptTokens' => $promptTokens, 'completionTokens' => $completionTokens];
    }
}
