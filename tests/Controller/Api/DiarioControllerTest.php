<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;

class DiarioControllerTest extends ApiTestCase
{
    private string $token;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
        $this->today = DateRange::nowInMadrid()->format('Y-m-d');
    }

    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/diario');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testTodayWithAudiosAndSummary(): void
    {
        $transcribed = $this->createAudio('now', content: 'He ido al dentista', durationSeconds: 42);
        $pending = $this->createAudio('now');
        $failed = $this->createAudio('now', AudioRecordingStatus::ERROR);
        $topicName = $this->unique('salud');
        $summary = $this->createSummary($this->today, 'Día de dentista', [$topicName]);

        $this->api('GET', '/api/v1/diario', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame(['date', 'entries', 'summary', 'streak', 'week', 'top_topic'], array_keys($json));
        self::assertSame($this->today, $json['date']);

        $entries = array_column($json['entries'], null, 'id');
        self::assertArrayHasKey($transcribed->getId(), $entries);
        self::assertArrayHasKey($pending->getId(), $entries);
        self::assertArrayHasKey($failed->getId(), $entries);

        $entry = $entries[$transcribed->getId()];
        self::assertSame(['id', 'status', 'source', 'duration_seconds', 'received_at', 'error_code', 'error_message', 'transcription'], array_keys($entry));
        self::assertSame('TRANSCRIBED', $entry['status']);
        self::assertSame('app', $entry['source']);
        self::assertSame(42, $entry['duration_seconds']);
        self::assertNull($entry['error_code']);
        self::assertNull($entry['error_message']);
        self::assertSame(['id', 'content', 'edited_manually', 'model', 'processing_ms', 'created_at', 'updated_at'], array_keys($entry['transcription']));
        self::assertSame('He ido al dentista', $entry['transcription']['content']);
        self::assertFalse($entry['transcription']['edited_manually']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $entry['received_at']);

        self::assertSame('PENDING', $entries[$pending->getId()]['status']);
        self::assertNull($entries[$pending->getId()]['transcription']);

        self::assertSame('ERROR', $entries[$failed->getId()]['status']);
        self::assertSame('TIMEOUT', $entries[$failed->getId()]['error_code']);
        self::assertNotNull($entries[$failed->getId()]['error_message']);
        self::assertNull($entries[$failed->getId()]['transcription']);

        self::assertSame($summary->getId(), $json['summary']['id']);
        self::assertSame($this->today, $json['summary']['date']);
        self::assertSame('Día de dentista', $json['summary']['summary_text']);
        self::assertSame([['emoji' => '🦷', 'meaning' => 'Dentista']], $json['summary']['emoji_legend']);
        self::assertSame([$topicName], array_column($json['summary']['topics'], 'name'));

        self::assertSame(['current', 'best'], array_keys($json['streak']));
        self::assertGreaterThanOrEqual(1, $json['streak']['current']);
        self::assertGreaterThanOrEqual($json['streak']['current'], $json['streak']['best']);
        self::assertSame(['total', 'delta'], array_keys($json['week']));
        self::assertGreaterThanOrEqual(3, $json['week']['total']);
        self::assertSame(['name', 'count'], array_keys($json['top_topic']));
    }

    public function testInternalDataIsNeverExposed(): void
    {
        $this->createAudio('now', content: 'texto');
        $this->createSummary($this->today);

        $this->api('GET', '/api/v1/diario', $this->token);

        $body = (string) $this->client->getResponse()->getContent();
        foreach (['file_path', 'content_hash', 'embedding', 'telegram'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $body);
        }
    }

    public function testStatusFilter(): void
    {
        $transcribed = $this->createAudio('now', content: 'texto');
        $failed = $this->createAudio('now', AudioRecordingStatus::ERROR);

        $this->api('GET', '/api/v1/diario?status=ERROR', $this->token);

        self::assertResponseStatusCodeSame(200);
        $ids = $this->ids($this->json()['entries']);
        self::assertContains($failed->getId(), $ids);
        self::assertNotContains($transcribed->getId(), $ids);
    }

    public function testUnknownStatusIsRejected(): void
    {
        $this->api('GET', '/api/v1/diario?status=HECHO', $this->token);

        $this->assertApiError(422, 'validation_failed');
    }
}
