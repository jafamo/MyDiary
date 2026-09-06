<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\FlattenedContextJsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class FlattenedContextJsonFormatterTest extends TestCase
{
    public function testContextFieldsAreFlattenedToRootLevel(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-09-03 12:00:00'),
            channel: 'app',
            level: Level::Error,
            message: 'Fallo al generar el resumen diario',
            context: [
                'event' => 'daily_summary.generation_failed',
                'error_code' => 'TIMEOUT',
            ],
        );

        $formatter = new FlattenedContextJsonFormatter();
        $decoded = json_decode($formatter->format($record), true);

        self::assertArrayNotHasKey('context', $decoded);
        self::assertSame('daily_summary.generation_failed', $decoded['event']);
        self::assertSame('TIMEOUT', $decoded['error_code']);
        self::assertSame('Fallo al generar el resumen diario', $decoded['message']);
    }

    public function testReservedFieldsAreNotOverwrittenByContext(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable('2026-09-03 12:00:00'),
            channel: 'app',
            level: Level::Error,
            message: 'Mensaje original',
            context: [
                'message' => 'no debería sobreescribir',
            ],
        );

        $formatter = new FlattenedContextJsonFormatter();
        $decoded = json_decode($formatter->format($record), true);

        self::assertSame('Mensaje original', $decoded['message']);
    }
}
