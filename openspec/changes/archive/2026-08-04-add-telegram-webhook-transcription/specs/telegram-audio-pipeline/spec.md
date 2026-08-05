## ADDED Requirements

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
El sistema SHALL, al procesar `TranscribeAudioMessage`, llamar al servicio de transcripción configurado (`TranscriberInterface`), guardar el resultado en `Transcription` (contenido en BD y export a fichero), marcar el `AudioRecording` como `TRANSCRIBED`, y notificar al usuario con un resumen corto por Telegram.

#### Scenario: Transcripción exitosa
- **WHEN** el handler procesa un `TranscribeAudioMessage` y la llamada al transcriptor tiene éxito
- **THEN** se crea una `Transcription` asociada al `AudioRecording`, el `AudioRecording` pasa a `TRANSCRIBED`, y se envía un mensaje de Telegram con el resultado

### Requirement: Reintentos con backoff y fallo definitivo
El sistema SHALL reintentar automáticamente `TranscribeAudioMessage` un número limitado de veces con backoff si la transcripción falla. Al agotar los reintentos, SHALL marcar el `AudioRecording` como `ERROR` con `error_code`/`error_message`, y SHALL notificar al usuario *"No se pudo transcribir este audio ❌"*.

#### Scenario: Fallo agotando reintentos
- **WHEN** todas las tentativas de transcripción de un `TranscribeAudioMessage` fallan (según el `retry_strategy` configurado)
- **THEN** el `AudioRecording` correspondiente pasa a `ERROR` con `error_code` (clave corta) y `error_message` (frase descriptiva en castellano, no solo una clave) describiendo la causa, y se notifica al usuario *"No se pudo transcribir este audio ❌"*

#### Scenario: Fallo transitorio con reintento posterior exitoso
- **WHEN** la primera tentativa de transcripción falla pero un reintento posterior tiene éxito
- **THEN** el `AudioRecording` termina en `TRANSCRIBED`, no en `ERROR`, y no se envía la notificación de fallo

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
