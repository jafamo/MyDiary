<?php

declare(strict_types=1);

namespace App\Twig;

/**
 * Geometría del gráfico de tokens de la sección "Consumo IA" de Estadísticas. Es presentación
 * de la web: los datos los calcula EstadisticasService.
 */
final class TokensChartBuilder
{
    /**
     * Geometría del gráfico de barras apiladas (viewBox 640×220, área de dibujo x 44–628, y 16–192).
     *
     * @param list<array<string, mixed>> $days
     *
     * @return array{max: int, bars: list<array<string, mixed>>, labels: list<array{x: float, date: string}>}
     */
    public function build(array $days): array
    {
        $left = 44.0;
        $right = 628.0;
        $top = 16.0;
        $bottom = 192.0;

        $maxTotal = max([0, ...array_map(static fn (array $day) => ($day['promptTokens'] ?? 0) + ($day['completionTokens'] ?? 0), $days)]);
        $max = $this->niceMax($maxTotal);
        $scale = ($bottom - $top) / $max;

        $count = \count($days);
        $slot = ($right - $left) / max(1, $count);
        $width = max(1.0, $slot * 0.62);

        $bars = [];
        foreach ($days as $i => $day) {
            $x = $left + $i * $slot + ($slot - $width) / 2;
            $inHeight = ($day['promptTokens'] ?? 0) * $scale;
            $outHeight = ($day['completionTokens'] ?? 0) * $scale;
            $bars[] = [
                'date' => $day['date'],
                'x' => round($x, 2),
                'width' => round($width, 2),
                'inY' => round($bottom - $inHeight, 2),
                'inHeight' => round($inHeight, 2),
                'outY' => round($bottom - $inHeight - $outHeight, 2),
                'outHeight' => round($outHeight, 2),
                'promptTokens' => $day['promptTokens'],
                'completionTokens' => $day['completionTokens'],
            ];
        }

        $labels = [];
        foreach (array_unique([0, intdiv($count - 1, 2), $count - 1]) as $index) {
            if (isset($bars[$index])) {
                $labels[] = ['x' => $bars[$index]['x'] + $bars[$index]['width'] / 2, 'date' => $bars[$index]['date']];
            }
        }

        return ['max' => $max, 'bars' => $bars, 'labels' => $labels];
    }

    /**
     * Máximo "redondo" del eje (múltiplo de media potencia de 10) para que las marcas sean legibles.
     */
    private function niceMax(int $value): int
    {
        if ($value <= 0) {
            return 1000;
        }

        $magnitude = 10 ** (int) floor(log10($value));

        return (int) (ceil($value / $magnitude * 2) / 2 * $magnitude);
    }
}
