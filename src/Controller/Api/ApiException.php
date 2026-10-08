<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Error de la API con su código estable. ApiExceptionListener lo convierte en la respuesta
 * JSON `{"code": ..., "message": ...}`.
 */
class ApiException extends HttpException
{
    public const BAD_REQUEST = 'bad_request';
    public const UNAUTHORIZED = 'unauthorized';
    public const FORBIDDEN = 'forbidden';
    public const NOT_FOUND = 'not_found';
    public const METHOD_NOT_ALLOWED = 'method_not_allowed';
    public const CONFLICT = 'conflict';
    public const VALIDATION_FAILED = 'validation_failed';
    public const TOO_MANY_REQUESTS = 'too_many_requests';
    public const INTERNAL_ERROR = 'internal_error';

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $statusCode,
        private readonly string $errorCode,
        string $message,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $message, null, $headers);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function badRequest(string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, self::BAD_REQUEST, $message);
    }

    public static function unauthorized(string $message): self
    {
        return new self(Response::HTTP_UNAUTHORIZED, self::UNAUTHORIZED, $message);
    }

    public static function validationFailed(string $message): self
    {
        return new self(Response::HTTP_UNPROCESSABLE_ENTITY, self::VALIDATION_FAILED, $message);
    }

    public static function tooManyRequests(string $message, int $retryAfterSeconds): self
    {
        return new self(Response::HTTP_TOO_MANY_REQUESTS, self::TOO_MANY_REQUESTS, $message, ['Retry-After' => (string) $retryAfterSeconds]);
    }
}
