<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\FlattenedContextJsonFormatter;
use App\Logger\MessengerStatusProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MessengerStatusProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function messengerMessages(): iterable
    {
        $class = 'Symfony\\Component\\Console\\Messenger\\RunCommandMessage';

        yield 'received (plantilla)' => ['Received message {class}', 'received'];
        yield 'received' => ["Received message $class", 'received'];
        yield 'sent (plantilla)' => ['Sending message {class} with {alias} sender using {sender}', 'sent'];
        yield 'sent' => ["Sending message $class with async sender using Foo\\Sender", 'sent'];
        yield 'handled (plantilla)' => ['Message {class} handled by {handler}', 'handled'];
        yield 'handled' => ["Message $class handled by Foo\\Handler::__invoke", 'handled'];
        yield 'no_handler (plantilla)' => ['No handler for message {class}', 'no_handler'];
        yield 'acknowledged (plantilla)' => ['{class} was handled successfully (acknowledging to transport).', 'acknowledged'];
        yield 'acknowledged' => ["$class was handled successfully (acknowledging to transport).", 'acknowledged'];
        yield 'retry (plantilla)' => ['Error thrown while handling message {class}. Sending for retry #{retryCount} using {delay} ms delay. Error: "{error}"', 'retry'];
        yield 'retry' => ["Error thrown while handling message $class. Sending for retry #1 using 1000 ms delay. Error: \"boom\"", 'retry'];
        yield 'failed (plantilla)' => ['Error thrown while handling message {class}. Removing from transport after {retryCount} retries. Error: "{error}"', 'failed'];
        yield 'failed' => ["Error thrown while handling message $class. Removing from transport after 3 retries. Error: \"boom\"", 'failed'];
        yield 'rejected (plantilla)' => ['Rejected message {class} will be sent to the failure transport {transport}.', 'rejected'];
    }

    #[DataProvider('messengerMessages')]
    public function testAddsStatusToMessengerRecords(string $message, string $expectedStatus): void
    {
        $record = (new MessengerStatusProcessor())($this->record('messenger', $message));

        self::assertSame($expectedStatus, $record->extra['messenger_status'] ?? null);
    }

    public function testLeavesUnknownMessengerRecordsUntouched(): void
    {
        $record = (new MessengerStatusProcessor())($this->record('messenger', 'Stopping worker.'));

        self::assertArrayNotHasKey('messenger_status', $record->extra);
    }

    public function testLeavesOtherChannelsUntouched(): void
    {
        $record = (new MessengerStatusProcessor())($this->record('cache', 'Received message {class}'));

        self::assertArrayNotHasKey('messenger_status', $record->extra);
    }

    public function testStatusIsExposedAtRootLevelOfJsonLog(): void
    {
        $record = (new MessengerStatusProcessor())($this->record('messenger', 'Received message {class}'));

        $decoded = json_decode((new FlattenedContextJsonFormatter())->format($record), true);

        self::assertSame('received', $decoded['messenger_status']);
    }

    private function record(string $channel, string $message): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-09-24 12:00:00'),
            channel: $channel,
            level: Level::Info,
            message: $message,
            context: ['class' => 'Symfony\\Component\\Console\\Messenger\\RunCommandMessage'],
        );
    }
}
