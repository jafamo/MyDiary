## Purpose

Entidades Doctrine que forman el modelo de dominio de la aplicación (registros de audio, transcripciones, resúmenes diarios, temas y usuarios) y sus migraciones versionadas.
## Requirements
### Requirement: Entidad `AudioRecording`
El sistema SHALL definir una entidad Doctrine `AudioRecording` con los campos de `Especificaciones.md` sección 6: `telegram_message_id` (nullable, unique), `telegram_file_unique_id` (nullable, unique), `source` (enum `telegram`/`app`, obligatorio), `content_hash` (SHA-256 en hexadecimal, nullable, unique), `file_path`, `received_at`, `status` (enum `PENDING`/`TRANSCRIBED`/`ERROR`), `duration_seconds`, `error_code` (nullable), `error_message` (nullable). Los audios de origen `telegram` SHALL tener los dos identificadores de Telegram; los de origen `app` SHALL tener `content_hash` y no tener identificadores de Telegram.

#### Scenario: Constraints únicos aplicados
- **WHEN** se inspecciona el esquema de `audio_recording`
- **THEN** existen constraints `UNIQUE` sobre `telegram_message_id`, `telegram_file_unique_id` y `content_hash`

#### Scenario: Varios audios sin identificadores de Telegram
- **WHEN** se persisten dos `AudioRecording` de origen `app` con `content_hash` distintos y sin identificadores de Telegram
- **THEN** ambos se guardan sin violar ninguna constraint

#### Scenario: Audios anteriores marcados como de Telegram
- **WHEN** se aplica la migración sobre una base de datos con audios existentes
- **THEN** las filas existentes quedan con `source` `telegram` y `content_hash` a `null`, sin error

### Requirement: Entidad `Transcription`
El sistema SHALL definir una entidad Doctrine `Transcription` relacionada 1:1 con `AudioRecording` (`onDelete: CASCADE`), con campos `content`, `file_path`, `edited_manually` (default false), `processing_ms` (entero, nullable), `model` (string, nullable), `created_at`, `updated_at`.

#### Scenario: Borrado en cascada
- **WHEN** se elimina un `AudioRecording` que tiene `Transcription` asociada
- **THEN** la `Transcription` asociada se elimina automáticamente por la constraint `onDelete: CASCADE`

#### Scenario: Transcripciones anteriores sin métricas
- **WHEN** se aplica la migración sobre una base de datos con transcripciones existentes
- **THEN** las filas existentes quedan con `processing_ms` y `model` a `null`, sin error

### Requirement: Entidad `DailySummary`
El sistema SHALL definir una entidad Doctrine `DailySummary` con `date` (unique), `summary_text`, `generated_at`, `emoji_legend` (JSON, nullable: lista de `{emoji, meaning}`), `prompt_tokens` (entero, nullable), `completion_tokens` (entero, nullable), `generation_ms` (entero, nullable), `model` (string, nullable), y relación N:M con `Topic` a través de tabla pivote `daily_summary_topic`.

#### Scenario: Un resumen por día
- **WHEN** se intenta persistir dos `DailySummary` con la misma `date`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `date`

#### Scenario: Resumen sin leyenda
- **WHEN** existe un `DailySummary` creado antes de introducir la leyenda
- **THEN** su `emoji_legend` es `null` y el resto de sus datos no cambia

#### Scenario: Resúmenes anteriores sin métricas
- **WHEN** se aplica la migración sobre una base de datos con resúmenes existentes
- **THEN** las filas existentes quedan con `prompt_tokens`, `completion_tokens`, `generation_ms` y `model` a `null`, sin error

### Requirement: Entidad `Topic`
El sistema SHALL definir una entidad Doctrine `Topic` con `name` (unique).

#### Scenario: Nombre de tema único
- **WHEN** se intenta persistir dos `Topic` con el mismo `name`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `name`

### Requirement: Entidad `User`
El sistema SHALL definir una entidad Doctrine `User` (implementando `PasswordAuthenticatedUserInterface`/`UserInterface` de Symfony Security) con `username` (unique), `password_hash`, `roles` (json, p. ej. `["ROLE_USER"]`).

#### Scenario: Nombre de usuario único
- **WHEN** se intenta persistir dos `User` con el mismo `username`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `username`

### Requirement: Entidad `Reminder`
El sistema SHALL definir una entidad Doctrine `Reminder` con `date` (tipo `date`, sin constraint unique — puede haber varios recordatorios el mismo día), `text`, `created_at`, `updated_at`.

#### Scenario: Varios recordatorios el mismo día
- **WHEN** se persisten dos `Reminder` con la misma `date`
- **THEN** ambos se guardan correctamente, sin conflicto de constraint

### Requirement: Migraciones versionadas aplicadas
El sistema SHALL generar migraciones Doctrine versionadas para todas las entidades del modelo de dominio, incluyendo `Reminder`, y aplicarlas contra `diary-postgres`, en vez de usar `doctrine:schema:update` directo.

#### Scenario: Esquema aplicado
- **WHEN** se ejecuta `bin/console doctrine:migrations:status` dentro de `diary-php`
- **THEN** todas las migraciones generadas figuran como ejecutadas, incluyendo la de `reminder`

### Requirement: Entidad `ApiToken`
El sistema SHALL definir una entidad Doctrine `ApiToken` (tabla `api_token`) con `user` (relación N:1 con `User`, `onDelete: CASCADE`), `token_hash` (64 caracteres, unique), `name` (nombre del dispositivo, hasta 100 caracteres), `created_at` y `last_used_at` (nullable). La tabla MUST NOT guardar el token en claro.

#### Scenario: Hash único
- **WHEN** se inspecciona la migración generada para `api_token`
- **THEN** existe una constraint `UNIQUE` sobre `token_hash`

#### Scenario: Borrado en cascada con el usuario
- **WHEN** se elimina un `User` que tiene tokens
- **THEN** sus filas de `api_token` se eliminan por la constraint `onDelete: CASCADE`

