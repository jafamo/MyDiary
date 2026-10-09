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

### Requirement: Formas de respuesta compartidas
Todos los endpoints de la API SHALL representar cada recurso con la misma forma JSON:

- **Audio:** `id`, `status`, `source`, `duration_seconds`, `received_at`, `error_code`, `error_message` y `transcription` (`null` si aún no existe), con `id`, `content`, `edited_manually`, `model`, `processing_ms`, `created_at` y `updated_at`.
- **Resumen:** `id`, `date`, `summary_text`, `generated_at`, `emoji_legend`, `topics` (lista de `id` y `name`), `model`, `prompt_tokens`, `completion_tokens` y `generation_ms`.
- **Recordatorio:** `id`, `date`, `time` (`HH:MM` o `null`), `text`, `created_at` y `updated_at`.

La API MUST NOT exponer rutas de ficheros, embeddings, el hash de contenido ni los identificadores de Telegram.

#### Scenario: Audio transcrito
- **WHEN** un endpoint devuelve un audio en estado `TRANSCRIBED`
- **THEN** el audio lleva `transcription` con su `content` y `edited_manually`, y `error_code` y `error_message` a `null`

#### Scenario: Audio pendiente
- **WHEN** un endpoint devuelve un audio en estado `PENDING`
- **THEN** `transcription` es `null`

#### Scenario: Audio con error
- **WHEN** un endpoint devuelve un audio en estado `ERROR`
- **THEN** `error_code` y `error_message` llevan la causa y `transcription` es `null`

#### Scenario: Sin datos internos
- **WHEN** un endpoint devuelve un audio subido desde la app
- **THEN** el JSON no contiene `file_path`, `content_hash`, `embedding` ni ningún identificador de Telegram

### Requirement: Validación de parámetros de consulta
Los endpoints de lectura SHALL responder `422` con `code` `validation_failed` cuando un parámetro de consulta está presente y no es válido: `status` distinto de `PENDING`, `TRANSCRIBED` o `ERROR`; un día que no sea una fecha real en formato `AAAA-MM-DD`; un mes que no tenga el formato `AAAA-MM`; `page` menor que 1; `per_page` fuera de 1–100; o un valor no admitido en un parámetro enumerado. Un parámetro ausente SHALL tomar su valor por defecto (`page` 1, `per_page` 20).

#### Scenario: Estado desconocido
- **WHEN** se pide `GET /api/v1/diario?status=HECHO`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Fecha imposible
- **WHEN** se pide `GET /api/v1/historial/2026-02-30`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Paginación fuera de rango
- **WHEN** se pide `GET /api/v1/resumenes?per_page=500`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Página más allá de la última
- **WHEN** se pide `GET /api/v1/resumenes?page=999` y hay 3 resúmenes
- **THEN** la respuesta es `200` con `items` vacío y `total` `3`

