<?php

declare(strict_types=1);

namespace App\Contract;

interface TranscriberInterface
{
    /**
     * @throws TranscriptionException si la transcripción falla
     */
    public function transcribe(string $audioFilePath): string;
}
