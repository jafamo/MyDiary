## MODIFIED Requirements

### Requirement: Entidad `AudioRecording`
El sistema SHALL definir una entidad Doctrine `AudioRecording` con los campos de `Especificaciones.md` sección 6: `telegram_message_id` (nullable, unique), `telegram_file_unique_id` (nullable, unique), `source` (enum `telegram`/`app`, obligatorio), `content_hash` (SHA-256 en hexadecimal, nullable, unique), `file_path`, `received_at`, `status` (enum `PENDING`/`TRANSCRIBED`/`ERROR`), `duration_seconds`, `error_code` (nullable), `error_message` (nullable), `device_name` (hasta 100 caracteres, nullable). Los audios de origen `telegram` SHALL tener los dos identificadores de Telegram; los de origen `app` SHALL tener `content_hash` y no tener identificadores de Telegram. `device_name` SHALL ser el nombre del dispositivo del token que subió el audio, copiado y no como relación (el token puede revocarse), y SHALL quedar a `null` en los audios de Telegram.

#### Scenario: Constraints únicos aplicados
- **WHEN** se inspecciona el esquema de `audio_recording`
- **THEN** existen constraints `UNIQUE` sobre `telegram_message_id`, `telegram_file_unique_id` y `content_hash`

#### Scenario: Varios audios sin identificadores de Telegram
- **WHEN** se persisten dos `AudioRecording` de origen `app` con `content_hash` distintos y sin identificadores de Telegram
- **THEN** ambos se guardan sin violar ninguna constraint

#### Scenario: Audios anteriores marcados como de Telegram
- **WHEN** se aplica la migración sobre una base de datos con audios existentes
- **THEN** las filas existentes quedan con `source` `telegram` y `content_hash` a `null`, sin error

#### Scenario: Audios anteriores sin dispositivo
- **WHEN** se aplica la migración de `device_name` sobre una base de datos con audios existentes
- **THEN** las filas existentes quedan con `device_name` a `null`, sin error
