<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\LocalTimezone;

/**
 * Formatos de fecha de la API: instantes en ISO 8601 UTC y días en la zona horaria de la aplicación.
 */
final class ApiFormatter
{
    public static function instant(?\DateTimeImmutable $instant): ?string
    {
        return $instant?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Fecha de calendario guardada sin hora (resúmenes, recordatorios): se devuelve tal cual, sin convertir zona.
     */
    public static function date(?\DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    public static function day(?\DateTimeImmutable $day): ?string
    {
        return $day?->setTimezone(new \DateTimeZone(LocalTimezone::NAME))->format('Y-m-d');
    }
}
