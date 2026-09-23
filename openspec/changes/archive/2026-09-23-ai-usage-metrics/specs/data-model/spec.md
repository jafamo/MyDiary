## MODIFIED Requirements

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
