<?php

declare(strict_types=1);

namespace App\Entity;

enum DailySummaryGenerationStatus: string
{
    case QUEUED = 'QUEUED';
    case RUNNING = 'RUNNING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';
}
