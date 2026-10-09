<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AudioRecording;

final class AudioUploadResult
{
    public function __construct(
        public readonly AudioRecording $audioRecording,
        public readonly AudioRecordingReceiveResult $result,
    ) {
    }
}
