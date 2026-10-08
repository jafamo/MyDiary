## Context

La aplicación tiene un único firewall `main` (sesión + `form_login` + CSRF) y todos los controladores devuelven Twig. La fase 1 dejó la lógica de las vistas en servicios reutilizables. Esta fase añade la segunda entrada: una API JSON para la app de iPhone, de un solo usuario.

Restricciones de `AGENTS.md` que aplican: sin CRUDs genéricos, sin capas ni CQRS, interfaces solo con razón real, gestión de usuarios solo por consola, logs con campos planos y prefijados, y tests que no dependan del `.env` local.

Decisiones tomadas por el usuario antes de esta propuesta: token opaco con caducidad de 90 días por inactividad (D1), controladores planos documentados con Swagger vía Nelmio (D2), y colección de peticiones en Postman y en `.http`.

## Goals / Non-Goals

**Goals:**

- Que un cliente pueda obtener un token, usarlo, consultarlo y revocarlo.
- Que todo error bajo `/api/` tenga el mismo formato JSON.
- Que la documentación (OpenAPI y colecciones) no pueda quedarse atrás respecto al código sin que falle un test.
- Dejar fijadas las convenciones que usarán las fases 3 a 8.

**Non-Goals:**

- Endpoints de datos (diario, historial, etc.): fases 3 y siguientes.
- Refresh tokens, OAuth2, scopes o permisos por token.
- `login_throttling` del formulario web y el secreto del webhook (fase 0).
- Multiusuario: los endpoints toman el usuario del token y nada más.

## Decisions

### D1 — Token opaco con hash en BD

- El token es `bin2hex(random_bytes(32))` (256 bits). Se devuelve una sola vez, en la respuesta del login.
- En BD se guarda `hash('sha256', $token)`. Al ser un valor aleatorio de alta entropía no hace falta un hash lento ni sal; la búsqueda es por igualdad sobre una columna única.
- Entidad `ApiToken`: `id`, `user` (ManyToOne, `onDelete: CASCADE`), `token_hash` (64 caracteres, único), `name` (nombre del dispositivo), `created_at`, `last_used_at`.
- **Revocar es borrar la fila.** No se guarda histórico de tokens revocados: no hay nada que consultar sobre ellos.

Alternativas descartadas: JWT (claves que custodiar, refresh tokens, sin revocación inmediata) y sesión con cookie (CSRF y cookies desde una app nativa).

### D2 — Caducidad por inactividad

Un token es válido mientras `last_used_at` (o `created_at` si nunca se usó) tenga menos de 90 días. No hay columna `expires_at`: se calcula, y `GET /me` la devuelve como `expires_at`. El valor vive en `ApiTokenManager::INACTIVITY_DAYS`, porque es propio de los tokens y no un valor de toda la aplicación.

`last_used_at` se actualiza como mucho una vez por hora, para no escribir en BD en cada petición. Un token caducado se borra en el momento en que se intenta usar.

### D3 — Autenticador `access_token` nativo

`App\Security\ApiTokenHandler` implementa `AccessTokenHandlerInterface`: busca el token con `ApiTokenManager`, y devuelve el `UserBadge` del usuario. Además deja el `ApiToken` en el atributo `_api_token` de la petición, que usan `logout`, `me` y el log por petición. Es una interfaz de Symfony, no un puerto nuevo.

Firewall `api`: `pattern: ^/api/`, `stateless: true`, `access_token` con ese handler, declarado antes de `main`. En `access_control`, `^/api/v1/login` es público y el resto de `^/api/` exige `ROLE_USER`.

### D4 — Login como controlador normal

`POST /api/v1/login` es una acción pública que valida el JSON, comprueba la contraseña con `UserPasswordHasherInterface` y emite el token. No se usa `json_login`, porque ese autenticador está pensado para abrir una sesión y aquí el firewall no tiene estado.

- Cuerpo: `username`, `password`, `device_name` (obligatorios; `device_name` hasta 100 caracteres).
- `200` con `token`, `token_id`, `device_name` y `expires_at`.
- Credenciales incorrectas: `401` con el mismo mensaje exista o no el usuario.
- JSON mal formado: `400`. Campos ausentes o vacíos: `422`.

### D5 — Límite de intentos

Limitador `api_login` de `symfony/rate-limiter`, ventana deslizante de 5 intentos cada 15 minutos, con clave IP + usuario en minúsculas. Solo cuentan los intentos fallidos; un login correcto reinicia el contador. Superado el límite: `429` con cabecera `Retry-After`.

En el entorno de test el limitador usa un almacenamiento en memoria (`InMemoryStorage`), para que una ejecución no herede intentos de la anterior. No sirve un pool de caché `array`: el kernel lo vacía entre peticiones del mismo test.

### D6 — Formato de error

```json
{"code": "unauthorized", "message": "Token ausente, inválido o caducado."}
```

