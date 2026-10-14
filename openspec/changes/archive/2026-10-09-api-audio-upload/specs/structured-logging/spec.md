## ADDED Requirements

### Requirement: Log de subida de audio por la API
El endpoint de subida de audio SHALL registrar, por cada subida aceptada, una línea `info` en el canal `app` con `event` (`audio.uploaded`), `audio_recording_id`, `audio_source` (`app`), `audio_content_hash` y `audio_upload_result` (`created`, `duplicate` o `retrying`). Los campos SHALL ser planos y con prefijo `audio_`, sin usar `result` ni `status`.

#### Scenario: Audio nuevo
- **WHEN** se sube por la API un audio que no existía
- **THEN** se escribe una línea con `"event": "audio.uploaded"`, `"audio_source": "app"` y `"audio_upload_result": "created"`

#### Scenario: Audio duplicado
- **WHEN** se sube por la API un audio ya transcrito
- **THEN** se escribe una línea con `"audio_upload_result": "duplicate"` y el `audio_recording_id` del audio existente

### Requirement: Origen del audio en los logs de transcripción
Los logs del pipeline de transcripción (intento fallido, fallo definitivo y transcripción creada) SHALL incluir `audio_source` y SHALL incluir `telegram_file_unique_id` solo cuando el audio lo tiene.

#### Scenario: Fallo de un audio de la app
- **WHEN** falla un intento de transcripción de un audio con `source` `app`
- **THEN** la línea de log lleva `"audio_source": "app"` y no contiene la clave `telegram_file_unique_id`

#### Scenario: Fallo de un audio de Telegram
- **WHEN** falla un intento de transcripción de un audio con `source` `telegram`
- **THEN** la línea de log lleva `"audio_source": "telegram"` y su `telegram_file_unique_id`
