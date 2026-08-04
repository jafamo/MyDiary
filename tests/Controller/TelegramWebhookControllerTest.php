<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\AudioRecordingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramWebhookControllerTest extends WebTestCase
{
    private const WEBHOOK_TOKEN_PARAM = 'TELEGRAM_WEBHOOK_TOKEN';
    private const CHAT_ID_PARAM = 'TELEGRAM_AUTHORIZED_CHAT_ID';

    private EntityManagerInterface $entityManager;
    private AudioRecordingRepository $audioRecordingRepository;

    protected function tearDown(): void
    {
        if (isset($this->entityManager, $this->audioRecordingRepository)) {
            foreach (['ctrl-msg-1', 'ctrl-msg-2', 'ctrl-msg-3'] as $telegramMessageId) {
                $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId($telegramMessageId);

                if (null !== $audioRecording) {
                    $this->entityManager->remove($audioRecording);
                }
            }

            $this->entityManager->flush();
        }

        parent::tearDown();
    }

    public function testInvalidTokenReturns404(): void
    {
        $client = static::createClient();

        $client->request('POST', '/telegram/webhook/token-incorrecto', [], [], [], json_encode(['message' => []]));

        self::assertSame(404, $client->getResponse()->getStatusCode());
    }

    public function testUnauthorizedChatIsIgnored(): void
    {
        $client = static::createClient();
        $this->bootServices($client);

        $webhookToken = $_ENV[self::WEBHOOK_TOKEN_PARAM];

        $payload = [
            'message' => [
                'message_id' => 'ctrl-msg-1',
                'chat' => ['id' => 999999999],
                'voice' => ['file_id' => 'file-id-1', 'file_unique_id' => 'ctrl-file-1', 'duration' => 5],
            ],
        ];

        $client->request('POST', '/telegram/webhook/'.$webhookToken, [], [], [], json_encode($payload));

        self::assertSame(200, $client->getResponse()->getStatusCode());
        self::assertNull($this->audioRecordingRepository->findOneByTelegramMessageId('ctrl-msg-1'));
    }

    public function testNewAudioFromAuthorizedChatIsStored(): void
    {
        $client = static::createClient();
        $this->bootServices($client);

        $webhookToken = $_ENV[self::WEBHOOK_TOKEN_PARAM];
        $chatId = (int) $_ENV[self::CHAT_ID_PARAM];

        $mockHttpClient = new MockHttpClient(function (string $method, string $url) {
            if (str_contains($url, 'getFile')) {
                return new MockResponse('{"ok":true,"result":{"file_id":"file-id-2","file_unique_id":"ctrl-file-2","file_path":"voice/ctrl.ogg"}}');
            }

            if (str_contains($url, '/file/bot')) {
                return new MockResponse('fake-audio-bytes');
            }

            return new MockResponse('{"ok":true}');
        });

        self::getContainer()->set(HttpClientInterface::class, $mockHttpClient);

        $payload = [
            'message' => [
                'message_id' => 'ctrl-msg-2',
                'chat' => ['id' => $chatId],
                'voice' => ['file_id' => 'file-id-2', 'file_unique_id' => 'ctrl-file-2', 'duration' => 7],
            ],
        ];

        $client->request('POST', '/telegram/webhook/'.$webhookToken, [], [], [], json_encode($payload));

        self::assertSame(200, $client->getResponse()->getStatusCode());

        $audioRecording = $this->audioRecordingRepository->findOneByTelegramMessageId('ctrl-msg-2');

        self::assertNotNull($audioRecording);
        self::assertSame('ctrl-file-2', $audioRecording->getTelegramFileUniqueId());
        self::assertSame(7, $audioRecording->getDurationSeconds());

        if (file_exists($audioRecording->getFilePath())) {
            unlink($audioRecording->getFilePath());
        }
    }

    private function bootServices($client): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->audioRecordingRepository = self::getContainer()->get(AudioRecordingRepository::class);
    }
}
