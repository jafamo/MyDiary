<?php

declare(strict_types=1);

namespace App\Security;

use App\Service\ApiTokenManager;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resuelve el token `Bearer` de la API a su usuario y deja el token en la petición
 * (atributos `_api_token` y `_api_token_id`) para los controladores y el log por petición.
 */
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public const REQUEST_ATTRIBUTE = '_api_token';
    public const REQUEST_ATTRIBUTE_ID = '_api_token_id';

    public function __construct(
        private readonly ApiTokenManager $apiTokenManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $apiToken = $this->apiTokenManager->findValid($accessToken, new \DateTimeImmutable());

        if (null === $apiToken) {
            throw new BadCredentialsException('Invalid API token.');
        }

        $request = $this->requestStack->getMainRequest();
        $request?->attributes->set(self::REQUEST_ATTRIBUTE, $apiToken);
        $request?->attributes->set(self::REQUEST_ATTRIBUTE_ID, $apiToken->getId());

        $user = $apiToken->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
