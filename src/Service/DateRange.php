<?php

declare(strict_types=1);

namespace App\Service;

final class DateRange
{
    private const TIMEZONE = 'Europe/Madrid';

    /**
     * Devuelve [inicio, fin) del día indicado en Europe/Madrid, convertido a UTC.
     *
     * La app corre con date_default_timezone = UTC (confirmado en runtime), y Doctrine
     * escribe el valor "wall-clock" del DateTimeImmutable tal cual, sin convertir de zona.
     * Por eso aquí se devuelve explícitamente en UTC: así el wall-clock coincide con el que
     * usan las columnas `receivedAt`/`createdAt`, evitando comparar horas de zonas distintas.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function dayBoundaries(\DateTimeImmutable $date): array
    {
        $utc = new \DateTimeZone('UTC');
        $localDate = $date->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $start = $localDate->setTime(0, 0, 0)->setTimezone($utc);
        $end = $localDate->setTime(0, 0, 0)->modify('+1 day')->setTimezone($utc);

        return [$start, $end];
    }

    /**
     * Devuelve [inicio, fin) de la semana (lunes-domingo) que contiene $date, en Europe/Madrid, convertido a UTC.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function weekBoundaries(\DateTimeImmutable $date): array
    {
        $utc = new \DateTimeZone('UTC');
        $local = $date->setTimezone(new \DateTimeZone(self::TIMEZONE));
        $dayOfWeek = (int) $local->format('N');
        $monday = $local->setTime(0, 0, 0)->modify(sprintf('-%d days', $dayOfWeek - 1));
        $nextMonday = $monday->modify('+7 days');

        return [$monday->setTimezone($utc), $nextMonday->setTimezone($utc)];
    }

    /**
     * Devuelve [inicio, fin) cubriendo desde el día $from hasta el día $to (ambos incluidos), en UTC.
     *
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    public static function boundariesForDates(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        [$start] = self::dayBoundaries($from);
        [, $end] = self::dayBoundaries($to);

        return [$start, $end];
    }

    /**
     * "Ahora" en Europe/Madrid — para lógica de calendario/racha independiente de la BD.
     */
    public static function nowInMadrid(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone(self::TIMEZONE));
    }
}
