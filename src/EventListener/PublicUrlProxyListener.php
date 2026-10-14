<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * El proxy KeenDNS del router termina el HTTPS del dominio público y reenvía a nginx por HTTP con
 * el Host cambiado a la IP del servidor, sin cabeceras X-Forwarded-* en las que confiar. Para las
 * peticiones que llegan desde él, se restauran el esquema, el host y el puerto de APP_PUBLIC_URL
 * antes del router y del firewall, de modo que las redirecciones (p. ej. al login) usen el dominio.
 */
#[AsEventListener(priority: 1024)]
class PublicUrlProxyListener
{
    public function __construct(
        private readonly string $keenDnsProxyIp,
        private readonly string $publicUrl,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if ('' === $this->keenDnsProxyIp || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->server->get('REMOTE_ADDR') !== $this->keenDnsProxyIp) {
            return;
        }

        $url = parse_url($this->publicUrl);
        if (!\is_array($url) || !isset($url['host'])) {
            return;
        }

        $secure = 'https' === ($url['scheme'] ?? 'https');
        $host = $url['host'].(isset($url['port']) ? ':'.$url['port'] : '');

        $request->headers->set('HOST', $host);
        $request->server->set('HTTP_HOST', $host);
        $request->server->set('HTTPS', $secure ? 'on' : 'off');
        $request->server->set('SERVER_PORT', (string) ($url['port'] ?? ($secure ? 443 : 80)));
    }
}
