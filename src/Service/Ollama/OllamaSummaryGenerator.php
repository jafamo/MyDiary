<?php

declare(strict_types=1);

namespace App\Service\Ollama;

use App\Contract\SummaryGenerationException;
use App\Contract\SummaryGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaSummaryGenerator implements SummaryGeneratorInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 120;

    // El estilo del resumen vive en el fichero de prompt; el formato de salida se fija aquí
    // para que editar ese fichero no pueda romper el contrato que espera el código.
    private const OUTPUT_CONTRACT = <<<'PROMPT'
        Responde ÚNICAMENTE con un objeto JSON válido, sin texto adicional ni Markdown, con esta forma:
        {"summary": "💼 Primer párrafo...\n\n✅ Segundo párrafo...", "topics": ["tema concreto 1", "tema concreto 2"], "legend": [{"emoji": "💼", "meaning": "Trabajo"}, {"emoji": "✅", "meaning": "Pendientes"}]}
        Cada párrafo de "summary" DEBE empezar con un emoji elegido por ti según su contenido (los del ejemplo son solo ilustrativos); separa los párrafos con una línea en blanco.
        En "legend" incluye exactamente los emojis que has usado en "summary", uno por entrada, en el orden en que aparecen.
        En "meaning" pon una categoría general y corta ("Trabajo", "Familia", "Salud", "Pendientes"...), no el tema concreto del día.
        PROMPT;

    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'properties' => [
            'summary' => ['type' => 'string'],
            'topics' => ['type' => 'array', 'items' => ['type' => 'string']],
            'legend' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'emoji' => ['type' => 'string'],
                        'meaning' => ['type' => 'string'],
                    ],
                    'required' => ['emoji', 'meaning'],
                ],
            ],
        ],
        'required' => ['summary', 'topics', 'legend'],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $ollamaBaseUrl,
        private readonly string $ollamaModel,
        private readonly string $summaryPromptFile,
    ) {
    }

    public function generate(array $transcriptions): array
    {
        $systemPrompt = $this->loadStylePrompt()."\n\n".self::OUTPUT_CONTRACT;
        $userPrompt = implode("\n\n---\n\n", $transcriptions);

        try {
            $response = $this->httpClient->request('POST', rtrim($this->ollamaBaseUrl, '/').'/v1/chat/completions', [
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'json' => [
                    'model' => $this->ollamaModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'daily_summary',
                            'schema' => self::RESPONSE_SCHEMA,
                        ],
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

        if (isset($data['usage']['prompt_tokens'])) {
            $this->logger->info('Tokens de entrada del resumen diario', [
                'event' => 'daily_summary.prompt_tokens',
                'prompt_tokens' => (int) $data['usage']['prompt_tokens'],
                'transcription_count' => \count($transcriptions),
            ]);
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

        $summary = (string) $parsed['summary'];

        return [
            'summary' => $summary,
            'topics' => array_values(array_map('strval', $parsed['topics'])),
            'legend' => $this->sanitizeLegend($parsed['legend'] ?? null, $summary),
            'usage' => [
                'promptTokens' => isset($data['usage']['prompt_tokens']) ? (int) $data['usage']['prompt_tokens'] : null,
                'completionTokens' => isset($data['usage']['completion_tokens']) ? (int) $data['usage']['completion_tokens'] : null,
                'model' => \is_string($data['model'] ?? null) && '' !== $data['model'] ? $data['model'] : $this->ollamaModel,
            ],
        ];
    }

    private function loadStylePrompt(): string
    {
        $prompt = is_readable($this->summaryPromptFile) ? trim((string) file_get_contents($this->summaryPromptFile)) : '';

        if ('' === $prompt) {
            throw new SummaryGenerationException(
                'PROMPT_NOT_FOUND',
                sprintf('No se encontró o está vacío el fichero de prompt del resumen: %s.', $this->summaryPromptFile),
            );
        }

        return $prompt;
    }

    /**
     * La leyenda es accesoria: las entradas inválidas se descartan en vez de hacer fallar la generación.
     *
     * @return list<array{emoji: string, meaning: string}>
     */
    private function sanitizeLegend(mixed $legend, string $summary): array
    {
        if (!\is_array($legend)) {
            return [];
        }

        $sanitized = [];

        foreach ($legend as $entry) {
            if (!\is_array($entry) || !\is_string($entry['emoji'] ?? null) || !\is_string($entry['meaning'] ?? null)) {
                continue;
            }

            $emoji = trim($entry['emoji']);
            $meaning = trim($entry['meaning']);

            if ('' === $emoji || '' === $meaning || isset($sanitized[$emoji]) || !str_contains($summary, $emoji)) {
                continue;
            }

            $sanitized[$emoji] = ['emoji' => $emoji, 'meaning' => $meaning];
        }

        return array_values($sanitized);
    }
}