#### Scenario: Lectura sin token
- **WHEN** se pide `GET /api/v1/diario` sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized`

### Requirement: Consulta del diario de hoy
El sistema SHALL exponer `GET /api/v1/diario`, que devuelve el día de hoy en la zona horaria de la aplicación: `date`, `entries` (audios recibidos hoy, con el filtro opcional `status`), `summary` (resumen de hoy o `null`), `streak` (`current` y `best`), `week` (`total` y `delta` respecto a la semana anterior) y `top_topic` (`name` y `count` del tema más frecuente del mes, o `null`).

#### Scenario: Día con audios y resumen
- **WHEN** hoy hay dos audios transcritos y un resumen, y se pide `GET /api/v1/diario`
- **THEN** la respuesta es `200`, `entries` tiene los dos audios con su transcripción y `summary` lleva el texto del resumen y sus temas

#### Scenario: Día vacío
- **WHEN** hoy no hay audios ni resumen
- **THEN** la respuesta es `200` con `entries` vacío y `summary` `null`

#### Scenario: Filtro por estado
- **WHEN** hoy hay un audio transcrito y otro en error, y se pide `GET /api/v1/diario?status=ERROR`
- **THEN** `entries` contiene solo el audio en error

### Requirement: Consulta del historial
El sistema SHALL exponer `GET /api/v1/historial`, que para el mes indicado en `month` (`AAAA-MM`; por defecto el mes actual) devuelve `month`, `previous_month`, `next_month` y `days`: una lista, ordenada por fecha, con los días de ese mes que tienen audios o resumen, cada uno con `date`, `audio_count` y `has_summary`. El sistema SHALL exponer `GET /api/v1/historial/{fecha}`, que devuelve `date`, `entries` (audios de ese día, con el filtro opcional `status`) y `summary` (o `null`).

#### Scenario: Mes con actividad
- **WHEN** en octubre de 2026 hay tres audios el día 5 y un resumen ese mismo día, y se pide `GET /api/v1/historial?month=2026-10`
- **THEN** `days` contiene `{"date": "2026-10-05", "audio_count": 3, "has_summary": true}` y no contiene días sin audios ni resumen

#### Scenario: Meses contiguos
- **WHEN** se pide `GET /api/v1/historial?month=2026-01`
- **THEN** `previous_month` es `2025-12` y `next_month` es `2026-02`

#### Scenario: Día concreto
- **WHEN** se pide `GET /api/v1/historial/2026-10-05`
- **THEN** la respuesta es `200` con los audios recibidos ese día y su resumen

#### Scenario: Día sin nada
- **WHEN** se pide `GET /api/v1/historial/2020-01-01` y ese día no tiene audios ni resumen
- **THEN** la respuesta es `200` con `entries` vacío y `summary` `null`

### Requirement: Consulta de resúmenes
El sistema SHALL exponer `GET /api/v1/resumenes`, listado paginado de resúmenes diarios del más reciente al más antiguo, donde cada elemento es el resumen más `audio_count` (audios recibidos ese día). Los parámetros opcionales `from` y `to` (`AAAA-MM-DD`, ambos inclusive) SHALL limitar el listado a ese rango; SHALL enviarse los dos o ninguno, y `from` MUST NOT ser posterior a `to`.

#### Scenario: Listado sin rango
- **WHEN** hay 25 resúmenes y se pide `GET /api/v1/resumenes`
- **THEN** la respuesta lleva los 20 más recientes en `items`, `page` `1`, `per_page` `20` y `total` `25`

#### Scenario: Rango de fechas
- **WHEN** se pide `GET /api/v1/resumenes?from=2026-10-01&to=2026-10-07`
- **THEN** `items` solo contiene resúmenes con `date` entre esos dos días

#### Scenario: Rango incompleto o invertido
- **WHEN** se pide `GET /api/v1/resumenes?from=2026-10-07` o `?from=2026-10-07&to=2026-10-01`
- **THEN** la respuesta es `422` con `code` `validation_failed`

### Requirement: Búsqueda
El sistema SHALL exponer `GET /api/v1/busqueda?q=`, que devuelve `query`, `results` y `reminders`. `results` SHALL ser la búsqueda semántica en transcripciones y resúmenes fusionada por cercanía, donde cada elemento lleva `type` (`transcription` o `daily_summary`), `distance`, `date` y el recurso encontrado (`audio` o `summary`; el otro a `null`). `reminders` SHALL ser la búsqueda textual en recordatorios. `q` es obligatorio y MUST NOT estar vacío. Si la generación del embedding falla, `results` SHALL ir vacío y `reminders` SHALL devolverse igualmente.

#### Scenario: Resultados de los dos tipos
- **WHEN** se pide `GET /api/v1/busqueda?q=dentista` y hay una transcripción y un resumen cercanos
- **THEN** `results` contiene un elemento `transcription` con su `audio` y uno `daily_summary` con su `summary`, ordenados por `distance` ascendente

#### Scenario: Recordatorios por texto
- **WHEN** existe un recordatorio con el texto «Dentista a las 10» y se pide `GET /api/v1/busqueda?q=dentista`
- **THEN** `reminders` contiene ese recordatorio

#### Scenario: Consulta vacía
- **WHEN** se pide `GET /api/v1/busqueda` sin `q` o con `q` en blanco
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Fallo del embedding
- **WHEN** el generador de embeddings falla durante la búsqueda
- **THEN** la respuesta es `200` con `results` vacío y los recordatorios coincidentes en `reminders`

### Requirement: Consulta de estadísticas
El sistema SHALL exponer `GET /api/v1/estadisticas`, que devuelve `range`, `from`, `to`, las métricas del rango (series diarias de audios y de recordatorios, totales, medias, días con resumen, recuento por estado, frecuencia de temas, rachas, día récord y comparativa con el periodo anterior) y `ai_usage` con `totals` y `days`. `range` SHALL admitir `15`, `30` (por defecto), `90`, `365` y `custom`; con `custom` SHALL exigir `from` y `to` válidos con `from` no posterior a `to`. El filtro opcional `status` SHALL aplicarse como en la vista web. Todas las claves SHALL ir en `snake_case`.

#### Scenario: Rango por defecto
- **WHEN** se pide `GET /api/v1/estadisticas`
- **THEN** `range` es `30`, `to` es hoy, y `series` tiene 30 elementos con `date` y `value`

#### Scenario: Rango personalizado
- **WHEN** se pide `GET /api/v1/estadisticas?range=custom&from=2026-10-01&to=2026-10-07`
- **THEN** `from` es `2026-10-01`, `to` es `2026-10-07` y `total_days` es `7`

#### Scenario: Rango personalizado incompleto
- **WHEN** se pide `GET /api/v1/estadisticas?range=custom&from=2026-10-01`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Consumo de IA en snake_case
- **WHEN** se pide `GET /api/v1/estadisticas`
- **THEN** `ai_usage.totals` tiene las claves `prompt_tokens`, `completion_tokens`, `total_tokens`, `avg_tokens_per_summary`, `summaries_with_tokens`, `audio_seconds`, `whisper_ms` y `ollama_ms`

### Requirement: Consulta de recordatorios
El sistema SHALL exponer `GET /api/v1/recordatorios`, listado paginado de recordatorios con uno de estos filtros, excluyentes entre sí: `scope=upcoming` (por defecto; de hoy en adelante, orden ascendente), `scope=history` (anteriores a hoy, orden descendente), `month=AAAA-MM` (los de ese mes) o `date=AAAA-MM-DD` (los de ese día). Combinar `scope`, `month` y `date` SHALL responder `422`.

El sistema SHALL exponer `GET /api/v1/recordatorios/proximos`, que devuelve el aviso de recordatorios cercanos: `count` (recordatorios en los próximos 5 días, hoy incluido), `level` (`urgent` si el más cercano es hoy o mañana, `upcoming` en otro caso, `null` si no hay ninguno), `nearest_date` y `reminders` (los del día más cercano).

#### Scenario: Próximos por defecto
- **WHEN** hay un recordatorio ayer y otro mañana, y se pide `GET /api/v1/recordatorios`
- **THEN** `items` contiene solo el de mañana y `total` es `1`

#### Scenario: Históricos
- **WHEN** se pide `GET /api/v1/recordatorios?scope=history` con un recordatorio ayer y otro mañana
- **THEN** `items` contiene solo el de ayer

#### Scenario: Recordatorios de un mes
- **WHEN** se pide `GET /api/v1/recordatorios?month=2026-10`
- **THEN** `items` contiene los recordatorios con fecha en octubre de 2026 y ninguno de otro mes

#### Scenario: Filtros combinados
- **WHEN** se pide `GET /api/v1/recordatorios?scope=history&month=2026-10`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Aviso urgente
- **WHEN** hay dos recordatorios mañana y uno dentro de cuatro días, y se pide `GET /api/v1/recordatorios/proximos`
- **THEN** `count` es `3`, `level` es `urgent`, `nearest_date` es mañana y `reminders` contiene los dos de mañana

#### Scenario: Sin recordatorios cercanos
- **WHEN** no hay recordatorios en los próximos 5 días
- **THEN** `count` es `0`, `level` y `nearest_date` son `null` y `reminders` está vacío

### Requirement: Consulta de temas
El sistema SHALL exponer `GET /api/v1/topics`, que devuelve todos los temas en `items`, sin paginar, cada uno con `id`, `name`, `usage_count` (resúmenes que lo usan) y `last_used` (día del resumen más reciente, o `null`), ordenados por uso descendente y nombre, junto con `total`.

#### Scenario: Temas con y sin uso
- **WHEN** existe un tema usado en dos resúmenes y otro sin usar, y se pide `GET /api/v1/topics`
- **THEN** el primero aparece antes, con `usage_count` `2` y su `last_used`, y el segundo con `usage_count` `0` y `last_used` `null`

