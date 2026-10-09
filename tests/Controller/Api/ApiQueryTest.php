<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Controller\Api\ApiException;
use App\Controller\Api\ApiQuery;
use App\Entity\AudioRecordingStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class ApiQueryTest extends TestCase
{
    public function testAbsentParametersTakeTheirDefaults(): void
    {
        $request = new Request();

        self::assertNull(ApiQuery::status($request));
        self::assertNull(ApiQuery::day($request, 'from'));
        self::assertNull(ApiQuery::month($request));
        self::assertSame(1, ApiQuery::page($request));
        self::assertSame(20, ApiQuery::perPage($request));
        self::assertSame('upcoming', ApiQuery::choice($request, 'scope', ['upcoming', 'history'], 'upcoming'));
        self::assertNull(ApiQuery::choice($request, 'scope', ['upcoming', 'history']));
        self::assertNull(ApiQuery::string($request, 'q'));
    }

    public function testBlankParametersCountAsAbsent(): void
    {
        $request = new Request(['status' => '', 'page' => '  ', 'q' => ' ']);

        self::assertNull(ApiQuery::status($request));
        self::assertSame(1, ApiQuery::page($request));
        self::assertNull(ApiQuery::string($request, 'q'));
    }

    public function testValidParametersAreParsed(): void
    {
        $request = new Request(['status' => 'ERROR', 'from' => '2026-10-05', 'month' => '2026-02', 'page' => '3', 'per_page' => '100', 'scope' => 'history', 'q' => ' dentista ']);

        self::assertSame(AudioRecordingStatus::ERROR, ApiQuery::status($request));
        self::assertSame('2026-10-05 00:00:00', ApiQuery::day($request, 'from')->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-01 00:00:00', ApiQuery::month($request)->format('Y-m-d H:i:s'));
        self::assertSame(3, ApiQuery::page($request));
        self::assertSame(100, ApiQuery::perPage($request));
        self::assertSame('history', ApiQuery::choice($request, 'scope', ['upcoming', 'history']));
        self::assertSame('dentista', ApiQuery::string($request, 'q'));
    }

    /**
     * @param array<string, mixed> $query
     */
    #[DataProvider('invalidParameters')]
    public function testInvalidParametersAreValidationErrors(array $query, callable $read): void
    {
        try {
            $read(new Request($query));
            self::fail('Se esperaba ApiException');
        } catch (ApiException $exception) {
            self::assertSame(422, $exception->getStatusCode());
            self::assertSame(ApiException::VALIDATION_FAILED, $exception->getErrorCode());
            self::assertNotSame('', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, callable}>
     */
    public static function invalidParameters(): iterable
    {
        yield 'estado desconocido' => [['status' => 'HECHO'], ApiQuery::status(...)];
        yield 'estado en minúsculas' => [['status' => 'error'], ApiQuery::status(...)];
        yield 'fecha imposible' => [['from' => '2026-02-30'], static fn (Request $r) => ApiQuery::day($r, 'from')];
        yield 'fecha con otro formato' => [['from' => '05/10/2026'], static fn (Request $r) => ApiQuery::day($r, 'from')];
        yield 'fecha sin ceros' => [['from' => '2026-1-5'], static fn (Request $r) => ApiQuery::day($r, 'from')];
        yield 'mes imposible' => [['month' => '2026-13'], ApiQuery::month(...)];
        yield 'mes con día' => [['month' => '2026-10-05'], ApiQuery::month(...)];
        yield 'página cero' => [['page' => '0'], ApiQuery::page(...)];
        yield 'página negativa' => [['page' => '-1'], ApiQuery::page(...)];
        yield 'página no numérica' => [['page' => 'dos'], ApiQuery::page(...)];
        yield 'página decimal' => [['page' => '1.5'], ApiQuery::page(...)];
        yield 'per_page cero' => [['per_page' => '0'], ApiQuery::perPage(...)];
        yield 'per_page sobre el máximo' => [['per_page' => '101'], ApiQuery::perPage(...)];
        yield 'valor fuera del enumerado' => [['scope' => 'todos'], static fn (Request $r) => ApiQuery::choice($r, 'scope', ['upcoming', 'history'])];
        yield 'parámetro repetido como lista' => [['q' => ['a', 'b']], static fn (Request $r) => ApiQuery::string($r, 'q')];
    }
}
