<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AudioRecording;
use App\Entity\AudioRecordingStatus;
use App\Entity\AudioSource;
use PHPUnit\Framework\TestCase;

class AudioRecordingTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $receivedAt = new \DateTimeImmutable('2026-08-04 10:00:00');

        $audioRecording = new AudioRecording();
        $audioRecording
            ->setTelegramMessageId('msg-1')
            ->setTelegramFileUniqueId('file-1')
            ->setFilePath('/data/audio/file-1.ogg')
            ->setReceivedAt($receivedAt)
            ->setDurationSeconds(42)
        ;

        self::assertSame('msg-1', $audioRecording->getTelegramMessageId());
        self::assertSame('file-1', $audioRecording->getTelegramFileUniqueId());
        self::assertSame('/data/audio/file-1.ogg', $audioRecording->getFilePath());
        self::assertSame($receivedAt, $audioRecording->getReceivedAt());
        self::assertSame(42, $audioRecording->getDurationSeconds());
    }

    public function testDefaultStatusIsPending(): void
    {
        $audioRecording = new AudioRecording();

        self::assertSame(AudioRecordingStatus::PENDING, $audioRecording->getStatus());
    }

    public function testErrorFieldsAreNullableAndSettable(): void
    {
        $audioRecording = new AudioRecording();

        self::assertNull($audioRecording->getErrorCode());
        self::assertNull($audioRecording->getErrorMessage());

        $audioRecording
            ->setStatus(AudioRecordingStatus::ERROR)
            ->setErrorCode('TIMEOUT')
            ->setErrorMessage('OLLAMA_UNREACHABLE')
        ;

        self::assertSame(AudioRecordingStatus::ERROR, $audioRecording->getStatus());
        self::assertSame('TIMEOUT', $audioRecording->getErrorCode());
        self::assertSame('OLLAMA_UNREACHABLE', $audioRecording->getErrorMessage());
    }

    public function testSourceDefaultsToTelegramAndTelegramIdsAreOptional(): void
    {
        $audioRecording = new AudioRecording();

        self::assertSame(AudioSource::TELEGRAM, $audioRecording->getSource());
        self::assertNull($audioRecording->getTelegramMessageId());
        self::assertNull($audioRecording->getTelegramFileUniqueId());
        self::assertNull($audioRecording->getContentHash());
    }

    public function testStorageKeyIsTheTelegramFileIdOrTheContentHash(): void
    {
        $fromTelegram = (new AudioRecording())->setTelegramFileUniqueId('file-1');
        $fromApp = (new AudioRecording())->setSource(AudioSource::APP)->setContentHash(str_repeat('a', 64));

        self::assertSame('file-1', $fromTelegram->getStorageKey());
        self::assertSame(AudioSource::APP, $fromApp->getSource());
        self::assertSame(str_repeat('a', 64), $fromApp->getStorageKey());
    }

    public function testStorageKeyFailsWithoutAnyIdentifier(): void
    {
        $this->expectException(\LogicException::class);

        (new AudioRecording())->getStorageKey();
    }
}
