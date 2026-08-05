<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AudioRecordingReceiveResult;
use App\Service\AudioRecordingService;
use App\Service\Telegram\TelegramClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookController
{
    private const MESSAGE_RECEIVED = 'Audio recibido ✅';
    private const MESSAGE_DUPLICATE = 'Ya tengo este audio 👍';
    private const MESSAGE_RETRYING = 'Reintentando transcripción 🔄';

    public function __construct(
        private readonly AudioRecordingService $audioRecordingService,
        private readonly TelegramClient $telegramClient,
        private readonly LoggerInterface $logger,
        private readonly string $webhookToken,
        private readonly string $authorizedChatId,
        private readonly string $audioStorageDir,
    ) {
    }

    #[Route('/telegram/webhook/{token}', name: 'telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request, string $token): Response
    {
        if (!hash_equals($this->webhookToken, $token)) {
            return new Response(status: Response::HTTP_NOT_FOUND);
        }

        $update = json_decode($request->getContent(), true);
        $message = $update['message'] ?? null;

        if (!\is_array($message)) {
            return new JsonResponse(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;

        if ((string) $chatId !== $this->authorizedChatId) {
            $this->logger->warning('Update de Telegram rechazado: chat no autorizado', [
                'event' => 'telegram.chat_rejected',
                'chat_id' => $chatId,
                'telegram_message_id' => $message['message_id'] ?? null,
            ]);

            return new JsonResponse(['ok' => true]);
        }

        $audio = $message['voice'] ?? $message['audio'] ?? null;

        if (!\is_array($audio)) {
            return new JsonResponse(['ok' => true]);
        }

        $telegramMessageId = (string) $message['message_id'];
        $telegramFileUniqueId = $audio['file_unique_id'];
        $durationSeconds = (int) ($audio['duration'] ?? 0);
        $fileId = $audio['file_id'];

        $result = $this->audioRecordingService->receive(
            $telegramMessageId,
            $telegramFileUniqueId,
            $durationSeconds,
            function () use ($fileId, $telegramFileUniqueId): string {
                $file = $this->telegramClient->getFile($fileId);
                $extension = pathinfo($file['file_path'] ?? '', PATHINFO_EXTENSION) ?: 'ogg';
                if ('oga' === $extension) {
                    $extension = 'ogg';
                }
                $destination = sprintf('%s/%s.%s', $this->audioStorageDir, $telegramFileUniqueId, $extension);

                return $this->telegramClient->downloadFile($file['file_path'], $destination);
            },
        );

        if (AudioRecordingReceiveResult::DUPLICATE_MESSAGE !== $result) {
            $this->telegramClient->sendMessage((int) $chatId, match ($result) {
                AudioRecordingReceiveResult::CREATED => self::MESSAGE_RECEIVED,
                AudioRecordingReceiveResult::DUPLICATE_FILE => self::MESSAGE_DUPLICATE,
                AudioRecordingReceiveResult::RETRYING_AFTER_ERROR => self::MESSAGE_RETRYING,
            });
        }

        return new JsonResponse(['ok' => true]);
    }
}
