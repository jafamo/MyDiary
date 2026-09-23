<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AudioRecording;
use App\Entity\Transcription;
use PHPUnit\Framework\TestCase;

class TranscriptionTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $audioRecording = new AudioRecording();
        $createdAt = new \DateTimeImmutable('2026-08-04 10:00:00');
        $updatedAt = new \DateTimeImmutable('2026-08-04 10:05:00');

        $transcription = new Transcription();
        $transcription
            ->setAudioRecording($audioRecording)
            ->setContent('Hola mundo')
            ->setFilePath('/data/transcriptions/file-1.txt')
            ->setCreatedAt($createdAt)
            ->setUpdatedAt($updatedAt)
        ;

        self::assertSame($audioRecording, $transcription->getAudioRecording());
        self::assertSame('Hola mundo', $transcription->getContent());
        self::assertSame('/data/transcriptions/file-1.txt', $transcription->getFilePath());
        self::assertSame($createdAt, $transcription->getCreatedAt());
        self::assertSame($updatedAt, $transcription->getUpdatedAt());
    }

    public function testEditedManuallyDefaultsToFalse(): void
    {
        $transcription = new Transcription();

        self::assertFalse($transcription->isEditedManually());

        $transcription->setEditedManually(true);

        self::assertTrue($transcription->isEditedManually());
    }

    public function testUsageMetrics(): void
    {
        $transcription = new Transcription();

        self::assertNull($transcription->getProcessingMs());
        self::assertNull($transcription->getModel());

        $transcription->setProcessingMs(14200)->setModel('whisper-1');

        self::assertSame(14200, $transcription->getProcessingMs());
        self::assertSame('whisper-1', $transcription->getModel());
    }
}
