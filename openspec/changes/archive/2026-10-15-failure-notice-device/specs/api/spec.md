## MODIFIED Requirements

### Requirement: Subida de audio por la API
El sistema SHALL exponer `POST /api/v1/audios`, autenticado con token, que recibe un fichero de audio en el campo `file` de un cuerpo `multipart/form-data`, lo guarda en el almacenamiento de audios, crea un `AudioRecording` con `source` `app` en estado `PENDING` y despacha un `TranscribeAudioMessage`. La respuesta SHALL ser `201` con un JSON con `id`, `status`, `source`, `duration_seconds`, `received_at` (instante ISO 8601 UTC) y `result` con valor `created`. La duración SHALL calcularse en el servidor a partir del fichero, no tomarse de la petición. El `AudioRecording` SHALL guardar en `device_name` el nombre del dispositivo del token de la petición; no forma parte de la respuesta.

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

#### Scenario: Dispositivo del token
- **WHEN** se sube un audio nuevo con un token emitido con `device_name` «Google Pixel 7 (Android 16)»
- **THEN** el `AudioRecording` creado tiene `device_name` «Google Pixel 7 (Android 16)»

### Requirement: Deduplicación de subidas por contenido
El sistema SHALL identificar cada audio subido por el SHA-256 de su contenido (`content_hash`) y MUST NOT crear un segundo `AudioRecording` para un contenido ya subido. Si el audio existente está en `PENDING` o `TRANSCRIBED`, SHALL responder `200` con los datos de ese audio y `result` `duplicate`, sin modificarlo. Si está en `ERROR`, SHALL reiniciarlo a `PENDING`, limpiar `error_code` y `error_message`, guardar en `device_name` el dispositivo del token de la nueva subida, despachar un nuevo `TranscribeAudioMessage` y responder `200` con `result` `retrying`.

#### Scenario: Subida repetida
- **WHEN** se sube por segunda vez el mismo fichero y el audio existente está en `TRANSCRIBED`
- **THEN** la respuesta es `200` con `result` `duplicate` y el `id` del audio existente, no se crea otro `AudioRecording`, no se despacha ningún mensaje y su `device_name` no cambia

#### Scenario: Subida repetida tras un fallo
- **WHEN** se sube de nuevo un fichero cuyo `AudioRecording` está en `ERROR`
- **THEN** la respuesta es `200` con `result` `retrying`, el audio pasa a `PENDING` con `error_code` y `error_message` a `null`, `device_name` pasa a ser el del token de esta subida y se despacha un `TranscribeAudioMessage` para él
