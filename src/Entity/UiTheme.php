<?php

declare(strict_types=1);

namespace App\Entity;

enum UiTheme: string
{
    case MODERNO = 'moderno';
    case CUADERNO = 'cuaderno';

    public static function default(): self
    {
        return self::MODERNO;
    }

    public function label(): string
    {
        return match ($this) {
            self::MODERNO => 'Moderno',
            self::CUADERNO => 'Cuaderno',
        };
    }

    /**
     * Id del símbolo en templates/_partials/svg_sprite.html.twig.
     */
    public function icon(): string
    {
        return match ($this) {
            self::MODERNO => 'i-grid',
            self::CUADERNO => 'i-notebook',
        };
    }
}
