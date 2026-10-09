<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\AudioRecordingStatus;
use App\Service\DateRange;

class EstadisticasControllerTest extends ApiTestCase
{
    private const CUSTOM = '?range=custom&from=2019-04-01&to=2019-04-07';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = $this->issueToken($this->createUser());
    }

    public function testRequiresToken(): void
    {
        $this->api('GET', '/api/v1/estadisticas');

        $this->assertApiError(401, 'unauthorized');
    }

    public function testDefaultRangeIsTheLastThirtyDays(): void
    {
        $this->api('GET', '/api/v1/estadisticas', $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        $today = DateRange::nowInMadrid();
        self::assertSame(
            ['range', 'from', 'to', 'series', 'reminders_series', 'total_audios', 'total_reminders_in_range', 'total_days', 'avg_audios_per_day', 'avg_duration_seconds', 'days_with_summary', 'status_counts', 'topic_frequency', 'current_streak', 'best_streak', 'record_day', 'previous_period_comparison', 'ai_usage'],
            array_keys($json),
        );
        self::assertSame('30', $json['range']);
        self::assertSame($today->format('Y-m-d'), $json['to']);
        self::assertSame($today->modify('-29 days')->format('Y-m-d'), $json['from']);
        self::assertSame(30, $json['total_days']);
        self::assertCount(30, $json['series']);
        self::assertCount(30, $json['reminders_series']);
        self::assertSame(['date', 'value'], array_keys($json['series'][0]));
        self::assertSame($json['to'], $json['series'][29]['date']);
    }

    public function testPreset(): void
    {
        $this->api('GET', '/api/v1/estadisticas?range=90', $this->token);

        $json = $this->json();
        self::assertSame('90', $json['range']);
        self::assertCount(90, $json['series']);
    }

    public function testCustomRange(): void
    {
        $topicName = $this->unique('viajes');
        $this->createAudio('2019-04-02 09:00:00', content: 'uno', durationSeconds: 20);
        $this->createAudio('2019-04-02 15:00:00', content: 'dos', durationSeconds: 40);
        $this->createAudio('2019-04-05 15:00:00', AudioRecordingStatus::ERROR, durationSeconds: 60);
        $this->createSummary('2019-04-02', 'Resumen', [$topicName]);
        $this->createReminder('2019-04-03', 'Algo');

        $this->api('GET', '/api/v1/estadisticas'.self::CUSTOM, $this->token);

        self::assertResponseStatusCodeSame(200);
        $json = $this->json();
        self::assertSame('custom', $json['range']);
        self::assertSame('2019-04-01', $json['from']);
        self::assertSame('2019-04-07', $json['to']);
        self::assertSame(7, $json['total_days']);
        self::assertSame(3, $json['total_audios']);
        self::assertSame([0, 2, 0, 0, 1, 0, 0], array_column($json['series'], 'value'));
        self::assertSame([0, 0, 1, 0, 0, 0, 0], array_column($json['reminders_series'], 'value'));
        self::assertSame(1, $json['total_reminders_in_range']);
        self::assertSame(40, $json['avg_duration_seconds']);
        self::assertSame(1, $json['days_with_summary']);
        self::assertSame(['PENDING' => 0, 'TRANSCRIBED' => 2, 'ERROR' => 1], $json['status_counts']);
        self::assertSame([['name' => $topicName, 'count' => 1]], $json['topic_frequency']);
        self::assertSame(['date' => '2019-04-02', 'value' => 2], $json['record_day']);
        self::assertSame(1, $json['best_streak']);
        self::assertSame(0, $json['current_streak']);
        self::assertSame(['available' => false, 'percentage' => null], $json['previous_period_comparison']);
        self::assertArrayNotHasKey('max_topic_count', $json);
    }

    public function testAiUsageUsesSnakeCaseKeys(): void
    {
        $this->createAudio('2019-04-02 09:00:00', content: 'uno', durationSeconds: 20);
        $this->createSummary('2019-04-02');

        $this->api('GET', '/api/v1/estadisticas'.self::CUSTOM, $this->token);

        $aiUsage = $this->json()['ai_usage'];
        self::assertSame(['totals', 'days'], array_keys($aiUsage));
        self::assertSame(
            ['prompt_tokens', 'completion_tokens', 'total_tokens', 'avg_tokens_per_summary', 'summaries_with_tokens', 'audio_seconds', 'whisper_ms', 'ollama_ms'],
            array_keys($aiUsage['totals']),
        );
        self::assertSame(100, $aiUsage['totals']['prompt_tokens']);
        self::assertSame(150, $aiUsage['totals']['total_tokens']);
        self::assertSame(1, $aiUsage['totals']['summaries_with_tokens']);
        self::assertSame(20, $aiUsage['totals']['audio_seconds']);
        self::assertSame(1200, $aiUsage['totals']['whisper_ms']);
        self::assertSame(900, $aiUsage['totals']['ollama_ms']);

        self::assertCount(7, $aiUsage['days']);
        self::assertSame(
            ['date', 'audios', 'audio_seconds', 'processing_ms', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'generation_ms'],
            array_keys($aiUsage['days'][1]),
        );
        self::assertSame('2019-04-02', $aiUsage['days'][1]['date']);
        self::assertSame(1, $aiUsage['days'][1]['audios']);
        self::assertSame(150, $aiUsage['days'][1]['total_tokens']);
    }

    public function testStatusFilterAppliesToAudioSeriesOnly(): void
    {
        $this->createAudio('2019-04-02 09:00:00', content: 'uno');
        $this->createAudio('2019-04-05 15:00:00', AudioRecordingStatus::ERROR);

        $this->api('GET', '/api/v1/estadisticas'.self::CUSTOM.'&status=ERROR', $this->token);

        $json = $this->json();
        self::assertSame(1, $json['total_audios']);
        self::assertSame([0, 0, 0, 0, 1, 0, 0], array_column($json['series'], 'value'));
        self::assertSame(['PENDING' => 0, 'TRANSCRIBED' => 1, 'ERROR' => 1], $json['status_counts']);
    }

    public function testInvalidRangesAreRejected(): void
    {
        $queries = [
            'range=7',
            'range=custom',
            'range=custom&from=2019-04-01',
            'range=custom&from=2019-04-07&to=2019-04-01',
            'range=custom&from=2019-02-30&to=2019-04-01',
            'status=HECHO',
        ];

        foreach ($queries as $query) {
            $this->api('GET', '/api/v1/estadisticas?'.$query, $this->token);

            $this->assertApiError(422, 'validation_failed');
        }
    }
}
