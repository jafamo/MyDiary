<?php

declare(strict_types=1);

namespace App\Service;

enum AudioRecordingReceiveResult
{
    case CREATED;
    case DUPLICATE_MESSAGE;
    case DUPLICATE_FILE;
    case RETRYING_AFTER_ERROR;
}
