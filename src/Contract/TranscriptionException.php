<?php

declare(strict_types=1);

namespace App\Contract;

class TranscriptionException extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly string $errorMessage,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($errorMessage, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }
}
