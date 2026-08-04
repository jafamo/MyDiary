<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\SetTelegramWebhookCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SetTelegramWebhookCommandTest extends TestCase
{
    public function testRegistersWebhookSuccessfully(): void
    {
        $requestedUrl = null;
        $requestedOptions = null;

        $mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestedUrl, &$requestedOptions) {
            $requestedUrl = $url;
            $requestedOptions = $options;

            return new MockResponse('{"ok":true,"result":true}');
        });

        $command = new SetTelegramWebhookCommand($mockClient, 'bot-token', 'webhook-token', 'https://diary.jfarinos.keenetic.pro');

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:telegram:set-webhook'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertSame('https://api.telegram.org/botbot-token/setWebhook', $requestedUrl);
        $body = json_decode($requestedOptions['body'], true);
        self::assertSame('https://diary.jfarinos.keenetic.pro/telegram/webhook/webhook-token', $body['url']);
    }

    public function testFailsWhenTelegramRejectsWebhook(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('{"ok":false,"description":"invalid url"}'));

        $command = new SetTelegramWebhookCommand($mockClient, 'bot-token', 'webhook-token', 'https://diary.jfarinos.keenetic.pro');

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('app:telegram:set-webhook'));
        $commandTester->execute([]);

        self::assertSame(1, $commandTester->getStatusCode());
    }
}
