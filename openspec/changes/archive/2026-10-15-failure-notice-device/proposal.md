## Why

Cuando falla la transcripción de un audio, el aviso de Telegram dice siempre «No se pudo transcribir este audio ❌», sin más. Con la app (subida por `POST /api/v1/audios`) ya hay dos orígenes y puede haber varios dispositivos: el aviso no permite saber qué nota ha fallado ni desde dónde se envió.

## What Changes

- `AudioRecording` guarda `device_name`: el nombre del dispositivo del token que subió el audio (copiado de `ApiToken.name`, para que no dependa de que el token siga existiendo). Nullable: vacío en los audios de Telegram y en los de la app anteriores a este cambio. Migración nueva.
- `POST /api/v1/audios` lo rellena al crear el audio y lo actualiza al relanzar uno en `ERROR` (`retrying`), igual que el webhook actualiza `telegram_message_id` al reenviar. Con `duplicate` no se toca. Sin cambios en el contrato de la API.
- El aviso de fallo definitivo pasa a tres líneas: título, origen (📱 nombre del dispositivo, o 📱 App si no se conoce; 💬 Telegram) y 🕐 día, hora local (`Europe/Madrid`) y duración. En los audios de Telegram, el aviso responde al mensaje original (si ya no existe, se envía igualmente).
- `TelegramClient::sendMessage` admite responder a un mensaje.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `data-model`: `AudioRecording` con `device_name`.
- `api`: la subida guarda el dispositivo del token.
- `telegram-audio-pipeline`: nuevo texto del aviso de fallo definitivo, con origen, hora y duración, y respuesta al mensaje original.

## Impact

- **Código modificado:** `AudioRecording`, `AudioRecordingService::receiveUpload`, `AudioUploadService::upload`, `Api\AudioController::upload`, `TranscriptionFailureListener`, `TelegramClient`.
- **Migración:** columna `audio_recording.device_name` (`varchar(100)`, nullable).
- **Documentación:** `Especificaciones.md` (flujo de fallo y tabla `audio_recording`) y `CHANGELOG.md`.
