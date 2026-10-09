# api Specification

## Purpose
TBD - created by archiving change api-base. Update Purpose after archive.
## Requirements
### Requirement: Autenticación de la API por token
El sistema SHALL proteger todas las rutas bajo `/api/` con un firewall sin estado que autentica mediante la cabecera `Authorization: Bearer <token>`, sin sesión ni CSRF. La única ruta pública bajo `/api/` SHALL ser `POST /api/v1/login`. El usuario de cada petición SHALL obtenerse del token y nunca de un parámetro. Las rutas web existentes MUST seguir funcionando con sesión como hasta ahora.

#### Scenario: Petición sin token
- **WHEN** se pide `GET /api/v1/me` sin cabecera `Authorization`
- **THEN** la respuesta es `401` en JSON con `code` `unauthorized` y cabecera `WWW-Authenticate: Bearer`

#### Scenario: Token inválido
- **WHEN** se pide `GET /api/v1/me` con un token que no existe
- **THEN** la respuesta es `401` en JSON con `code` `unauthorized`

#### Scenario: Token válido
- **WHEN** se pide `GET /api/v1/me` con un token emitido por el login
- **THEN** la respuesta es `200`

#### Scenario: La sesión web no sirve para la API
- **WHEN** un navegador con sesión web activa pide `GET /api/v1/me` sin token
- **THEN** la respuesta es `401`

#### Scenario: La web no cambia
- **WHEN** un visitante sin sesión pide `/`
- **THEN** el sistema le redirige a `/login`, igual que antes

### Requirement: Login de la API
`POST /api/v1/login` SHALL aceptar un cuerpo JSON con `username`, `password` y `device_name`, y con credenciales correctas SHALL crear un token nuevo para ese dispositivo y responder `200` con `token`, `token_id`, `device_name` y `expires_at`. El token SHALL ser un valor aleatorio de 256 bits que solo se devuelve en esa respuesta; el sistema SHALL guardar únicamente su hash SHA-256.

#### Scenario: Login correcto
- **WHEN** se envían las credenciales correctas con `device_name` "iPhone"
- **THEN** la respuesta es `200` con un `token` de 64 caracteres hexadecimales, y en `api_token` existe una fila con `name` "iPhone" cuyo `token_hash` es el SHA-256 de ese token

#### Scenario: Contraseña incorrecta
- **WHEN** se envía una contraseña incorrecta para un usuario existente
- **THEN** la respuesta es `401` con `code` `unauthorized` y no se crea ningún token

#### Scenario: Usuario inexistente
- **WHEN** se envía un `username` que no existe
- **THEN** la respuesta es `401` con el mismo `code` y `message` que con contraseña incorrecta

#### Scenario: Campos ausentes
- **WHEN** se envía un cuerpo JSON sin `device_name`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: JSON mal formado
- **WHEN** se envía un cuerpo que no es JSON válido
- **THEN** la respuesta es `400` con `code` `bad_request`

### Requirement: Límite de intentos en el login de la API
El sistema SHALL limitar los intentos fallidos de `POST /api/v1/login` a 5 cada 15 minutos por combinación de IP y `username`. Superado el límite SHALL responder `429` con cabecera `Retry-After`, sin comprobar la contraseña. Un login correcto SHALL reiniciar el contador.

#### Scenario: Demasiados intentos
- **WHEN** se envían 5 logins fallidos seguidos para el mismo usuario desde la misma IP y después un sexto
- **THEN** el sexto responde `429` con `code` `too_many_requests` y cabecera `Retry-After`, aunque lleve la contraseña correcta

#### Scenario: El límite es por usuario
- **WHEN** un usuario está bloqueado por intentos y se hace login con otro `username` desde la misma IP
- **THEN** ese segundo login se procesa con normalidad

### Requirement: Caducidad y revocación de tokens
Un token SHALL dejar de ser válido cuando pasen 90 días desde su último uso (o desde su creación si nunca se usó). El sistema SHALL actualizar `last_used_at` al usar el token, como mucho una vez por hora. `POST /api/v1/logout` SHALL revocar el token con el que se hace la petición y responder `204`.

#### Scenario: Token caducado por inactividad
- **WHEN** se usa un token cuyo último uso fue hace 91 días
- **THEN** la respuesta es `401` y el token se elimina de `api_token`

#### Scenario: El uso renueva la validez
- **WHEN** se usa un token cuyo último uso fue hace 89 días
- **THEN** la respuesta es `200` y `last_used_at` pasa a la fecha actual

