<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SpanishDateExtension extends AbstractExtension
{
    private const WEEKDAYS = ['lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
    private const MONTHS = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    public function getFilters(): array
    {
        return [
            new TwigFilter('es_weekday_date', [$this, 'weekdayDate']),
            new TwigFilter('es_month_year', [$this, 'monthYear']),
            new TwigFilter('es_day_month', [$this, 'dayMonth']),
        ];
    }

    public function weekdayDate(\DateTimeInterface $date): string
    {
        $weekday = self::WEEKDAYS[((int) $date->format('N')) - 1];
        $month = self::MONTHS[((int) $date->format('n')) - 1];

        return ucfirst($weekday).', '.((int) $date->format('j')).' de '.$month;
    }

    public function monthYear(\DateTimeInterface $date): string
    {
        $month = self::MONTHS[((int) $date->format('n')) - 1];

        return ucfirst($month).' '.$date->format('Y');
    }

    public function dayMonth(\DateTimeInterface $date): string
    {
        $month = self::MONTHS[((int) $date->format('n')) - 1];

        return ((int) $date->format('j')).' '.mb_substr($month, 0, 3);
    }
}
