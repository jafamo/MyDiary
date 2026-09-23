<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\UsageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UsageFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function durationProvider(): iterable
    {
        yield 'menos de un segundo' => [400, '0 s'];
        yield 'segundos redondeados' => [14200, '14 s'];
        yield 'justo por debajo del minuto' => [59400, '59 s'];
        yield 'redondeo que alcanza el minuto' => [59600, '1 min 00 s'];
        yield 'minutos y segundos' => [65000, '1 min 05 s'];
    }

    #[DataProvider('durationProvider')]
    public function testDuration(int $milliseconds, string $expected): void
    {
        self::assertSame($expected, (new UsageFormatter())->duration($milliseconds));
    }

    public function testAudioDuration(): void
    {
        $formatter = new UsageFormatter();

        self::assertSame('1:42', $formatter->audioDuration(102));
        self::assertSame('0:07', $formatter->audioDuration(7));
    }

    public function testNumberUsesDotAsThousandsSeparator(): void
    {
        $formatter = new UsageFormatter();

        self::assertSame('432', $formatter->number(432));
        self::assertSame('3.412', $formatter->number(3412));
        self::assertSame('1.250.000', $formatter->number(1250000));
    }

    public function testSpeedWithOneDecimal(): void
    {
        $formatter = new UsageFormatter();

        self::assertSame('7,3×', $formatter->speed(102, 14000));
        self::assertSame('1,0×', $formatter->speed(10, 10000));
    }

    public function testSpeedWithoutProcessingTime(): void
    {
        self::assertSame('—', (new UsageFormatter())->speed(102, 0));
    }
}
