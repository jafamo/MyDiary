<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Controller\Api\ApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Da a todos los errores de las rutas `/api/` el formato JSON `{"code": ..., "message": ...}`.
 * El resto de rutas conserva las páginas de error de Symfony. Prioridad negativa para ejecutarse
 * después del listener que registra la excepción en el log.
 */
#[AsEventListener(priority: -64)]
class ApiExceptionListener
{
    private const DEFAULTS = [
        Response::HTTP_BAD_REQUEST => [ApiException::BAD_REQUEST, 'Petición incorrecta.'],
        Response::HTTP_UNAUTHORIZED => [ApiException::UNAUTHORIZED, 'Token ausente, inválido o caducado.'],
        Response::HTTP_FORBIDDEN => [ApiException::FORBIDDEN, 'No tienes permiso para esta operación.'],
        Response::HTTP_NOT_FOUND => [ApiException::NOT_FOUND, 'Recurso no encontrado.'],
        Response::HTTP_METHOD_NOT_ALLOWED => [ApiException::METHOD_NOT_ALLOWED, 'Método no permitido para esta ruta.'],
        Response::HTTP_CONFLICT => [ApiException::CONFLICT, 'La operación no es posible en el estado actual.'],
        Response::HTTP_UNPROCESSABLE_ENTITY => [ApiException::VALIDATION_FAILED, 'Los datos enviados no son válidos.'],
        Response::HTTP_TOO_MANY_REQUESTS => [ApiException::TOO_MANY_REQUESTS, 'Demasiadas peticiones. Inténtalo más tarde.'],
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ApiException) {
            $event->setResponse(new JsonResponse(
                ['code' => $throwable->getErrorCode(), 'message' => $throwable->getMessage()],
                $throwable->getStatusCode(),
                $throwable->getHeaders(),
            ));

            return;
        }

        $status = $throwable instanceof HttpExceptionInterface ? $throwable->getStatusCode() : Response::HTTP_INTERNAL_SERVER_ERROR;
        $headers = $throwable instanceof HttpExceptionInterface ? $throwable->getHeaders() : [];

        // Los mensajes de las excepciones de Symfony y de los errores inesperados no se exponen al cliente.
        [$code, $message] = self::DEFAULTS[$status] ?? [ApiException::INTERNAL_ERROR, 'Error interno del servidor.'];
        if (!isset(self::DEFAULTS[$status])) {
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $event->setResponse(new JsonResponse(['code' => $code, 'message' => $message], $status, $headers));
    }
}
