## ADDED Requirements

### Requirement: Validación de cuerpos JSON de escritura
Los endpoints de escritura que reciben datos SHALL aceptar un cuerpo JSON con claves en `snake_case` y validarlo con las mismas reglas que el formulario equivalente de la web. Un cuerpo que no sea un objeto JSON SHALL responder `400` con `code` `bad_request`. Un cuerpo con datos inválidos, campos obligatorios ausentes o campos desconocidos SHALL responder `422` con `code` `validation_failed` y un `message` que indique el campo y el motivo, sin modificar nada. En los `PATCH`, los campos ausentes SHALL conservar su valor actual.

#### Scenario: Cuerpo que no es JSON
- **WHEN** se envía `POST /api/v1/recordatorios` con un cuerpo que no es JSON válido
- **THEN** la respuesta es `400` con `code` `bad_request` y no se crea ningún recordatorio

#### Scenario: Campo desconocido
- **WHEN** se envía `PATCH /api/v1/recordatorios/{id}` con `{"titulo": "x"}`
- **THEN** la respuesta es `422` con `code` `validation_failed` y el recordatorio no cambia

#### Scenario: Escritura sin token
- **WHEN** se envía `DELETE /api/v1/recordatorios/{id}` sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized` y el recordatorio sigue existiendo

#### Scenario: Recurso inexistente
- **WHEN** se envía `PATCH /api/v1/topics/{id}` con un id que no existe
- **THEN** la respuesta es `404` con `code` `not_found`

### Requirement: Edición de la transcripción por la API
El sistema SHALL exponer `PATCH /api/v1/audios/{id}/transcripcion`, que recibe `content` y aplica la misma edición manual que la web: actualiza el texto, marca `edited_manually`, regenera el fichero de export y el embedding. Solo SHALL admitirse sobre audios en estado `TRANSCRIBED`; en otro estado SHALL responder `409` con `code` `conflict` sin modificar nada. Un `content` ausente o en blanco SHALL responder `422`. La respuesta SHALL ser `200` con el audio en la forma compartida, incluida su transcripción.

#### Scenario: Edición correcta
- **WHEN** se envía `{"content": "Texto corregido"}` para un audio `TRANSCRIBED`
- **THEN** la respuesta es `200`, `transcription.content` es "Texto corregido" y `transcription.edited_manually` es `true`

#### Scenario: Audio sin transcribir
- **WHEN** se envía la edición para un audio en `PENDING` o `ERROR`
- **THEN** la respuesta es `409` con `code` `conflict`

#### Scenario: Texto vacío
- **WHEN** se envía `{"content": "   "}` para un audio `TRANSCRIBED`
- **THEN** la respuesta es `422` con `code` `validation_failed` y la transcripción no cambia

### Requirement: Eliminación de un audio por la API
El sistema SHALL exponer `DELETE /api/v1/audios/{id}`, que elimina el audio, su transcripción y sus ficheros con las mismas reglas que la web. Solo SHALL admitirse sobre audios en `TRANSCRIBED` o `ERROR`; un audio en `PENDING` SHALL responder `409` con `code` `conflict` sin borrar nada. La respuesta SHALL ser `204` sin cuerpo.

#### Scenario: Eliminar un audio transcrito
- **WHEN** se envía `DELETE /api/v1/audios/{id}` para un audio `TRANSCRIBED`
- **THEN** la respuesta es `204` y ya no existen el audio ni su transcripción

#### Scenario: Eliminar un audio pendiente
- **WHEN** se envía `DELETE /api/v1/audios/{id}` para un audio `PENDING`
- **THEN** la respuesta es `409` con `code` `conflict` y el audio sigue existiendo

### Requirement: Reintento de la transcripción por la API
El sistema SHALL exponer `POST /api/v1/audios/{id}/reintentar`, que para un audio en `ERROR` lo pasa a `PENDING`, limpia `error_code` y `error_message` y despacha un `TranscribeAudioMessage`, igual que la web. En otro estado SHALL responder `409` con `code` `conflict` sin modificar nada ni despachar mensajes. La respuesta SHALL ser `200` con el audio en la forma compartida.

#### Scenario: Reintentar un audio en error
- **WHEN** se envía `POST /api/v1/audios/{id}/reintentar` para un audio en `ERROR`
- **THEN** la respuesta es `200` con `status` `PENDING` y `error_code` `null`, y se ha despachado un `TranscribeAudioMessage` para él

#### Scenario: Reintentar un audio que no está en error
- **WHEN** se envía la petición para un audio `TRANSCRIBED`
- **THEN** la respuesta es `409` con `code` `conflict` y no se despacha ningún mensaje

### Requirement: Gestión de recordatorios por la API
El sistema SHALL exponer `POST /api/v1/recordatorios` (crear), `PATCH /api/v1/recordatorios/{id}` (editar) y `DELETE /api/v1/recordatorios/{id}` (eliminar), con las reglas del formulario de la web: `date` (`AAAA-MM-DD`) y `text` (no en blanco) obligatorios y `time` (`HH:MM`) opcional, que se borra enviándolo a `null`. Crear SHALL responder `201`, editar `200`, ambos con el recordatorio en la forma compartida, y eliminar `204` sin cuerpo. Editar SHALL actualizar `updated_at`.

#### Scenario: Crear un recordatorio
- **WHEN** se envía `POST /api/v1/recordatorios` con `{"date": "2026-10-20", "time": "10:30", "text": "Dentista"}`
- **THEN** la respuesta es `201` con `date` "2026-10-20", `time` "10:30" y `text` "Dentista", y el recordatorio existe

#### Scenario: Crear sin texto
- **WHEN** se envía `POST /api/v1/recordatorios` con `{"date": "2026-10-20", "text": " "}`
- **THEN** la respuesta es `422` con `code` `validation_failed` y no se crea ningún recordatorio

#### Scenario: Fecha inválida
- **WHEN** se envía `POST /api/v1/recordatorios` con `{"date": "2026-02-30", "text": "x"}`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Edición parcial
- **WHEN** se envía `PATCH /api/v1/recordatorios/{id}` con solo `{"text": "Nuevo texto"}` sobre un recordatorio con fecha y hora
- **THEN** la respuesta es `200` con el texto nuevo y la fecha y la hora sin cambios

#### Scenario: Quitar la hora
- **WHEN** se envía `PATCH /api/v1/recordatorios/{id}` con `{"time": null}`
- **THEN** la respuesta es `200` con `time` `null`

#### Scenario: Eliminar un recordatorio
- **WHEN** se envía `DELETE /api/v1/recordatorios/{id}`
- **THEN** la respuesta es `204` y el recordatorio ya no existe

### Requirement: Renombrado de temas por la API
El sistema SHALL exponer `PATCH /api/v1/topics/{id}`, que recibe `name` y renombra el tema. Un nombre ausente, en blanco o de más de 255 caracteres SHALL responder `422` con `code` `validation_failed`. Un nombre que ya usa otro tema, sin distinguir mayúsculas, SHALL responder `409` con `code` `conflict` y un `message` que nombre el tema existente, sin modificar nada. La respuesta SHALL ser `200` con el tema (`id` y `name`).

#### Scenario: Renombrado válido
- **WHEN** se envía `{"name": "trabajo"}` para el tema "curro" y no existe otro tema "trabajo"
- **THEN** la respuesta es `200` con `name` "trabajo"

#### Scenario: Nombre ya usado
- **WHEN** se envía `{"name": "TRABAJO"}` para el tema "curro" y existe el tema "trabajo"
- **THEN** la respuesta es `409` con `code` `conflict` y el tema sigue llamándose "curro"

### Requirement: Fusión de temas por la API
El sistema SHALL exponer `POST /api/v1/topics/fusionar`, que recibe `origins` (lista de ids) y `destination` (id) y fusiona los temas origen en el destino, como la web pero sin paso de confirmación. Un id inexistente, `origins` vacío o que solo contenga el destino SHALL responder `422` con `code` `validation_failed` sin modificar nada. La respuesta SHALL ser `200` con el tema destino (`id` y `name`).

#### Scenario: Fusión correcta
- **WHEN** se envía `{"origins": [<curro>], "destination": <trabajo>}` y "curro" estaba en el resumen A
- **THEN** la respuesta es `200` con el tema "trabajo", el resumen A queda asociado a "trabajo" y "curro" ya no existe

#### Scenario: Origen igual al destino
- **WHEN** se envía `{"origins": [<trabajo>], "destination": <trabajo>}`
- **THEN** la respuesta es `422` con `code` `validation_failed` y no se elimina ningún tema

#### Scenario: Tema inexistente
- **WHEN** `origins` contiene un id que no existe
- **THEN** la respuesta es `422` con `code` `validation_failed` y no se modifica ningún tema
