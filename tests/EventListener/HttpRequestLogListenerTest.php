<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\HttpRequestLogListener;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class HttpRequestLogListenerTest extends TestCase
{
    private TestHandler $handler;
    private HttpRequestLogListener $listener;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->listener = new HttpRequestLogListener(new Logger('http', [$this->handler]));
    }

    /**
     * @return iterable<string, array{int, Level}>
     */
    public static function statusLevels(): iterable
    {
        yield '200' => [200, Level::Info];
        yield '201' => [201, Level::Info];
        yield '302' => [302, Level::Info];
        yield '404' => [404, Level::Warning];
        yield '500' => [500, Level::Error];
    }

    #[DataProvider('statusLevels')]
    public function testLogsStatusWithLevelByCode(int $status, Level $expectedLevel): void
    {
        $request = Request::create('/telegram/webhook/abc', 'POST');
        $request->attributes->set('_route', 'telegram_webhook');

        ($this->listener)($this->terminateEvent($request, $status));

        $records = $this->handler->getRecords();
        self::assertCount(1, $records);
        self::assertSame($expectedLevel, $records[0]->level);
        self::assertSame(\sprintf('POST /telegram/webhook/abc %d', $status), $records[0]->message);
        self::assertSame('http.request', $records[0]->context['event']);
        self::assertSame($status, $records[0]->context['status']);
        self::assertSame('POST', $records[0]->context['method']);
        self::assertSame('telegram_webhook', $records[0]->context['route']);
        self::assertSame('/telegram/webhook/abc', $records[0]->context['path']);
        self::assertIsInt($records[0]->context['duration_ms']);
    }

    public function testIgnoresSymfonyInternalRoutes(): void
    {
        ($this->listener)($this->terminateEvent(Request::create('/_wdt/abc123'), 200));

        self::assertFalse($this->handler->hasRecords(Level::Debug));
    }

    private function terminateEvent(Request $request, int $status): TerminateEvent
    {
        return new TerminateEvent($this->createStub(HttpKernelInterface::class), $request, new Response('', $status));
    }
}
