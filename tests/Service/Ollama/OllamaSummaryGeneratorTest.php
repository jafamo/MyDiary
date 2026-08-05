<?php

declare(strict_types=1);

namespace App\Tests\Service\Ollama;

use App\Contract\SummaryGenerationException;
use App\Service\Ollama\OllamaSummaryGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OllamaSummaryGeneratorTest extends TestCase
{
    public function testGenerateReturnsSummaryAndTopicsOnSuccess(): void
    {
        $content = json_encode(['summary' => 'Un resumen', 'topics' => ['Trabajo', 'Ocio']]);
        $response = json_encode(['choices' => [['message' => ['content' => $content]]]]);

        $mockClient = new MockHttpClient(new MockResponse($response));
        $generator = new OllamaSummaryGenerator($mockClient, 'http://192.168.4.200:11434', 'qwen2.5:14b');

        $result = $generator->generate(['transcripción 1', 'transcripción 2']);

        self::assertSame('Un resumen', $result['summary']);
        self::assertSame(['Trabajo', 'Ocio'], $result['topics']);
    }

    public function testGenerateThrowsOnInvalidJsonContent(): void
    {
        $response = json_encode(['choices' => [['message' => ['content' => 'esto no es JSON']]]]);

        $mockClient = new MockHttpClient(new MockResponse($response));
        $generator = new OllamaSummaryGenerator($mockClient, 'http://192.168.4.200:11434', 'qwen2.5:14b');

        $this->expectException(SummaryGenerationException::class);
        $generator->generate(['transcripción 1']);
    }

    public function testGenerateThrowsDescriptiveExceptionOnHttpError(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $generator = new OllamaSummaryGenerator($mockClient, 'http://192.168.4.200:11434', 'qwen2.5:14b');

        try {
            $generator->generate(['transcripción 1']);
            self::fail('Expected SummaryGenerationException was not thrown.');
        } catch (SummaryGenerationException $exception) {
            self::assertSame('503', $exception->getErrorCode());
            self::assertStringContainsString('Ollama', $exception->getErrorMessage());
        }
    }

    public function testGenerateThrowsDescriptiveExceptionOnTimeout(): void
    {
        $mockClient = new MockHttpClient(function () {
            throw new TransportException('Connection timed out');
        });
        $generator = new OllamaSummaryGenerator($mockClient, 'http://192.168.4.200:11434', 'qwen2.5:14b');

        try {
            $generator->generate(['transcripción 1']);
            self::fail('Expected SummaryGenerationException was not thrown.');
        } catch (SummaryGenerationException $exception) {
            self::assertSame('TIMEOUT', $exception->getErrorCode());
            self::assertStringContainsString('tiempo de espera', $exception->getErrorMessage());
        }
    }
}
