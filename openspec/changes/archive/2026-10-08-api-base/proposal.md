## Why

Es la fase 2 de `ROADMAP.md`: la app de iPhone necesita una API JSON, y antes de exponer datos (fases 3 en adelante) hace falta la base común: cómo se autentica la app, qué convenciones siguen todos los endpoints y cómo se documentan. Hoy solo existe la web con sesión, formulario y CSRF, que una app nativa no puede usar de forma razonable.

## What Changes

La web actual (vistas Twig, login por formulario, webhook de Telegram) no cambia.

- **Autenticación por token opaco** (decisión D1 del roadmap): nueva tabla `api_token` que guarda solo el hash SHA-256 del token, un token por dispositivo con nombre, `created_at` y `last_used_at`. Caduca tras **90 días sin uso**.
- **Firewall `api`** sin estado para `^/api/`, declarado antes de `main`, con el autenticador `access_token` de Symfony (`Authorization: Bearer <token>`). Sin CSRF ni sesión.
- **Endpoints nuevos** bajo `/api/v1`:
  - `POST /api/v1/login`: usuario + contraseña + nombre del dispositivo → devuelve el token (única vez que se ve en claro).
  - `POST /api/v1/logout`: revoca el token con el que se hace la petición.
  - `GET /api/v1/me`: usuario y datos del token en uso.
- **Límite de intentos en el login** (`symfony/rate-limiter`): 5 intentos fallidos cada 15 minutos por IP y usuario; después `429`.
- **Formato único de error JSON** (`code`, `message`) para cualquier respuesta de error bajo `/api/`: 400, 401, 403, 404, 405, 409, 422, 429 y 500.
- **Convenciones comunes** documentadas: prefijo `/api/v1`, instantes en ISO 8601 UTC, días como `AAAA-MM-DD` en `LocalTimezone`, paginación `page` / `per_page`.
- **Comandos de consola** `app:user:token:list <username>` y `app:user:token:revoke <id>`, en línea con «gestión de usuarios solo por consola».
- **Documentación OpenAPI con Swagger** (decisión D2: controladores planos + `nelmio/api-doc-bundle`): atributos OpenAPI junto a cada acción, Swagger UI en `/doc/api` (tras el login web) y esquema versionado en `doc/openapi.json`.
- **Colecciones de peticiones** versionadas: `doc/MyDiary.postman_collection.json` y `doc/api.http`, con el login guardando el token para el resto de peticiones.
- **Logs**: `HttpRequestLogListener` añade `api_token_id` en las peticiones autenticadas por token; eventos `api.login_succeeded`, `api.login_failed` y `api.login_throttled`. El token nunca se escribe en un log.

Dependencias nuevas: `nelmio/api-doc-bundle` y `symfony/rate-limiter`.

## Capabilities

### New Capabilities

- `api`: base de la API JSON (`/api/v1`): autenticación por token, login/logout/me, límite de intentos, formato de error, convenciones comunes, documentación OpenAPI y colecciones de peticiones. Las fases siguientes del roadmap añaden aquí sus endpoints.

### Modified Capabilities

- `data-model`: nueva entidad `ApiToken` (tabla `api_token`).
- `structured-logging`: el log por petición HTTP incluye `api_token_id` cuando la petición se autentica con token.

## Impact

- Código: `src/Entity/ApiToken.php`, `src/Repository/ApiTokenRepository.php`, `src/Service/ApiTokenManager.php`, `src/Security/` (handler del token y respuestas 401/403 en JSON), `src/Controller/Api/`, `src/EventListener/` (errores JSON), `src/Command/` (dos comandos), `HttpRequestLogListener`.
- Configuración: `config/packages/security.yaml` (firewall `api`, `access_control`), `rate_limiter.yaml`, `nelmio_api_doc.yaml`, rutas de Swagger.
- Base de datos: una migración (`api_token`). **Requiere `make migrate` en el despliegue.**
- Despliegue: dependencias nuevas, así que hace falta `make composer-install` en el servidor (`make deploy` no lo ejecuta).
- Documentación: `Especificaciones.md` (stack, API, modelo de datos), `AGENTS.md` (regla de documentar endpoints y mantener las colecciones), `ROADMAP.md` (fase 2 hecha, D1 y D2 decididas), `CHANGELOG.md`, `doc/`.
- Seguridad: se abre una superficie nueva (`/api/`). El login por API queda limitado por intentos; el de la web sigue sin `login_throttling` (fase 0 del roadmap, pendiente).
