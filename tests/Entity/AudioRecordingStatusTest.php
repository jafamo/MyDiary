<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\AudioRecordingStatus;
use PHPUnit\Framework\TestCase;

class AudioRecordingStatusTest extends TestCase
{
    public function testCasesHaveExpectedValues(): void
    {
        self::assertSame('PENDING', AudioRecordingStatus::PENDING->value);
        self::assertSame('TRANSCRIBED', AudioRecordingStatus::TRANSCRIBED->value);
        self::assertSame('ERROR', AudioRecordingStatus::ERROR->value);
    }

    public function testFromValue(): void
    {
        self::assertSame(AudioRecordingStatus::TRANSCRIBED, AudioRecordingStatus::from('TRANSCRIBED'));
    }
}
