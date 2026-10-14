<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AudioRecordingStatus;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lectura y validación de los parámetros de consulta de la API. Un parámetro ausente toma su
 * valor por defecto; uno presente e inválido es un 422 (la web, en cambio, lo ignora).
 */
final class ApiQuery
{
    public const DEFAULT_PER_PAGE = 20;
    public const MAX_PER_PAGE = 100;

    public static function status(Request $request): ?AudioRecordingStatus
    {
        $value = self::string($request, 'status');
        if (null === $value) {
            return null;
        }

        return AudioRecordingStatus::tryFrom($value)
            ?? throw ApiException::validationFailed('El parámetro "status" debe ser PENDING, TRANSCRIBED o ERROR.');
    }

    /**
     * Día en formato `AAAA-MM-DD`, a medianoche. Null si el parámetro no viene.
     */
    public static function day(Request $request, string $name): ?\DateTimeImmutable
    {
        $value = self::string($request, $name);

        return null === $value ? null : self::parseDay($value, $name);
    }

    public static function parseDay(string $value, string $name): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (false === $date || $date->format('Y-m-d') !== $value) {
            throw ApiException::validationFailed(sprintf('"%s" debe ser una fecha válida con el formato AAAA-MM-DD.', $name));
        }

        return $date;
    }

    /**
     * Primer día del mes indicado como `AAAA-MM`. Null si el parámetro no viene.
     */
    public static function month(Request $request, string $name = 'month'): ?\DateTimeImmutable
    {
        $value = self::string($request, $name);
        if (null === $value) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m', $value);
        if (false === $date || $date->format('Y-m') !== $value) {
            throw ApiException::validationFailed(sprintf('El parámetro "%s" debe ser un mes con el formato AAAA-MM.', $name));
        }

        return $date;
    }

    public static function page(Request $request): int
    {
        return self::integer($request, 'page', 1, 1, null);
    }

    public static function perPage(Request $request): int
    {
        return self::integer($request, 'per_page', self::DEFAULT_PER_PAGE, 1, self::MAX_PER_PAGE);
    }

    /**
     * @param list<string> $choices
     */
    public static function choice(Request $request, string $name, array $choices, ?string $default = null): ?string
    {
        $value = self::string($request, $name);
        if (null === $value) {
            return $default;
        }
        if (!\in_array($value, $choices, true)) {
            throw ApiException::validationFailed(sprintf('El parámetro "%s" debe ser uno de: %s.', $name, implode(', ', $choices)));
        }

        return $value;
    }

    /**
     * Valor del parámetro sin espacios alrededor; null si no viene o viene vacío.
     */
    public static function string(Request $request, string $name): ?string
    {
        $value = $request->query->all()[$name] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw ApiException::validationFailed(sprintf('El parámetro "%s" debe ser un único valor.', $name));
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }

    private static function integer(Request $request, string $name, int $default, int $min, ?int $max): int
    {
        $value = self::string($request, $name);
        if (null === $value) {
            return $default;
        }

        if (1 !== preg_match('/^\d{1,9}$/', $value) || (int) $value < $min || (null !== $max && (int) $value > $max)) {
            throw ApiException::validationFailed(null === $max
                ? sprintf('El parámetro "%s" debe ser un entero mayor o igual que %d.', $name, $min)
                : sprintf('El parámetro "%s" debe ser un entero entre %d y %d.', $name, $min, $max));
        }

        return (int) $value;
    }
}
