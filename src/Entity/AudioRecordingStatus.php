<?php

declare(strict_types=1);

namespace App\Entity;

enum AudioRecordingStatus: string
{
    case PENDING = 'PENDING';
    case TRANSCRIBED = 'TRANSCRIBED';
    case ERROR = 'ERROR';
}
