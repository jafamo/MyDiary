<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

class ApiTokenManager
{
    /** Un token deja de valer tras este número de días sin usarse. */
    public const INACTIVITY_DAYS = 90;

    /** `last_used_at` se reescribe como mucho con esta frecuencia, para no escribir en cada petición. */
    private const LAST_USED_REFRESH_SECONDS = 3600;

    public function __construct(
        private readonly ApiTokenRepository $apiTokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Emite un token nuevo para un dispositivo. El valor en claro solo existe en el resultado
     * de esta llamada: en BD queda su hash.
     *
     * @return array{token: string, apiToken: ApiToken}
     */
    public function issue(User $user, string $name, \DateTimeImmutable $now): array
    {
        $token = bin2hex(random_bytes(32));
        $apiToken = new ApiToken($user, self::hash($token), $name, $now);

        $this->entityManager->persist($apiToken);
        $this->entityManager->flush();

        return ['token' => $token, 'apiToken' => $apiToken];
    }

    /**
     * Devuelve el token si existe y no ha caducado, anotando su uso. Un token caducado se elimina.
     */
    public function findValid(string $token, \DateTimeImmutable $now): ?ApiToken
    {
        $apiToken = $this->apiTokenRepository->findOneByTokenHash(self::hash($token));

        if (null === $apiToken) {
            return null;
        }

        if ($this->expiresAt($apiToken) <= $now) {
            $this->revoke($apiToken);

            return null;
        }

        $lastUsedAt = $apiToken->getLastUsedAt();
        if (null === $lastUsedAt || $now->getTimestamp() - $lastUsedAt->getTimestamp() >= self::LAST_USED_REFRESH_SECONDS) {
            $apiToken->setLastUsedAt($now);
            $this->entityManager->flush();
        }

        return $apiToken;
    }

    public function revoke(ApiToken $apiToken): void
    {
        $this->entityManager->remove($apiToken);
        $this->entityManager->flush();
    }

    /**
     * Momento en que caduca si no se vuelve a usar.
     */
    public function expiresAt(ApiToken $apiToken): \DateTimeImmutable
    {
        return ($apiToken->getLastUsedAt() ?? $apiToken->getCreatedAt())->modify(sprintf('+%d days', self::INACTIVITY_DAYS));
    }

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
