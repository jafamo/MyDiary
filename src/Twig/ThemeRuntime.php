<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\UiTheme;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\RuntimeExtensionInterface;

class ThemeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * Tema del usuario con sesión; sin sesión (login), el tema por defecto.
     */
    public function currentTheme(): UiTheme
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getUiTheme() : UiTheme::default();
    }

    /**
     * @return list<UiTheme>
     */
    public function allThemes(): array
    {
        return UiTheme::cases();
    }
}
