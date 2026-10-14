<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\EventListener\PublicUrlProxyListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PublicUrlProxyListenerTest extends TestCase
{
    private const PROXY_IP = '192.168.4.1';

    public function testRequestFromTheProxyTakesThePublicUrl(): void
    {
        $request = $this->request(self::PROXY_IP);

        $this->dispatch(new PublicUrlProxyListener(self::PROXY_IP, 'https://diary.example.test'), $request);

        self::assertSame('https://diary.example.test', $request->getSchemeAndHttpHost());
        self::assertTrue($request->isSecure());
        self::assertSame(443, $request->getPort());
        self::assertSame('https://diary.example.test/login', $request->getUriForPath('/login'));
    }

    public function testExplicitPortIsKept(): void
    {
        $request = $this->request(self::PROXY_IP);

        $this->dispatch(new PublicUrlProxyListener(self::PROXY_IP, 'http://diary.example.test:8080'), $request);

        self::assertSame('http://diary.example.test:8080', $request->getSchemeAndHttpHost());
        self::assertFalse($request->isSecure());
    }

    public function testRequestFromAnotherIpIsUntouched(): void
    {
        $request = $this->request('192.168.4.37');

        $this->dispatch(new PublicUrlProxyListener(self::PROXY_IP, 'https://diary.example.test'), $request);

        self::assertSame('http://192.168.4.200', $request->getSchemeAndHttpHost());
    }

    public function testWithoutProxyConfiguredNothingChanges(): void
    {
        $request = $this->request(self::PROXY_IP);

        $this->dispatch(new PublicUrlProxyListener('', 'https://diary.example.test'), $request);

        self::assertSame('http://192.168.4.200', $request->getSchemeAndHttpHost());
    }

    public function testSubRequestsAreUntouched(): void
    {
        $request = $this->request(self::PROXY_IP);

        $this->dispatch(new PublicUrlProxyListener(self::PROXY_IP, 'https://diary.example.test'), $request, HttpKernelInterface::SUB_REQUEST);

        self::assertSame('http://192.168.4.200', $request->getSchemeAndHttpHost());
    }

    private function request(string $remoteAddr): Request
    {
        return Request::create('http://192.168.4.200/', server: ['REMOTE_ADDR' => $remoteAddr]);
    }

    private function dispatch(PublicUrlProxyListener $listener, Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): void
    {
        $listener(new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $type));
    }
}
