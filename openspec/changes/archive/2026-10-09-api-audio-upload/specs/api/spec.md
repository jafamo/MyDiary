## ADDED Requirements

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
