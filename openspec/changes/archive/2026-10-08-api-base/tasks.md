## 1. Dependencias y configuración

- [x] 1.1 `composer require nelmio/api-doc-bundle symfony/rate-limiter` y revisar lo que añadan las recetas de Flex
- [x] 1.2 `config/packages/rate_limiter.yaml`: limitador `api_login` (ventana deslizante, 5 cada 15 min); en `when@test`, almacenamiento en memoria (`InMemoryStorage`)
- [x] 1.3 `config/packages/nelmio_api_doc.yaml`: título, descripción con las convenciones comunes, esquema de seguridad `Bearer`, área limitada a `^/api/v1`; rutas `/doc/api` y `/doc/api.json`

## 2. Modelo de datos

- [x] 2.1 Entidad `ApiToken` y `ApiTokenRepository` (`findOneByTokenHash`, `findByUser`)
- [x] 2.2 Migración de `api_token` (unique en `token_hash`, FK a `app_user` con `ON DELETE CASCADE`), aplicada en las BD local y de test
- [x] 2.3 Tests de la entidad y del repositorio

## 3. Tokens

- [x] 3.1 `ApiTokenManager`: `issue(User, string $name)` devuelve el token en claro y la entidad; `findValid(string $token, $now)` aplica la caducidad de 90 días, borra el caducado y actualiza `last_used_at` como mucho una vez por hora; `revoke(ApiToken)`; `expiresAt(ApiToken)`
- [x] 3.2 Tests de `ApiTokenManager`: emisión (solo se guarda el hash), token inexistente, caducado (se borra), renovación por uso, límite de una escritura por hora, revocación

## 4. Seguridad

- [x] 4.1 `App\Security\ApiTokenHandler` (`AccessTokenHandlerInterface`): valida el token y deja el `ApiToken` en el atributo `_api_token` de la petición
- [x] 4.2 Respuestas 401/403 en JSON: entry point y `failure_handler` con `WWW-Authenticate: Bearer`
- [x] 4.3 `security.yaml`: firewall `api` (`^/api/`, sin estado, `access_token`) antes de `main`; `access_control` con `^/api/v1/login` público

## 5. Errores

- [x] 5.1 `ApiException` (código, mensaje, estado) y `ApiExceptionListener`: JSON `code` / `message` solo para rutas `/api/`, mensaje genérico en 500
- [x] 5.2 Tests: 404 y 405 de la API en JSON, 500 sin detalle interno, ruta web inexistente sigue en HTML

## 6. Endpoints

- [x] 6.1 `Api\AuthController::login` (`POST /api/v1/login`): validación del cuerpo, comprobación de contraseña, limitador, emisión del token y logs `api.login_*`
- [x] 6.2 `Api\AuthController::logout` (`POST /api/v1/logout`): revoca el token de la petición, `204`
- [x] 6.3 `Api\AuthController::me` (`GET /api/v1/me`): usuario y datos del token, fechas en ISO 8601 UTC
- [x] 6.4 Atributos OpenAPI en las tres acciones (cuerpos, respuestas y errores)
- [x] 6.5 Tests funcionales: sin token, token inválido, token revocado, token caducado, sesión web sin token, login correcto e incorrecto, usuario inexistente, cuerpo inválido, límite de intentos (por usuario), logout sin afectar a otro dispositivo, `me`

## 7. Consola y logs

- [x] 7.1 `app:user:token:list <username>` y `app:user:token:revoke <id>`, con tests de `CommandTester`
- [x] 7.2 `HttpRequestLogListener`: campo `api_token_id` cuando la petición trae token; test del listener
- [x] 7.3 Tests de los logs del login (sin contraseña ni token en el contexto)

## 8. Documentación de la API

- [x] 8.1 Target `make openapi` y `doc/openapi.json` generado
- [x] 8.2 `doc/MyDiary.postman_collection.json` (v2.1): variables `base_url` y `token`, autenticación Bearer a nivel de colección, script del login que guarda el token
- [x] 8.3 `doc/api.http`: mismas tres peticiones, con variables y captura del token
- [x] 8.4 Tests guarda: `doc/openapi.json` coincide con el esquema generado; cada ruta `/api/v1/*` aparece en la colección Postman y en el `.http`
- [x] 8.5 Tests de `/doc/api` y `/doc/api.json`: con sesión `200`, sin sesión redirige a `/login`

## 9. Documentación del proyecto y cierre

- [x] 9.1 `Especificaciones.md`: stack (Nelmio, rate limiter), sección de la API (autenticación, convenciones, errores, documentación), tabla `api_token` y comandos de tokens
- [x] 9.2 `AGENTS.md`: regla de documentar cada endpoint (OpenAPI + ambas colecciones) y mención a los comandos `app:user:token:*`
- [x] 9.3 `ROADMAP.md`: fase 2 hecha, D1 y D2 decididas
- [x] 9.4 `README.md` / `doc/README_ES.md`: apartado breve de la API y dónde está su documentación
- [x] 9.5 `CHANGELOG.md` bajo `## [Sin publicar]`: Añadido, Migraciones y Despliegue (`make composer-install`, `make migrate`)
- [x] 9.6 `make cs-check`, `make phpstan` y `make test` en verde