- `App\EventListener\ApiExceptionListener` convierte en ese formato cualquier excepción de una petición cuya ruta empiece por `/api/`. El resto de rutas no se ve afectado.
- Códigos: `bad_request`, `unauthorized`, `forbidden`, `not_found`, `method_not_allowed`, `conflict`, `validation_failed`, `too_many_requests`, `internal_error`.
- Los 401 del firewall (sin token o token inválido) salen de un `AuthenticationEntryPoint` y un `failure_handler` propios, con el mismo formato y la cabecera `WWW-Authenticate: Bearer`.
- En un 500 el mensaje es genérico; el detalle va al log, no al cliente.

Una excepción propia `ApiException` (código + mensaje + estado HTTP) cubre los errores de negocio que añadan las fases siguientes, como el 409 del reintento de audio.

### D7 — Convenciones comunes

Se documentan en `Especificaciones.md` y en la descripción del esquema OpenAPI:

- Prefijo `/api/v1`. Cuerpos y respuestas en JSON, claves en `snake_case`.
- Instantes en ISO 8601 UTC (`2026-10-08T07:09:33Z`). «Días» como `AAAA-MM-DD` en `LocalTimezone`.
- Paginación con `page` (desde 1) y `per_page`; la respuesta paginada lleva `items`, `page`, `per_page` y `total`.

En esta fase solo las usa `GET /me` (fechas). No se crea todavía ningún helper de paginación: se introduce en la fase 3, cuando exista el primer listado.

### D8 — Documentación y colecciones

- **Swagger UI** en `/doc/api` y esquema en `/doc/api.json`, fuera de `^/api/` para que queden bajo el firewall `main`: hace falta sesión web para verlos, también en producción. El esquema declara el esquema de seguridad `Bearer`.
- **`doc/openapi.json`** se genera con `make openapi` (`nelmio:apidoc:dump`) y se versiona. Sirve para importarlo en Postman o generar el cliente de la app.
- **`doc/MyDiary.postman_collection.json`** (v2.1) y **`doc/api.http`**, escritos a mano, con variables `base_url` y `token`; la petición de login guarda el token.
- **Guardas en tests**: uno compara `doc/openapi.json` con el esquema generado, y otro comprueba que cada ruta `/api/v1/*` del router aparece (método y ruta) en la colección Postman y en el `.http`. Así añadir un endpoint sin documentarlo rompe el suite.
- `AGENTS.md` recoge la regla: todo endpoint nuevo lleva sus atributos OpenAPI y entra en ambas colecciones en el mismo cambio.

Alternativa descartada: generar las colecciones a partir del OpenAPI. Postman puede importar el esquema, pero se pierden el guardado automático del token y los ejemplos; y para el `.http` no hay generador que merezca la dependencia.

### D9 — Logs

Campos planos y con prefijo `api_`:

- `HttpRequestLogListener`: añade `api_token_id` (entero) cuando la petición trae un token válido.
- `api.login_succeeded` (INFO): `api_token_id`, `api_device_name`.
- `api.login_failed` y `api.login_throttled` (WARNING): `api_username`.

Nunca se registra el token, su hash ni la contraseña. Los eventos van al canal `app`, que ya emite INFO en producción.

### D10 — Comandos de consola

- `app:user:token:list <username>`: tabla con id, dispositivo, creado, último uso y caducidad.
- `app:user:token:revoke <id>`: borra el token.

No hay comando para crear tokens: se crean con el login, que es el único sitio donde el token en claro tiene un destinatario.

## Risks / Trade-offs

- [Superficie nueva expuesta a internet] → login limitado por intentos, tokens de 256 bits guardados como hash, caducidad por inactividad y revocación por consola.
- [El esquema OpenAPI o las colecciones se quedan atrás] → los dos tests de D8.
- [Swagger UI necesita sus recursos estáticos] → Nelmio se configura con `assets_mode: offline`, que los incrusta en la página: sin CDN ni `assets:install`.
- [Borrar un token caducado dentro de una petición de lectura] → es una única fila y evita un comando de limpieza programado.
- [Dos dependencias nuevas] → ambas se usan en esta misma fase; `symfony/rate-limiter` servirá también para la fase 0.

## Migration Plan

`make deploy` hace `git pull` y a continuación `cache:clear`, que fallaría sin las dependencias nuevas (el bundle de Nelmio está en `config/bundles.php`). En el servidor, por este orden:

1. `git pull origin main`.
2. `make composer-install` (dependencias nuevas).
3. `make migrate` (tabla `api_token`).
4. `make deploy` (limpia la caché y reinicia `diary-php` y `diary-messenger-worker`).

Rollback: revertir el merge y ejecutar el `down()` de la migración (borra `api_token`; se pierden los tokens emitidos, que basta con volver a pedir).
