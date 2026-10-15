<?php

declare(strict_types=1);

namespace App\Service\Telegram;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramClient
{
    private const API_BASE_URL = 'https://api.telegram.org';

    // Telegram rechaza mensajes de más de 4096 caracteres; se deja margen porque
    // cuenta algunos emojis como más de un carácter.
    private const MAX_MESSAGE_LENGTH = 4000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $botToken,
    ) {
    }

    /**
     * Los textos que superan el límite de Telegram se envían en varios mensajes, en orden.
     *
     * Con $replyToMessageId, el primer mensaje responde a ese mensaje del chat; si ya no
     * existe, se envía igualmente sin la respuesta.
     */
    public function sendMessage(int $chatId, string $text, ?int $replyToMessageId = null): void
    {
        foreach (self::splitText($text, self::MAX_MESSAGE_LENGTH) as $index => $part) {
            $payload = [
                'chat_id' => $chatId,
                'text' => $part,
            ];
            if (null !== $replyToMessageId && 0 === $index) {
                $payload['reply_parameters'] = [
                    'message_id' => $replyToMessageId,
                    'allow_sending_without_reply' => true,
                ];
            }

            $this->httpClient->request('POST', $this->apiUrl('sendMessage'), [
                'json' => $payload,
            ]);
        }
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

    /**
     * Divide el texto en partes de como máximo $maxLength caracteres, cortando preferentemente
     * entre párrafos, después en saltos de línea, después en espacios y, en último caso, por longitud.
     *
     * @return list<string>
     */
    private static function splitText(string $text, int $maxLength): array
    {
        $parts = [];

        while (mb_strlen($text) > $maxLength) {
            $chunk = mb_substr($text, 0, $maxLength);
            $cut = null;

            foreach (["\n\n", "\n", ' '] as $separator) {
                $position = mb_strrpos($chunk, $separator);

                if (false !== $position && $position > 0) {
                    $cut = $position;
                    break;
                }
            }

            if (null === $cut) {
                $parts[] = $chunk;
                $text = mb_substr($text, $maxLength);

                continue;
            }

            $parts[] = rtrim(mb_substr($text, 0, $cut));
            $text = ltrim(mb_substr($text, $cut));
        }

        $parts[] = $text;

        return $parts;
    }

    private function apiUrl(string $method): string
    {
        return sprintf('%s/bot%s/%s', self::API_BASE_URL, $this->botToken, $method);
    }
}
