# structured-logging Specification

## Purpose
Campos estructurados que la aplicación añade a sus logs JSON (aplanados a primer nivel) para poder filtrarlos y agregarlos en Kibana: estado de los mensajes Messenger, código HTTP de cada petición y eventos de negocio.
## Requirements
### Requirement: Estado de los mensajes Messenger en los logs
Los registros de log del canal `messenger` SHALL incluir un campo `messenger_status` de primer nivel en el JSON con el estado del mensaje en su ciclo de vida, deducido del log que emite Symfony Messenger:

| Log de Symfony Messenger | `messenger_status` |
|---|---|
| `Received message {class}` | `received` |
| `Sending message {class} with {alias} sender using {sender}` | `sent` |
| `Message {class} handled by {handler}` | `handled` |
| `No handler for message {class}` | `no_handler` |
| `{class} was handled successfully (acknowledging to transport).` | `acknowledged` |
| `Error thrown while handling message {class}. Sending for retry ...` | `retry` |
| `Error thrown while handling message {class}. Removing from transport ...` | `failed` |
| `Rejected message {class} will be sent to the failure transport {transport}.` | `rejected` |

#### Scenario: Mensaje recibido por el worker
- **WHEN** el worker registra `Received message App\Message\TranscribeAudioMessage` en el canal `messenger`
- **THEN** la línea JSON del log incluye `"messenger_status": "received"` en el nivel raíz

#### Scenario: Mensaje procesado con éxito
- **WHEN** el worker registra `... was handled successfully (acknowledging to transport).`
- **THEN** la línea JSON del log incluye `"messenger_status": "acknowledged"`

#### Scenario: Mensaje reenviado a reintento
- **WHEN** Messenger registra `Error thrown while handling message ... Sending for retry #1 ...`
- **THEN** la línea JSON del log incluye `"messenger_status": "retry"`

#### Scenario: Mensaje descartado tras agotar reintentos
- **WHEN** Messenger registra `Error thrown while handling message ... Removing from transport after 3 retries ...`
- **THEN** la línea JSON del log incluye `"messenger_status": "failed"`

### Requirement: Sin `messenger_status` en logs no reconocidos
El campo `messenger_status` SHALL añadirse solo a registros del canal `messenger` que correspondan a uno de los estados definidos; el resto de registros (otros canales, o logs de `messenger` como `Stopping worker.`) MUST quedar sin modificar.

#### Scenario: Log de otro canal
- **WHEN** se registra `Lock acquired, now computing item "scheduler_checkpoint_default"` en el canal `cache`
- **THEN** la línea JSON del log no incluye campo `messenger_status`

#### Scenario: Log de messenger sin estado asociado
- **WHEN** el worker registra `Stopping worker.` en el canal `messenger`
- **THEN** la línea JSON del log no incluye campo `messenger_status`

### Requirement: Log por petición HTTP con su código de estado
La aplicación SHALL escribir, al terminar cada petición HTTP principal, una línea de log en el canal `http` con los campos de primer nivel `event` (`http.request`), `status` (código HTTP de la respuesta, numérico), `method`, `route`, `path` y `duration_ms`. El nivel MUST ser `info` para códigos < 400, `warning` para 4xx y `error` para 5xx. Las rutas internas de Symfony (path que empieza por `/_`, p. ej. `/_wdt`, `/_profiler`) MUST excluirse.

#### Scenario: Petición correcta
- **WHEN** el webhook de Telegram responde 200
- **THEN** se escribe una línea `info` en el canal `http` con `"status": 200`, `"method": "POST"` y `"route": "telegram_webhook"`

#### Scenario: Petición con error del cliente
- **WHEN** una petición responde 404 (p. ej. token del webhook incorrecto o ruta inexistente)
- **THEN** se escribe una línea `warning` en el canal `http` con `"status": 404`

#### Scenario: Excepción no capturada
- **WHEN** un controlador lanza una excepción no capturada y Symfony responde 500
- **THEN** se escribe una línea `error` en el canal `http` con `"status": 500`

#### Scenario: Rutas internas de Symfony
- **WHEN** se pide `/_wdt/abc123`
- **THEN** no se escribe ninguna línea en el canal `http`

### Requirement: Log de recepción de audio en el webhook
El webhook de Telegram SHALL registrar, por cada audio de un chat autorizado, una línea `info` en el canal `app` con `event` (`audio_recording.received`), `result` (`created`, `duplicate_message`, `duplicate_file` o `retrying_after_error`), `status` (código HTTP devuelto), `telegram_message_id` y `telegram_file_unique_id`.

#### Scenario: Audio nuevo
- **WHEN** llega por el webhook un audio que no existía
- **THEN** se escribe una línea con `"event": "audio_recording.received"`, `"result": "created"` y `"status": 200`

#### Scenario: Audio duplicado
- **WHEN** llega un audio con un `file_unique_id` ya transcrito
- **THEN** se escribe una línea con `"result": "duplicate_file"`

### Requirement: Log de transcripción creada
Al guardar una transcripción, el worker SHALL registrar una línea `info` en el canal `app` con `event` (`transcription.created`), `audio_recording_id`, `transcription_id`, `audio_recording_status` (`TRANSCRIBED`), `processing_ms` y `model`.

#### Scenario: Transcripción correcta
- **WHEN** el worker transcribe un audio con éxito
- **THEN** se escribe una línea con `"event": "transcription.created"` y `"audio_recording_status": "TRANSCRIBED"`

### Requirement: Logs INFO estructurados sin buffer en producción
En `prod`, los registros de nivel `info` o superior de los canales `http`, `app` y `messenger` SHALL escribirse siempre en el fichero de log de la app, sin depender de que se produzca un error en la misma petición o mensaje, y MUST NOT duplicarse cuando el `fingers_crossed` principal vuelca su buffer.

#### Scenario: Petición correcta en producción
- **WHEN** en `prod` una petición responde 200 sin ningún error
- **THEN** su línea del canal `http` aparece en el fichero de log

#### Scenario: Error en producción
- **WHEN** en `prod` una petición produce un error y el `fingers_crossed` vuelca su buffer
- **THEN** la línea del canal `http` de esa petición aparece una sola vez

