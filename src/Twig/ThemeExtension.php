<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ThemeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('ui_theme', [ThemeRuntime::class, 'currentTheme']),
            new TwigFunction('ui_themes', [ThemeRuntime::class, 'allThemes']),
        ];
    }
}
