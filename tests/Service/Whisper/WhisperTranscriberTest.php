<?php

declare(strict_types=1);

namespace App\Tests\Service\Whisper;

use App\Contract\TranscriptionException;
use App\Service\Whisper\WhisperTranscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class WhisperTranscriberTest extends TestCase
{
    private string $audioFilePath;

    protected function setUp(): void
    {
        $this->audioFilePath = tempnam(sys_get_temp_dir(), 'whisper-test-');
        file_put_contents($this->audioFilePath, 'fake-audio-bytes');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->audioFilePath)) {
            unlink($this->audioFilePath);
        }
    }

    public function testTranscribeReturnsTextOnSuccess(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('{"text":"Hola mundo"}'));
        $transcriber = new WhisperTranscriber($mockClient, 'http://192.168.4.200:9006', 'api-key');

        $result = $transcriber->transcribe($this->audioFilePath);

        self::assertSame('Hola mundo', $result);
    }

    public function testTranscribeThrowsDescriptiveExceptionOnHttpError(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $transcriber = new WhisperTranscriber($mockClient, 'http://192.168.4.200:9006', 'api-key');

        try {
            $transcriber->transcribe($this->audioFilePath);
            self::fail('Expected TranscriptionException was not thrown.');
        } catch (TranscriptionException $exception) {
            self::assertSame('503', $exception->getErrorCode());
            self::assertStringContainsString('Open WebUI', $exception->getErrorMessage());
            self::assertStringContainsString('503', $exception->getErrorMessage());
        }
    }

    public function testTranscribeThrowsDescriptiveExceptionOnTimeout(): void
    {
        $mockClient = new MockHttpClient(function () {
            throw new TransportException('Connection timed out');
        });
        $transcriber = new WhisperTranscriber($mockClient, 'http://192.168.4.200:9006', 'api-key');

        try {
            $transcriber->transcribe($this->audioFilePath);
            self::fail('Expected TranscriptionException was not thrown.');
        } catch (TranscriptionException $exception) {
            self::assertSame('TIMEOUT', $exception->getErrorCode());
            self::assertStringContainsString('tiempo de espera', $exception->getErrorMessage());
        }
    }
}
