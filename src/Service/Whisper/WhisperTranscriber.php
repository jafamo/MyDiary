<?php

declare(strict_types=1);

namespace App\Service\Whisper;

use App\Contract\TranscriberInterface;
use App\Contract\TranscriptionException;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhisperTranscriber implements TranscriberInterface
{
    private const REQUEST_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openWebUiBaseUrl,
        private readonly string $openWebUiApiKey,
    ) {
    }

    public function transcribe(string $audioFilePath): string
    {
        try {
            $formData = new FormDataPart([
                'file' => DataPart::fromPath($audioFilePath),
                'model' => 'whisper-1',
            ]);

            $response = $this->httpClient->request('POST', rtrim($this->openWebUiBaseUrl, '/').'/api/v1/audio/transcriptions', [
                'timeout' => self::REQUEST_TIMEOUT_SECONDS,
                'headers' => array_merge($formData->getPreparedHeaders()->toArray(), [
                    'Authorization' => 'Bearer '.$this->openWebUiApiKey,
                ]),
                'body' => $formData->bodyToIterable(),
            ]);

            $data = $response->toArray();
        } catch (TransportExceptionInterface $exception) {
            throw new TranscriptionException(
                'TIMEOUT',
                'No se pudo contactar con el servicio de transcripción (Open WebUI): se agotó el tiempo de espera.',
                $exception,
            );
        } catch (HttpExceptionInterface $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            throw new TranscriptionException(
                (string) $statusCode,
                sprintf(
                    'El servicio de transcripción (Open WebUI) respondió con un error HTTP %d: %s.',
                    $statusCode,
                    $exception->getMessage(),
                ),
                $exception,
            );
        }

        return $data['text'] ?? '';
    }
}
