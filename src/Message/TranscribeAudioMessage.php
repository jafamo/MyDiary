<?php

declare(strict_types=1);

namespace App\Message;

final class TranscribeAudioMessage
{
    public function __construct(
        public readonly int $audioRecordingId,
    ) {
    }
}
