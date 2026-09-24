<?php

declare(strict_types=1);

namespace App\EventListener;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Escribe una línea por petición HTTP en el canal "http" con el código de la
 * respuesta ("status", numérico como en el access log de nginx), para poder
 * filtrar en Kibana tanto los errores como las respuestas correctas.
 * "kernel.terminate" solo se lanza para la petición principal.
 */
#[AsEventListener]
#[WithMonologChannel('http')]
class HttpRequestLogListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/_')) {
            return;
        }

        $status = $event->getResponse()->getStatusCode();
        $startedAt = (float) $request->server->get('REQUEST_TIME_FLOAT', microtime(true));

        $this->logger->log(
            match (true) {
                $status >= 500 => LogLevel::ERROR,
                $status >= 400 => LogLevel::WARNING,
                default => LogLevel::INFO,
            },
            sprintf('%s %s %d', $request->getMethod(), $path, $status),
            [
                'event' => 'http.request',
                'status' => $status,
                'method' => $request->getMethod(),
                'route' => $request->attributes->get('_route'),
                'path' => $path,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
        );
    }
}
