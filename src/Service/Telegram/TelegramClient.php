<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramClient
{
    private const API_BASE_URL = 'https://api.telegram.org';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $botToken,
    ) {
    }

    public function sendMessage(int $chatId, string $text): void
    {
        $this->httpClient->request('POST', $this->apiUrl('sendMessage'), [
            'json' => [
                'chat_id' => $chatId,
                'text' => $text,
            ],
        ]);
    }

    /**
     * @return array{file_id: string, file_unique_id: string, file_path?: string, file_size?: int}
     */
    public function getFile(string $fileId): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl('getFile'), [
            'query' => ['file_id' => $fileId],
        ]);

        return $response->toArray()['result'];
    }

    /**
     * Descarga el fichero identificado por $filePath (el que devuelve getFile) y
     * lo guarda en $destinationPath. Devuelve la ruta local del fichero descargado.
     */
    public function downloadFile(string $filePath, string $destinationPath): string
    {
        $response = $this->httpClient->request(
            'GET',
            sprintf('%s/file/bot%s/%s', self::API_BASE_URL, $this->botToken, $filePath),
        );

        $directory = \dirname($destinationPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($destinationPath, $response->getContent());

        return $destinationPath;
    }

    private function apiUrl(string $method): string
    {
        return sprintf('%s/bot%s/%s', self::API_BASE_URL, $this->botToken, $method);
    }
}
