<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\Presenter;

use App\Controller\Api\Presenter\AudioPresenter;
use App\Controller\Api\Presenter\ReminderPresenter;
use App\Controller\Api\Presenter\SummaryPresenter;
use App\Controller\Api\Presenter\TopicPresenter;
use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use App\Entity\DailySummary;
use App\Entity\Reminder;
use App\Entity\Topic;
use PHPUnit\Framework\TestCase;

class PresentersTest extends TestCase
{
    public function testAudioBriefHasOnlyThePublicFields(): void
    {
        $audioRecording = $this->telegramAudio();

        self::assertSame([
            'id' => null,
            'status' => 'PENDING',
            'source' => 'telegram',
            'duration_seconds' => 42,
            'received_at' => '2026-10-05T08:15:00Z',
        ], (new AudioPresenter())->brief($audioRecording));
    }

    public function testAudioDetailNeverExposesInternalData(): void
    {
        $telegram = $this->telegramAudio()->setStatus(AudioRecordingStatus::ERROR)->setErrorCode('TIMEOUT')->setErrorMessage('Sin respuesta');
        $app = (new AudioRecording())
            ->setSource(AudioSource::APP)
            ->setContentHash(str_repeat('a', 64))
            ->setFilePath('/data/audio/secreto.m4a')
            ->setReceivedAt(new \DateTimeImmutable('2026-10-05 08:15:00', new \DateTimeZone('UTC')))
            ->setDurationSeconds(5)
        ;

        $presenter = new AudioPresenter();
        $detail = $presenter->detail($telegram);

        self::assertSame(['id', 'status', 'source', 'duration_seconds', 'received_at', 'error_code', 'error_message', 'transcription'], array_keys($detail));
        self::assertSame('TIMEOUT', $detail['error_code']);
        self::assertSame('Sin respuesta', $detail['error_message']);
        self::assertNull($detail['transcription']);

        $json = json_encode([$detail, $presenter->detail($app)], \JSON_THROW_ON_ERROR);
        foreach (['msg-1', 'file-1', 'secreto', str_repeat('a', 64), 'file_path', 'content_hash', 'telegram_'] as $internal) {
            self::assertStringNotContainsString($internal, $json);
        }
    }

    public function testInstantsAreConvertedToUtc(): void
    {
        $audioRecording = $this->telegramAudio()->setReceivedAt(new \DateTimeImmutable('2026-10-05 10:15:00', new \DateTimeZone('Europe/Madrid')));

        self::assertSame('2026-10-05T08:15:00Z', (new AudioPresenter())->brief($audioRecording)['received_at']);
    }

    public function testSummary(): void
    {
        $dailySummary = (new DailySummary())
            ->setDate(new \DateTimeImmutable('2026-10-05'))
            ->setSummaryText('Texto')
            ->setGeneratedAt(new \DateTimeImmutable('2026-10-05 19:00:00', new \DateTimeZone('UTC')))
            ->setEmbedding(array_fill(0, 768, 0.1))
            ->addTopic((new Topic())->setName('trabajo'))
        ;

        $presented = (new SummaryPresenter(new TopicPresenter()))->present($dailySummary);

        self::assertSame([
            'id' => null,
            'date' => '2026-10-05',
            'summary_text' => 'Texto',
            'generated_at' => '2026-10-05T19:00:00Z',
            'emoji_legend' => null,
            'topics' => [['id' => null, 'name' => 'trabajo']],
            'model' => null,
            'prompt_tokens' => null,
            'completion_tokens' => null,
            'generation_ms' => null,
        ], $presented);
    }

    public function testReminderWithAndWithoutTime(): void
    {
        $presenter = new ReminderPresenter();
        $withTime = (new Reminder())->setDate(new \DateTimeImmutable('2026-10-05'))->setTime(new \DateTimeImmutable('1970-01-01 09:05:00'))->setText('Dentista');
        $allDay = (new Reminder())->setDate(new \DateTimeImmutable('2026-10-06'))->setText('Cumpleaños');

        $presented = $presenter->present($withTime);
        self::assertSame(['id', 'date', 'time', 'text', 'created_at', 'updated_at'], array_keys($presented));
        self::assertSame('2026-10-05', $presented['date']);
        self::assertSame('09:05', $presented['time']);
        self::assertSame('Dentista', $presented['text']);
        self::assertNull($presenter->present($allDay)['time']);
        self::assertSame(['Dentista', 'Cumpleaños'], array_column($presenter->presentAll([$withTime, $allDay]), 'text'));
    }

    private function telegramAudio(): AudioRecording
    {
        return (new AudioRecording())
            ->setTelegramMessageId('msg-1')
            ->setTelegramFileUniqueId('file-1')
            ->setFilePath('/data/audio/file-1.ogg')
            ->setReceivedAt(new \DateTimeImmutable('2026-10-05 08:15:00', new \DateTimeZone('UTC')))
            ->setDurationSeconds(42)
        ;
    }
}
