<?php

declare(strict_types=1);

namespace App\Service\Ollama;

use App\Contract\SummaryGenerationException;
use App\Contract\SummaryGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaSummaryGenerator implements SummaryGeneratorInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 120;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        Eres un asistente que resume notas de voz personales transcritas de un día.
        Responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional ni markdown, con esta forma exacta:
        {"summary": "resumen breve del día en castellano", "topics": ["tema1", "tema2"]}
        PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ollamaBaseUrl,
        private readonly string $ollamaModel,
    ) {
    }

    public function generate(array $transcriptions): array
    {
        $userPrompt = implode("\n\n---\n\n", $transcriptions);

        try {
            $response = $this->httpClient->request('POST', rtrim($this->ollamaBaseUrl, '/').'/v1/chat/completions', [
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'json' => [
                    'model' => $this->ollamaModel,
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ],
            ]);

            $data = $response->toArray();
        } catch (TransportExceptionInterface $exception) {
            throw new SummaryGenerationException(
                'TIMEOUT',
                'No se pudo contactar con Ollama: se agotó el tiempo de espera.',
                $exception,
            );
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            throw new SummaryGenerationException(
                (string) $statusCode,
                sprintf('Ollama respondió con un error HTTP %d: %s.', $statusCode, $exception->getMessage()),
                $exception,
            );
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!\is_string($content)) {
            throw new SummaryGenerationException(
                'INVALID_RESPONSE',
                'Ollama devolvió una respuesta sin el contenido esperado.',
            );
        }

        $parsed = json_decode(trim($content), true);

        if (!\is_array($parsed) || !isset($parsed['summary'], $parsed['topics']) || !\is_array($parsed['topics'])) {
            throw new SummaryGenerationException(
                'INVALID_JSON',
                'Ollama no devolvió un JSON válido con "summary" y "topics".',
            );
        }

        return [
            'summary' => (string) $parsed['summary'],
            'topics' => array_values(array_map('strval', $parsed['topics'])),
        ];
    }
}
