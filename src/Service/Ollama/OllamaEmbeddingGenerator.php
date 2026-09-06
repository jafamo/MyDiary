<?php

declare(strict_types=1);

namespace App\Service\Ollama;

use App\Contract\EmbeddingGenerationException;
use App\Contract\EmbeddingGeneratorInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaEmbeddingGenerator implements EmbeddingGeneratorInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $ollamaBaseUrl,
        private readonly string $ollamaEmbeddingModel,
    ) {
    }

    public function generate(string $text): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->ollamaBaseUrl, '/').'/api/embeddings', [
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'json' => [
                    'model' => $this->ollamaEmbeddingModel,
                    'prompt' => $text,
                ],
            ]);

            $data = $response->toArray();
        } catch (TransportExceptionInterface $exception) {
            throw new EmbeddingGenerationException(
                'TIMEOUT',
                'No se pudo contactar con Ollama: se agotó el tiempo de espera.',
                $exception,
            );
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            throw new EmbeddingGenerationException(
                (string) $statusCode,
                sprintf('Ollama respondió con un error HTTP %d: %s.', $statusCode, $exception->getMessage()),
                $exception,
            );
        }

        $embedding = $data['embedding'] ?? null;

        if (!\is_array($embedding) || [] === $embedding) {
            throw new EmbeddingGenerationException(
                'INVALID_RESPONSE',
                'Ollama devolvió una respuesta sin el embedding esperado.',
            );
        }

        return array_values(array_map('floatval', $embedding));
    }
}