#### Scenario: Logout
- **WHEN** se llama a `POST /api/v1/logout` con un token válido y después se usa ese mismo token
- **THEN** el logout responde `204` y la segunda petición responde `401`

#### Scenario: El logout no afecta a otros dispositivos
- **WHEN** existen dos tokens del mismo usuario y se hace logout con uno
- **THEN** el otro token sigue siendo válido

### Requirement: Consulta del usuario y del token en uso
`GET /api/v1/me` SHALL devolver el `username` del usuario autenticado y los datos del token en uso: `token_id`, `device_name`, `created_at`, `last_used_at` y `expires_at`. La respuesta MUST NOT incluir el token ni su hash.

#### Scenario: Datos del token
- **WHEN** se pide `GET /api/v1/me` con un token emitido para "iPhone"
- **THEN** la respuesta incluye `username`, `device_name` "iPhone" y un `expires_at` 90 días posterior a `last_used_at`

### Requirement: Formato único de error
Toda respuesta de error de una ruta bajo `/api/` SHALL ser JSON con las claves `code` (identificador estable en `snake_case`) y `message` (texto legible), para los estados 400, 401, 403, 404, 405, 409, 422, 429 y 500. En un 500 el `message` MUST ser genérico y no revelar detalles internos. Las rutas fuera de `/api/` MUST conservar sus páginas de error actuales.

#### Scenario: Ruta inexistente de la API
- **WHEN** se pide `GET /api/v1/no-existe` con un token válido
- **THEN** la respuesta es `404` con `Content-Type: application/json` y `code` `not_found`

#### Scenario: Método no permitido
- **WHEN** se pide `GET /api/v1/login`
- **THEN** la respuesta es `405` con `code` `method_not_allowed`

#### Scenario: Ruta web inexistente
- **WHEN** un usuario con sesión pide `/no-existe`
- **THEN** la respuesta es la página de error HTML habitual, no JSON

### Requirement: Convenciones comunes de la API
Los endpoints de la API SHALL usar el prefijo `/api/v1`, cuerpos y respuestas JSON con claves en `snake_case`, instantes en ISO 8601 en UTC y días como `AAAA-MM-DD` en la zona horaria de la aplicación. Los listados paginados SHALL aceptar `page` (desde 1) y `per_page`, y responder con `items`, `page`, `per_page` y `total`.

#### Scenario: Instantes en UTC
- **WHEN** se pide `GET /api/v1/me`
- **THEN** `created_at` y `expires_at` tienen el formato `AAAA-MM-DDTHH:MM:SSZ`

### Requirement: Gestión de tokens por consola
El sistema SHALL proveer `bin/console app:user:token:list <username>`, que lista los tokens de un usuario (id, dispositivo, creación, último uso y caducidad) sin mostrar el token ni su hash, y `bin/console app:user:token:revoke <id>`, que elimina un token.

#### Scenario: Listar tokens
- **WHEN** se ejecuta `app:user:token:list` para un usuario con un token "iPhone"
- **THEN** la salida incluye su id y "iPhone"

#### Scenario: Revocar un token
- **WHEN** se ejecuta `app:user:token:revoke` con el id de un token existente y después se usa ese token
- **THEN** el comando termina con éxito y la petición responde `401`

#### Scenario: Revocar un id inexistente
- **WHEN** se ejecuta `app:user:token:revoke` con un id que no existe
- **THEN** el comando termina con error y no modifica nada

### Requirement: Documentación OpenAPI de la API
El sistema SHALL documentar cada endpoint de `/api/v1` con OpenAPI, servir Swagger UI en `/doc/api` y el esquema en `/doc/api.json` (ambos solo con sesión web), y mantener versionado el esquema en `doc/openapi.json`. El esquema SHALL declarar el esquema de seguridad `Bearer`. El suite de tests SHALL fallar si `doc/openapi.json` no coincide con el esquema generado desde el código.

#### Scenario: Swagger UI con sesión
- **WHEN** un usuario con sesión web pide `/doc/api`
- **THEN** la respuesta es `200` con la interfaz de Swagger

#### Scenario: Swagger UI sin sesión
- **WHEN** un visitante sin sesión pide `/doc/api`
- **THEN** el sistema le redirige a `/login`

#### Scenario: Esquema desactualizado
- **WHEN** se añade un endpoint a `/api/v1` sin regenerar `doc/openapi.json`
- **THEN** el test que compara el fichero con el esquema generado falla

