<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\UiTheme;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ThemePreferenceController
{
    public const CSRF_TOKEN_ID = 'ui_theme';

    public function __construct(
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/preferencias/tema', name: 'app_theme_preference', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_token')))) {
            return new Response(status: Response::HTTP_FORBIDDEN);
        }

        $theme = UiTheme::tryFrom((string) $request->request->get('theme'));
        $user = $this->security->getUser();
        if (null === $theme || !$user instanceof User) {
            return new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->setUiTheme($theme);
        $this->entityManager->flush();

        $this->logger->info('Tema de la interfaz cambiado', [
            'event' => 'ui.theme_changed',
            'ui_theme' => $theme->value,
        ]);

        // Desde el selector con JS el tema ya está aplicado en la página: no hace falta redirigir.
        if (\in_array('application/json', $request->getAcceptableContentTypes(), true)) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        return new RedirectResponse($this->internalReferer($request));
    }

    /**
     * Ruta del `Referer` si apunta a esta misma aplicación; si no, Diario.
     */
    private function internalReferer(Request $request): string
    {
        $referer = (string) $request->headers->get('Referer');
        $parts = parse_url($referer);
        if (false === $parts || ($parts['host'] ?? null) !== $request->getHost()) {
            return '/';
        }

        $path = $parts['path'] ?? '/';

        return isset($parts['query']) ? $path.'?'.$parts['query'] : $path;
    }
}
