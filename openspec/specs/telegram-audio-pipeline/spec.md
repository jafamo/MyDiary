## Purpose

Recepción de audios por webhook de Telegram, deduplicación (por mensaje y por fichero), descarga, transcripción asíncrona vía Open WebUI/Whisper, y manejo de errores/reintentos técnicos con logging estructurado.
## Requirements
### Requirement: Webhook con token en la URL
El sistema SHALL exponer el webhook de Telegram en `POST /telegram/webhook/{token}` y SHALL responder 404 sin procesar el body si `{token}` no coincide con el configurado.

#### Scenario: Token incorrecto
- **WHEN** llega una petición a `/telegram/webhook/token-incorrecto`
- **THEN** el sistema responde 404 sin crear ningún registro ni leer el body

### Requirement: Whitelist de chat autorizado
El sistema SHALL descartar cualquier update cuyo `chat_id` no coincida con `TELEGRAM_AUTHORIZED_CHAT_ID`, respondiendo 200 OK sin crear registros ni responder al remitente, dejando constancia en logs.

#### Scenario: Chat no autorizado
- **WHEN** llega un update de audio desde un `chat_id` distinto al configurado
- **THEN** el sistema responde 200 OK, no crea ningún `AudioRecording`, no envía respuesta a Telegram, y registra el evento en logs con `chat_id` y `telegram_message_id` como contexto estructurado

### Requirement: Deduplicación por `telegram_message_id`
El sistema SHALL responder 200 OK sin reprocesar nada si ya existe un `AudioRecording` con el mismo `telegram_message_id` (reintento del propio webhook de Telegram).

#### Scenario: Reintento del webhook
- **WHEN** Telegram reenvía el mismo update (mismo `telegram_message_id`) tras no recibir confirmación a tiempo
- **THEN** el sistema responde 200 OK sin crear un segundo `AudioRecording` ni volver a descargar el fichero

### Requirement: Deduplicación por `telegram_file_unique_id`
El sistema SHALL comprobar si ya existe un `AudioRecording` con el mismo `telegram_file_unique_id` antes de crear uno nuevo, y SHALL comportarse según su estado: si está en `ERROR`, lo resetea a `PENDING`, limpia `error_code`/`error_message` y despacha un nuevo `TranscribeAudioMessage` para ese mismo registro; si está en `PENDING` o `TRANSCRIBED`, no hace nada más.

#### Scenario: Reenvío tras fallo técnico
- **WHEN** el usuario reenvía un audio cuyo `AudioRecording` existente está en estado `ERROR`
- **THEN** el sistema resetea ese `AudioRecording` a `PENDING`, limpia los campos de error, despacha un nuevo `TranscribeAudioMessage` para el mismo registro, y responde *"Reintentando transcripción 🔄"*

#### Scenario: Reenvío redundante
- **WHEN** el usuario reenvía un audio cuyo `AudioRecording` existente está en `PENDING` o `TRANSCRIBED`
- **THEN** el sistema responde *"Ya tengo este audio 👍"* sin crear ni modificar ningún registro

### Requirement: Recepción y respuesta rápida
El sistema SHALL, para un audio nuevo, guardar `duration_seconds` desde el update, descargar el fichero de Telegram, crear un `AudioRecording` en estado `PENDING`, responder *"Audio recibido ✅"* y despachar `TranscribeAudioMessage` de forma asíncrona, sin bloquear la respuesta HTTP en la transcripción.

#### Scenario: Audio nuevo recibido
- **WHEN** llega un update de audio nuevo de un chat autorizado
- **THEN** se crea un `AudioRecording` en `PENDING` con `telegram_message_id`, `telegram_file_unique_id` y `duration_seconds` correctos, el fichero queda descargado en el filesystem, se responde *"Audio recibido ✅"*, y se despacha un `TranscribeAudioMessage`

### Requirement: Transcripción asíncrona
El sistema SHALL, al procesar `TranscribeAudioMessage`, llamar al servicio de transcripción configurado (`TranscriberInterface`), guardar el resultado en `Transcription` (contenido en BD y export a fichero, junto con sus métricas de consumo), marcar el `AudioRecording` como `TRANSCRIBED`, y notificar al usuario con un resumen corto por Telegram. El mensaje de Telegram SHALL terminar, tras una línea en blanco, con una línea de métricas con el formato `🎙️ <m:ss> de audio · ⏱️ transcrito en <tiempo>`, donde `<tiempo>` se expresa en segundos (`14 s`) si es menor de un minuto y en minutos y segundos (`1 min 05 s`) en caso contrario. El comportamiento SHALL ser el mismo sea cual sea el `source` del audio (`telegram` o `app`). El fichero exportado SHALL nombrarse con `telegram_file_unique_id` y, si el audio no lo tiene, con su `content_hash`.

#### Scenario: Transcripción exitosa
- **WHEN** el handler procesa un `TranscribeAudioMessage` y la llamada al transcriptor tiene éxito
- **THEN** se crea una `Transcription` asociada al `AudioRecording`, el `AudioRecording` pasa a `TRANSCRIBED`, y se envía un mensaje de Telegram con el resultado

#### Scenario: Mensaje con métricas de la transcripción
- **WHEN** se transcribe con éxito un audio de 102 segundos y la llamada al transcriptor tarda 14.200 ms
- **THEN** el mensaje de Telegram termina con la línea `🎙️ 1:42 de audio · ⏱️ transcrito en 14 s`

