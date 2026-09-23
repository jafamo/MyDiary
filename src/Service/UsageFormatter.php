<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Formatea las métricas de consumo de IA igual en la web y en Telegram.
 */
class UsageFormatter
{
    public function duration(int $milliseconds): string
    {
        $seconds = (int) round($milliseconds / 1000);

        if ($seconds < 60) {
            return sprintf('%d s', $seconds);
        }

        return sprintf('%d min %02d s', intdiv($seconds, 60), $seconds % 60);
    }

    public function audioDuration(int $seconds): string
    {
        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    public function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }

    public function speed(int $audioSeconds, int $milliseconds): string
    {
        if ($milliseconds <= 0) {
            return '—';
        }

        return number_format($audioSeconds * 1000 / $milliseconds, 1, ',', '.').'×';
    }
}
