## Why

Los logs de PHP no registran el código HTTP de las peticiones: los errores (`Uncaught PHP Exception ...`) no llevan el código (404, 500...) y las peticiones correctas (p. ej. el webhook de Telegram que crea un audio) no dejan rastro. Además, en `prod` el handler principal es `fingers_crossed` (`action_level: error`), así que cualquier log INFO solo se escribe si hay un error en la misma petición o mensaje: los "200 OK", la creación de audios/transcripciones y los `messenger_status` de mensajes correctos se pierden y no llegan a Kibana.

## What Changes

- Nuevo listener `App\EventListener\HttpRequestLogListener` (`kernel.terminate`, que solo se lanza para la petición principal) que escribe una línea por petición en un canal nuevo `http` con `event: http.request`, `status` (código HTTP, numérico como en el access log de nginx), `method`, `route`, `path` y `duration_ms`. Nivel `info` para 1xx–3xx, `warning` para 4xx y `error` para 5xx. Se excluyen las rutas internas de Symfony (`/_wdt`, `/_profiler`...).
- Nuevo log `audio_recording.received` (canal `app`, `info`) en el webhook de Telegram con el resultado de la recepción (`created`, `duplicate_message`, `duplicate_file`, `retrying_after_error`), el `status` HTTP devuelto y los ids de Telegram.
- Nuevo log `transcription.created` (canal `app`, `info`) en `TranscribeAudioMessageHandler` al guardar la transcripción, con `audio_recording_id`, `transcription_id`, `audio_recording_status` (`TRANSCRIBED`), `processing_ms` y `model`.
- `config/packages/monolog.yaml` (`prod`): nuevo handler `structured` sin buffer (`rotating_file`, mismo fichero, `level: info`) para los canales `http`, `app` y `messenger`; esos canales se excluyen del `fingers_crossed` principal para no duplicar líneas.
- Rellenar el `Purpose` (hoy `TBD`) de la spec `structured-logging`.

## Capabilities

### New Capabilities

### Modified Capabilities
- `structured-logging`: añade el log por petición HTTP con `status`, los eventos de negocio `audio_recording.received` y `transcription.created`, y la garantía de que los logs INFO de `http`/`app`/`messenger` se escriben en `prod` sin depender de que haya un error.

## Impact

- Código: `src/EventListener/HttpRequestLogListener.php` (nuevo), `src/Controller/TelegramWebhookController.php`, `src/MessageHandler/TranscribeAudioMessageHandler.php`, `config/packages/monolog.yaml`.
- Tests: listener nuevo, webhook y handler de transcripción.
- Volumen de logs en `prod`: una línea por petición HTTP más los INFO de `app`/`messenger` (scheduler cada 15 min). Proyecto de un solo usuario: volumen bajo, con la retención de 60 días ya existente.
- Kibana: `status` de PHP es numérico, compatible con el `status` de nginx ya mapeado. Refrescar el Data View para ver `route`, `method`, `duration_ms`.