#### Scenario: Audio subido desde la app
- **WHEN** el handler transcribe con éxito un `AudioRecording` con `source` `app` y sin identificadores de Telegram
- **THEN** la `Transcription` se exporta a `<content_hash>.txt`, el audio pasa a `TRANSCRIBED` y se envía el mismo mensaje de Telegram que para un audio de origen `telegram`

### Requirement: Reintentos con backoff y fallo definitivo
El sistema SHALL reintentar automáticamente `TranscribeAudioMessage` un número limitado de veces con backoff si la transcripción falla. Al agotar los reintentos, SHALL marcar el `AudioRecording` como `ERROR` con `error_code`/`error_message`, y SHALL notificar al usuario por Telegram con un mensaje de tres líneas: «No se pudo transcribir la nota ❌», el origen (`📱 <device_name>` en los audios de la app, `📱 App` si no tienen dispositivo y `💬 Telegram` en los de Telegram) y `🕐 <dd/mm> <HH:MM> · <m:ss>` con el día y la hora de `received_at` en la zona horaria local y la duración. En los audios de Telegram el aviso SHALL enviarse como respuesta al mensaje original y MUST enviarse igualmente si ese mensaje ya no existe.

#### Scenario: Fallo agotando reintentos
- **WHEN** todas las tentativas de transcripción de un `TranscribeAudioMessage` fallan (según el `retry_strategy` configurado)
- **THEN** el `AudioRecording` correspondiente pasa a `ERROR` con `error_code` (clave corta) y `error_message` (frase descriptiva en castellano, no solo una clave) describiendo la causa, y se notifica al usuario por Telegram con el aviso de fallo

#### Scenario: Fallo transitorio con reintento posterior exitoso
- **WHEN** la primera tentativa de transcripción falla pero un reintento posterior tiene éxito
- **THEN** el `AudioRecording` termina en `TRANSCRIBED`, no en `ERROR`, y no se envía la notificación de fallo

#### Scenario: Aviso de un audio de la app
- **WHEN** se agotan los reintentos de un audio de la app con `device_name` «Google Pixel 7 (Android 16)», recibido el 2026-10-15 a las 03:02 UTC y de 14 segundos
- **THEN** el aviso dice «No se pudo transcribir la nota ❌», «📱 Google Pixel 7 (Android 16)» y «🕐 15/10 05:02 · 0:14», y no responde a ningún mensaje

#### Scenario: Aviso de un audio de la app sin dispositivo
- **WHEN** se agotan los reintentos de un audio de la app sin `device_name`
- **THEN** la línea de origen del aviso es «📱 App»

#### Scenario: Aviso de un audio de Telegram
- **WHEN** se agotan los reintentos de un audio de Telegram
- **THEN** la línea de origen del aviso es «💬 Telegram» y el aviso se envía como respuesta a su `telegram_message_id`, permitiendo el envío aunque el mensaje original ya no exista

### Requirement: Logging estructurado en JSON
El sistema SHALL registrar los eventos relevantes del pipeline (chat rechazado, fallo de intento de transcripción, fallo definitivo tras agotar reintentos) en formato JSON con contexto estructurado (claves como `event`, `audio_recording_id`, `telegram_file_unique_id`, `error_code`, `error_message`), no solo como texto libre, para poder investigarlos en Kibana.

#### Scenario: Fallo definitivo queda trazable
- **WHEN** un `TranscribeAudioMessage` agota los reintentos y el `AudioRecording` pasa a `ERROR`
- **THEN** se escribe una entrada de log en JSON con, al menos, `event`, `audio_recording_id`, `error_code` y `error_message` como campos estructurados (no embebidos solo en el texto del mensaje)

### Requirement: Comando para registrar el webhook
El sistema SHALL proveer un comando `app:telegram:set-webhook` que registra la URL del webhook (incluyendo el token) en la API de Telegram.

#### Scenario: Registrar webhook
- **WHEN** se ejecuta `bin/console app:telegram:set-webhook`
- **THEN** el comando llama a la API de Telegram (`setWebhook`) con la URL pública configurada y confirma el resultado

### Requirement: Interruptor de envíos salientes a Telegram
El sistema SHALL enviar mensajes a Telegram solo si `TELEGRAM_NOTIFICATIONS_ENABLED` es verdadera. Si no está definida, SHALL valer `true`. Con `false`, todo mensaje saliente (avisos programados, resumen diario, notificaciones de transcripción o fallo y respuestas del webhook) SHALL omitirse sin llamar a la API de Telegram, y SHALL registrarse un log `info` con `event: telegram.message_skipped`, `telegram_chat_id` y `telegram_text_length`, sin el texto del mensaje. La descarga de audios (`getFile` y descarga del fichero) MUST NOT verse afectada. El entorno `dev` SHALL tenerla a `false` por defecto (`.env.dev`).

#### Scenario: Envíos activados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `true` o no está definida y se envía un mensaje
- **THEN** el sistema llama a `sendMessage` de la API de Telegram como hasta ahora

#### Scenario: Envíos desactivados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `false` y se envía un mensaje (p. ej. el aviso de día sin audios)
- **THEN** el sistema no hace ninguna petición a la API de Telegram y registra `telegram.message_skipped` con `telegram_chat_id` y `telegram_text_length`

#### Scenario: Descarga de audios con envíos desactivados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `false` y se descarga un audio recibido
- **THEN** `getFile` y la descarga del fichero siguen llamando a Telegram con normalidad

