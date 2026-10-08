<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Controller\Api\ApiException;
use App\EventListener\ApiExceptionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ApiExceptionListenerTest extends TestCase
{
    public function testApiExceptionKeepsItsCodeMessageAndHeaders(): void
    {
        $event = $this->handle('/api/v1/login', ApiException::tooManyRequests('Demasiados intentos.', 120));

        self::assertSame(429, $event->getResponse()?->getStatusCode());
        self::assertSame('120', $event->getResponse()->headers->get('Retry-After'));
        self::assertSame(['code' => 'too_many_requests', 'message' => 'Demasiados intentos.'], $this->body($event));
    }

    public function testHttpExceptionsGetAStableCodeAndAGenericMessage(): void
    {
        foreach ([
            [new NotFoundHttpException('No route found for "GET /api/v1/x"'), 404, 'not_found'],
            [new AccessDeniedHttpException('Access Denied.'), 403, 'forbidden'],
            [new ConflictHttpException('detalle'), 409, 'conflict'],
        ] as [$exception, $status, $code]) {
            $event = $this->handle('/api/v1/x', $exception);

            self::assertSame($status, $event->getResponse()?->getStatusCode());
            self::assertSame($code, $this->body($event)['code']);
            self::assertStringNotContainsString($exception->getMessage(), $this->body($event)['message']);
        }
    }

    public function testUnexpectedErrorHidesInternalDetails(): void
    {
        $event = $this->handle('/api/v1/me', new \RuntimeException('SQLSTATE[42P01]: tabla secreta'));

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame(['code' => 'internal_error', 'message' => 'Error interno del servidor.'], $this->body($event));
    }

    public function testUnmappedHttpStatusBecomesInternalError(): void
    {
        $event = $this->handle('/api/v1/me', new HttpException(503, 'Servicio caído'));

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame('internal_error', $this->body($event)['code']);
    }

    public function testNonApiRoutesAreLeftUntouched(): void
    {
        foreach (['/historial', '/doc/api', '/api', '/apis/v1'] as $path) {
            self::assertNull($this->handle($path, new NotFoundHttpException())->getResponse());
        }
    }

    private function handle(string $path, \Throwable $throwable): ExceptionEvent
    {
        $event = new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create($path), HttpKernelInterface::MAIN_REQUEST, $throwable);
        (new ApiExceptionListener())($event);

        return $event;
    }

    /**
     * @return array<string, string>
     */
    private function body(ExceptionEvent $event): array
    {
        return json_decode((string) $event->getResponse()?->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
