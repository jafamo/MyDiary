## Purpose

Entidades Doctrine que forman el modelo de dominio de la aplicación (registros de audio, transcripciones, resúmenes diarios, temas y usuarios) y sus migraciones versionadas.

## Requirements

### Requirement: Entidad `AudioRecording`
El sistema SHALL definir una entidad Doctrine `AudioRecording` con los campos de `Especificaciones.md` sección 6: `telegram_message_id` (unique), `telegram_file_unique_id` (unique), `file_path`, `received_at`, `status` (enum `PENDING`/`TRANSCRIBED`/`ERROR`), `duration_seconds`, `error_code` (nullable), `error_message` (nullable).

#### Scenario: Constraints únicos aplicados
- **WHEN** se inspecciona la migración generada para `audio_recording`
- **THEN** existen constraints `UNIQUE` sobre `telegram_message_id` y `telegram_file_unique_id`

### Requirement: Entidad `Transcription`
El sistema SHALL definir una entidad Doctrine `Transcription` relacionada 1:1 con `AudioRecording` (`onDelete: CASCADE`), con campos `content`, `file_path`, `edited_manually` (default false), `created_at`, `updated_at`.

#### Scenario: Borrado en cascada
- **WHEN** se elimina un `AudioRecording` que tiene `Transcription` asociada
- **THEN** la `Transcription` asociada se elimina automáticamente por la constraint `onDelete: CASCADE`

### Requirement: Entidad `DailySummary`
El sistema SHALL definir una entidad Doctrine `DailySummary` con `date` (unique), `summary_text`, `generated_at`, y relación N:M con `Topic` a través de tabla pivote `daily_summary_topic`.

#### Scenario: Un resumen por día
- **WHEN** se intenta persistir dos `DailySummary` con la misma `date`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `date`

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
