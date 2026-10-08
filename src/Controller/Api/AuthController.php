<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\ApiToken;
use App\Repository\UserRepository;
use App\Security\ApiTokenHandler;
use App\Service\ApiTokenManager;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Autenticación')]
class AuthController
{
    private const DEVICE_NAME_MAX_LENGTH = 100;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ApiTokenManager $apiTokenManager,
        private readonly RateLimiterFactoryInterface $apiLoginLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/v1/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Obtener un token',
        description: 'Comprueba usuario y contraseña y emite un token para el dispositivo. El token solo se devuelve en esta respuesta. Limitado a 5 intentos fallidos cada 15 minutos por IP y usuario.',
    )]
    #[Security(name: null)]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['username', 'password', 'device_name'],
        properties: [
            new OA\Property(property: 'username', type: 'string', example: 'jfarinos'),
            new OA\Property(property: 'password', type: 'string', format: 'password', example: 'secreto'),
            new OA\Property(property: 'device_name', type: 'string', maxLength: self::DEVICE_NAME_MAX_LENGTH, example: 'iPhone de Javier'),
        ],
    ))]
    #[OA\Response(response: 200, description: 'Token emitido', content: new OA\JsonContent(
        required: ['token', 'token_id', 'device_name', 'expires_at'],
        properties: [
            new OA\Property(property: 'token', type: 'string', description: 'Token en claro (64 caracteres hexadecimales). Enviarlo como `Authorization: Bearer <token>`.', example: '3f1c…'),
            new OA\Property(property: 'token_id', type: 'integer', example: 7),
            new OA\Property(property: 'device_name', type: 'string', example: 'iPhone de Javier'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', description: 'Caduca en esta fecha si no se usa antes; cada uso la renueva.'),
        ],
    ))]
    #[OA\Response(response: 400, description: 'El cuerpo no es JSON válido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 401, description: 'Usuario o contraseña incorrectos', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, description: 'Falta algún campo o no es válido', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 429, description: 'Demasiados intentos; ver cabecera `Retry-After`', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function login(Request $request): JsonResponse
    {
        ['username' => $username, 'password' => $password, 'device_name' => $deviceName] = $this->loginPayload($request);

        $limiter = $this->apiLoginLimiter->create($request->getClientIp().'|'.mb_strtolower($username));
        $limit = $limiter->consume();
        if (!$limit->isAccepted()) {
            $this->logger->warning('Login de la API bloqueado por exceso de intentos', [
                'event' => 'api.login_throttled',
                'api_username' => $username,
            ]);

            throw ApiException::tooManyRequests('Demasiados intentos de login. Inténtalo más tarde.', max(1, $limit->getRetryAfter()->getTimestamp() - time()));
        }

        $user = $this->userRepository->findOneByUsername($username);
        if (null === $user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->logger->warning('Login de la API fallido', [
                'event' => 'api.login_failed',
                'api_username' => $username,
            ]);

            throw ApiException::unauthorized('Usuario o contraseña incorrectos.');
        }

        $limiter->reset();

        ['token' => $token, 'apiToken' => $apiToken] = $this->apiTokenManager->issue($user, $deviceName, new \DateTimeImmutable());

        $this->logger->info('Login de la API correcto', [
            'event' => 'api.login_succeeded',
            'api_token_id' => $apiToken->getId(),
            'api_device_name' => $apiToken->getName(),
        ]);

        return new JsonResponse([
            'token' => $token,
            'token_id' => $apiToken->getId(),
            'device_name' => $apiToken->getName(),
            'expires_at' => ApiFormatter::instant($this->apiTokenManager->expiresAt($apiToken)),
        ]);
    }

    #[Route('/api/v1/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(summary: 'Revocar el token en uso', description: 'Elimina el token con el que se hace la petición. Los tokens de otros dispositivos no se ven afectados.')]
    #[OA\Response(response: 204, description: 'Token revocado')]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function logout(Request $request): Response
    {
        $this->apiTokenManager->revoke($this->currentToken($request));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/v1/me', name: 'api_me', methods: ['GET'])]
    #[OA\Get(summary: 'Usuario y token en uso')]
    #[OA\Response(response: 200, description: 'Usuario autenticado y datos de su token', content: new OA\JsonContent(
        required: ['username', 'token_id', 'device_name', 'created_at', 'last_used_at', 'expires_at'],
        properties: [
            new OA\Property(property: 'username', type: 'string', example: 'jfarinos'),
            new OA\Property(property: 'token_id', type: 'integer', example: 7),
            new OA\Property(property: 'device_name', type: 'string', example: 'iPhone de Javier'),
            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true, description: 'Se actualiza como mucho una vez por hora.'),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', description: '90 días después del último uso.'),
        ],
    ))]
    #[OA\Response(response: 401, description: 'Token ausente, inválido o caducado', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    public function me(Request $request): JsonResponse
    {
        $apiToken = $this->currentToken($request);

        return new JsonResponse([
            'username' => $apiToken->getUser()->getUserIdentifier(),
            'token_id' => $apiToken->getId(),
            'device_name' => $apiToken->getName(),
            'created_at' => ApiFormatter::instant($apiToken->getCreatedAt()),
            'last_used_at' => ApiFormatter::instant($apiToken->getLastUsedAt()),
            'expires_at' => ApiFormatter::instant($this->apiTokenManager->expiresAt($apiToken)),
        ]);
    }

    /**
     * @return array{username: string, password: string, device_name: string}
     */
    private function loginPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw ApiException::badRequest('El cuerpo de la petición debe ser un objeto JSON.');
        }

        $fields = [];
        foreach (['username', 'password', 'device_name'] as $field) {
            $value = $payload[$field] ?? null;
            if (!\is_string($value) || '' === trim($value)) {
                throw ApiException::validationFailed(sprintf('El campo "%s" es obligatorio.', $field));
            }
            $fields[$field] = $value;
        }

        $fields['username'] = trim($fields['username']);
        $fields['device_name'] = trim($fields['device_name']);
        if (mb_strlen($fields['device_name']) > self::DEVICE_NAME_MAX_LENGTH) {
            throw ApiException::validationFailed(sprintf('El campo "device_name" admite como máximo %d caracteres.', self::DEVICE_NAME_MAX_LENGTH));
        }

        return $fields;
    }

    private function currentToken(Request $request): ApiToken
    {
        $apiToken = $request->attributes->get(ApiTokenHandler::REQUEST_ATTRIBUTE);
        if (!$apiToken instanceof ApiToken) {
            throw ApiException::unauthorized('Token ausente, inválido o caducado.');
        }

        return $apiToken;
    }
}
