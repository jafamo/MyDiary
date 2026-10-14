<?php

declare(strict_types=1);

namespace App\Entity;

enum AudioSource: string
{
    case TELEGRAM = 'telegram';
    case APP = 'app';
}
