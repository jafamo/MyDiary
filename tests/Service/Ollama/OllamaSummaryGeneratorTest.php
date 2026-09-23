<?php

declare(strict_types=1);

namespace App\Tests\Service\Ollama;

use App\Contract\SummaryGenerationException;
use App\Service\Ollama\OllamaSummaryGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OllamaSummaryGeneratorTest extends TestCase
{
    private string $promptFile;

    protected function setUp(): void
    {
        $this->promptFile = sys_get_temp_dir().'/daily-summary-prompt-'.uniqid().'.md';
        file_put_contents($this->promptFile, 'Escribe un resumen descriptivo.');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->promptFile)) {
            unlink($this->promptFile);
        }
    }

    public function testGenerateReturnsSummaryTopicsAndLegendOnSuccess(): void
    {
        $mockClient = new MockHttpClient($this->ollamaResponse([
            'summary' => "💼 Un resumen de trabajo\n\n🎉 Y algo de ocio",
            'topics' => ['Informe de ventas', 'Cine'],
            'legend' => [['emoji' => '💼', 'meaning' => 'Trabajo'], ['emoji' => '🎉', 'meaning' => 'Ocio']],
        ]));

        $result = $this->createGenerator($mockClient)->generate(['transcripción 1', 'transcripción 2']);

        self::assertSame("💼 Un resumen de trabajo\n\n🎉 Y algo de ocio", $result['summary']);
        self::assertSame(['Informe de ventas', 'Cine'], $result['topics']);
        self::assertSame([['emoji' => '💼', 'meaning' => 'Trabajo'], ['emoji' => '🎉', 'meaning' => 'Ocio']], $result['legend']);
    }

    public function testSystemPromptContainsStyleFileAndOutputContract(): void
    {
        $payloads = [];
        $mockClient = $this->recordingClient($payloads);

        $this->createGenerator($mockClient)->generate(['transcripción 1']);

        $systemPrompt = $payloads[0]['messages'][0]['content'];
        self::assertSame('system', $payloads[0]['messages'][0]['role']);
        self::assertStringStartsWith('Escribe un resumen descriptivo.', $systemPrompt);
        self::assertStringContainsString('Responde ÚNICAMENTE con un objeto JSON', $systemPrompt);
        self::assertStringContainsString('"legend"', $systemPrompt);
    }

    public function testEditingPromptFileChangesNextRequest(): void
    {
        $payloads = [];
        $generator = $this->createGenerator($this->recordingClient($payloads));

        $generator->generate(['transcripción 1']);
        file_put_contents($this->promptFile, 'Estilo nuevo, muy detallado.');
        $generator->generate(['transcripción 1']);

        self::assertStringStartsWith('Escribe un resumen descriptivo.', $payloads[0]['messages'][0]['content']);
        self::assertStringStartsWith('Estilo nuevo, muy detallado.', $payloads[1]['messages'][0]['content']);
    }

    public function testRequestIncludesJsonSchemaResponseFormat(): void
    {
        $payloads = [];
        $this->createGenerator($this->recordingClient($payloads))->generate(['transcripción 1']);

        $responseFormat = $payloads[0]['response_format'];
        self::assertSame('json_schema', $responseFormat['type']);
        self::assertSame(['summary', 'topics', 'legend'], $responseFormat['json_schema']['schema']['required']);
    }

    public function testMissingPromptFileThrowsWithoutCallingOllama(): void
    {
        unlink($this->promptFile);
        $this->assertPromptNotFoundWithoutHttpRequest();
    }

    public function testEmptyPromptFileThrowsWithoutCallingOllama(): void
    {
        file_put_contents($this->promptFile, "  \n ");
        $this->assertPromptNotFoundWithoutHttpRequest();
    }

    public function testLegendDropsInvalidDuplicatedAndAbsentEmojis(): void
    {
        $mockClient = new MockHttpClient($this->ollamaResponse([
            'summary' => "💼 Trabajo\n\n✅ Pendientes",
            'topics' => [],
            'legend' => [
                ['emoji' => '💼', 'meaning' => 'Trabajo'],
                ['emoji' => '🏃', 'meaning' => 'Deporte'],
                ['emoji' => '💼', 'meaning' => 'Oficina'],
                ['emoji' => '✅'],
                'no es un objeto',
                ['emoji' => '✅', 'meaning' => 'Pendientes'],
            ],
        ]));

        $result = $this->createGenerator($mockClient)->generate(['transcripción 1']);

        self::assertSame([['emoji' => '💼', 'meaning' => 'Trabajo'], ['emoji' => '✅', 'meaning' => 'Pendientes']], $result['legend']);
    }

    public function testMissingOrInvalidLegendYieldsEmptyLegend(): void
    {
        $withoutLegend = $this->createGenerator(new MockHttpClient($this->ollamaResponse(['summary' => '💼 Resumen', 'topics' => []])))
            ->generate(['transcripción 1']);
        $invalidLegend = $this->createGenerator(new MockHttpClient($this->ollamaResponse(['summary' => '💼 Resumen', 'topics' => [], 'legend' => 'x'])))
            ->generate(['transcripción 1']);

        self::assertSame([], $withoutLegend['legend']);
        self::assertSame([], $invalidLegend['legend']);
    }

    public function testLogsPromptTokensWhenUsageIsPresent(): void
    {
        $logger = new class () extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };

        $body = json_encode([
            'choices' => [['message' => ['content' => json_encode(['summary' => 'Resumen', 'topics' => [], 'legend' => []])]]],
            'usage' => ['prompt_tokens' => 1234, 'completion_tokens' => 200],
        ]);

        $this->createGenerator(new MockHttpClient(new MockResponse($body)), $logger)->generate(['t1', 't2']);

        self::assertCount(1, $logger->records);
        self::assertSame('daily_summary.prompt_tokens', $logger->records[0]['context']['event']);
        self::assertSame(1234, $logger->records[0]['context']['prompt_tokens']);
        self::assertSame(2, $logger->records[0]['context']['transcription_count']);
    }

    public function testGenerateThrowsOnInvalidJsonContent(): void
    {
        $response = json_encode(['choices' => [['message' => ['content' => 'esto no es JSON']]]]);

        $generator = $this->createGenerator(new MockHttpClient(new MockResponse($response)));

        try {
            $generator->generate(['transcripción 1']);
            self::fail('Expected SummaryGenerationException was not thrown.');
        } catch (SummaryGenerationException $exception) {
            self::assertSame('INVALID_JSON', $exception->getErrorCode());
        }
    }

    public function testGenerateThrowsDescriptiveExceptionOnHttpError(): void
    {
        $generator = $this->createGenerator(new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503])));

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

        try {
            $this->createGenerator($mockClient)->generate(['transcripción 1']);
            self::fail('Expected SummaryGenerationException was not thrown.');
        } catch (SummaryGenerationException $exception) {
            self::assertSame('TIMEOUT', $exception->getErrorCode());
            self::assertStringContainsString('tiempo de espera', $exception->getErrorMessage());
        }
    }

    private function assertPromptNotFoundWithoutHttpRequest(): void
    {
        $mockClient = new MockHttpClient(function () {
            self::fail('No debe llamarse a Ollama sin fichero de prompt.');
        });

        try {
            $this->createGenerator($mockClient)->generate(['transcripción 1']);
            self::fail('Expected SummaryGenerationException was not thrown.');
        } catch (SummaryGenerationException $exception) {
            self::assertSame('PROMPT_NOT_FOUND', $exception->getErrorCode());
        }

        self::assertSame(0, $mockClient->getRequestsCount());
    }

    private function createGenerator(MockHttpClient $mockClient, ?LoggerInterface $logger = null): OllamaSummaryGenerator
    {
        return new OllamaSummaryGenerator($mockClient, $logger ?? new NullLogger(), 'http://192.168.4.200:11434', 'qwen2.5:14b', $this->promptFile);
    }

    /**
     * @param array<string, mixed> $content
     */
    private function ollamaResponse(array $content): MockResponse
    {
        return new MockResponse(json_encode(['choices' => [['message' => ['content' => json_encode($content)]]]]));
    }

    /**
     * @param list<array<string, mixed>> $payloads
     */
    private function recordingClient(array &$payloads): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) use (&$payloads): MockResponse {
            $payloads[] = json_decode($options['body'], true);

            return $this->ollamaResponse(['summary' => 'Resumen', 'topics' => [], 'legend' => []]);
        });
    }
}
