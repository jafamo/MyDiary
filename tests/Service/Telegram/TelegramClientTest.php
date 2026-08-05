<?php

declare(strict_types=1);

namespace App\Tests\Service\Telegram;

use App\Service\Telegram\TelegramClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TelegramClientTest extends TestCase
{
    public function testSendMessagePostsToTelegramApi(): void
    {
        $requestedUrl = null;
        $requestedOptions = null;

        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedOptions) {
            $requestedUrl = $url;
            $requestedOptions = $options;

            return new MockResponse('{"ok":true}');
        });

        $client = new TelegramClient($mockClient, 'test-token');
        $client->sendMessage(12345, 'Audio recibido ✅');

        self::assertSame('https://api.telegram.org/bottest-token/sendMessage', $requestedUrl);
        self::assertStringContainsString('"chat_id":12345', $requestedOptions['body']);
        self::assertStringContainsString('Audio recibido', $requestedOptions['body']);
    }

    public function testGetFileReturnsResultArray(): void
    {
        $mockClient = new MockHttpClient(
            new MockResponse('{"ok":true,"result":{"file_id":"abc","file_unique_id":"unique-1","file_path":"voice/file.ogg"}}'),
        );

        $client = new TelegramClient($mockClient, 'test-token');
        $file = $client->getFile('abc');

        self::assertSame('unique-1', $file['file_unique_id']);
        self::assertSame('voice/file.ogg', $file['file_path']);
    }

    public function testDownloadFileWritesContentToDestination(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('binary-audio-content'));
        $client = new TelegramClient($mockClient, 'test-token');

        $destination = sys_get_temp_dir().'/telegram-client-test-'.uniqid().'/file.ogg';

        $result = $client->downloadFile('voice/file.ogg', $destination);

        self::assertSame($destination, $result);
        self::assertFileExists($destination);
        self::assertSame('binary-audio-content', file_get_contents($destination));

        unlink($destination);
        rmdir(\dirname($destination));
    }
}
