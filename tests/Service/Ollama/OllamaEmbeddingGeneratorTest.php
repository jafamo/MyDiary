<?php

declare(strict_types=1);

namespace App\Tests\Service\Ollama;

use App\Contract\EmbeddingGenerationException;
use App\Service\Ollama\OllamaEmbeddingGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OllamaEmbeddingGeneratorTest extends TestCase
{
    public function testGenerateReturnsEmbeddingOnSuccess(): void
    {
        $response = json_encode(['embedding' => [0.1, 0.2, 0.3]]);

        $mockClient = new MockHttpClient(new MockResponse($response));
        $generator = new OllamaEmbeddingGenerator($mockClient, 'http://192.168.4.200:11434', 'nomic-embed-text');

        $result = $generator->generate('texto de prueba');

        self::assertSame([0.1, 0.2, 0.3], $result);
    }

    public function testGenerateThrowsOnMissingEmbeddingInResponse(): void
    {
        $response = json_encode(['ok' => true]);

        $mockClient = new MockHttpClient(new MockResponse($response));
        $generator = new OllamaEmbeddingGenerator($mockClient, 'http://192.168.4.200:11434', 'nomic-embed-text');

        $this->expectException(EmbeddingGenerationException::class);
        $generator->generate('texto de prueba');
    }

    public function testGenerateThrowsDescriptiveExceptionOnHttpError(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $generator = new OllamaEmbeddingGenerator($mockClient, 'http://192.168.4.200:11434', 'nomic-embed-text');

        try {
            $generator->generate('texto de prueba');
            self::fail('Expected EmbeddingGenerationException was not thrown.');
        } catch (EmbeddingGenerationException $exception) {
            self::assertSame('503', $exception->getErrorCode());
            self::assertStringContainsString('Ollama', $exception->getErrorMessage());
        }
    }

    public function testGenerateThrowsDescriptiveExceptionOnTimeout(): void
    {
        $mockClient = new MockHttpClient(function () {
            throw new TransportException('Connection timed out');
        });
        $generator = new OllamaEmbeddingGenerator($mockClient, 'http://192.168.4.200:11434', 'nomic-embed-text');

        try {
            $generator->generate('texto de prueba');
            self::fail('Expected EmbeddingGenerationException was not thrown.');
        } catch (EmbeddingGenerationException $exception) {
            self::assertSame('TIMEOUT', $exception->getErrorCode());
            self::assertStringContainsString('tiempo de espera', $exception->getErrorMessage());
        }
    }
}