### Requirement: Colecciones de peticiones
El repositorio SHALL incluir una colección Postman (`doc/MyDiary.postman_collection.json`) y un fichero `doc/api.http` con una petición por cada endpoint de `/api/v1`, usando variables para la URL base y el token, y guardando el token devuelto por el login para las demás peticiones. El suite de tests SHALL fallar si alguna ruta `/api/v1/*` del router no aparece, con su método, en ambos ficheros.

#### Scenario: Endpoint sin colección
- **WHEN** se añade una ruta `/api/v1/*` y no se añade a la colección Postman o al `.http`
- **THEN** el test que cruza el router con las colecciones falla indicando la ruta que falta

### Requirement: Subida de audio por la API
El sistema SHALL exponer `POST /api/v1/audios`, autenticado con token, que recibe un fichero de audio en el campo `file` de un cuerpo `multipart/form-data`, lo guarda en el almacenamiento de audios, crea un `AudioRecording` con `source` `app` en estado `PENDING` y despacha un `TranscribeAudioMessage`. La respuesta SHALL ser `201` con un JSON con `id`, `status`, `source`, `duration_seconds`, `received_at` (instante ISO 8601 UTC) y `result` con valor `created`. La duración SHALL calcularse en el servidor a partir del fichero, no tomarse de la petición.

#### Scenario: Audio nuevo
- **WHEN** se envía a `POST /api/v1/audios` un fichero `.m4a` válido que no existía
- **THEN** la respuesta es `201` con `result` `created`, `status` `PENDING` y `source` `app`, existe un `AudioRecording` con ese `id` y se ha despachado un `TranscribeAudioMessage` para él

#### Scenario: Duración calculada en servidor
- **WHEN** se sube un audio que el sondeo del fichero mide en 37 segundos
- **THEN** `duration_seconds` es `37` en la respuesta y en el `AudioRecording`

#### Scenario: Fichero guardado con su hash
- **WHEN** se sube un audio nuevo en formato `m4a`
- **THEN** el fichero queda guardado en el almacenamiento de audios como `<content_hash>.m4a` y `file_path` apunta a él

#### Scenario: Sin token
- **WHEN** se envía `POST /api/v1/audios` sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized` y no se crea ningún `AudioRecording`

### Requirement: Deduplicación de subidas por contenido
El sistema SHALL identificar cada audio subido por el SHA-256 de su contenido (`content_hash`) y MUST NOT crear un segundo `AudioRecording` para un contenido ya subido. Si el audio existente está en `PENDING` o `TRANSCRIBED`, SHALL responder `200` con los datos de ese audio y `result` `duplicate`, sin modificarlo. Si está en `ERROR`, SHALL reiniciarlo a `PENDING`, limpiar `error_code` y `error_message`, despachar un nuevo `TranscribeAudioMessage` y responder `200` con `result` `retrying`.

#### Scenario: Subida repetida
- **WHEN** se sube por segunda vez el mismo fichero y el audio existente está en `TRANSCRIBED`
- **THEN** la respuesta es `200` con `result` `duplicate` y el `id` del audio existente, no se crea otro `AudioRecording` y no se despacha ningún mensaje

#### Scenario: Subida repetida tras un fallo
- **WHEN** se sube de nuevo un fichero cuyo `AudioRecording` está en `ERROR`
- **THEN** la respuesta es `200` con `result` `retrying`, el audio pasa a `PENDING` con `error_code` y `error_message` a `null` y se despacha un `TranscribeAudioMessage` para él

### Requirement: Validación del audio subido
El sistema SHALL aceptar únicamente audios en formato `m4a`, `mp3`, `ogg` o `wav`, determinando el formato por el contenido del fichero y no por su extensión ni por el `Content-Type` enviado por el cliente, y SHALL rechazar los ficheros de más de 25 MB. Toda subida rechazada SHALL responder `422` con `code` `validation_failed` y MUST NOT crear ningún `AudioRecording` ni dejar el fichero en el almacenamiento de audios.

#### Scenario: Falta el fichero
- **WHEN** se envía `POST /api/v1/audios` sin el campo `file`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: El fichero no es un audio admitido
- **WHEN** se sube un fichero de texto con nombre `nota.m4a` y `Content-Type` `audio/mp4`
- **THEN** la respuesta es `422` con `code` `validation_failed` y no se crea ningún `AudioRecording`

#### Scenario: Fichero demasiado grande
- **WHEN** se sube un fichero de más de 25 MB
- **THEN** la respuesta es `422` con `code` `validation_failed` y no se crea ningún `AudioRecording`

#### Scenario: Formato admitido distinto de m4a
- **WHEN** se sube un fichero `.ogg` válido
- **THEN** la respuesta es `201` y el fichero se guarda con extensión `ogg`

