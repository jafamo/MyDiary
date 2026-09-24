<?php

declare(strict_types=1);

namespace App;

/**
 * Zona horaria "de negocio" de toda la aplicación: días, calendarios, horas mostradas y cron.
 *
 * PHP corre en UTC (date_default_timezone) y las fechas se guardan en UTC; esta zona solo se
 * usa para interpretar/mostrar esas fechas en local. Fuente única: no repetir el literal.
 */
final class LocalTimezone
{
    public const NAME = 'Europe/Madrid';
}
