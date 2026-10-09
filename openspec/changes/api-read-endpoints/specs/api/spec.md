## ADDED Requirements

### Requirement: Formas de respuesta compartidas
Todos los endpoints de la API SHALL representar cada recurso con la misma forma JSON:

- **Audio:** `id`, `status`, `source`, `duration_seconds`, `received_at`, `error_code`, `error_message` y `transcription` (`null` si aún no existe), con `id`, `content`, `edited_manually`, `model`, `processing_ms`, `created_at` y `updated_at`.
- **Resumen:** `id`, `date`, `summary_text`, `generated_at`, `emoji_legend`, `topics` (lista de `id` y `name`), `model`, `prompt_tokens`, `completion_tokens` y `generation_ms`.
- **Recordatorio:** `id`, `date`, `time` (`HH:MM` o `null`), `text`, `created_at` y `updated_at`.

La API MUST NOT exponer rutas de ficheros, embeddings, el hash de contenido ni los identificadores de Telegram.

#### Scenario: Audio transcrito
- **WHEN** un endpoint devuelve un audio en estado `TRANSCRIBED`
- **THEN** el audio lleva `transcription` con su `content` y `edited_manually`, y `error_code` y `error_message` a `null`

#### Scenario: Audio pendiente
- **WHEN** un endpoint devuelve un audio en estado `PENDING`
- **THEN** `transcription` es `null`

#### Scenario: Audio con error
- **WHEN** un endpoint devuelve un audio en estado `ERROR`
- **THEN** `error_code` y `error_message` llevan la causa y `transcription` es `null`

#### Scenario: Sin datos internos
- **WHEN** un endpoint devuelve un audio subido desde la app
- **THEN** el JSON no contiene `file_path`, `content_hash`, `embedding` ni ningún identificador de Telegram

### Requirement: Validación de parámetros de consulta
Los endpoints de lectura SHALL responder `422` con `code` `validation_failed` cuando un parámetro de consulta está presente y no es válido: `status` distinto de `PENDING`, `TRANSCRIBED` o `ERROR`; un día que no sea una fecha real en formato `AAAA-MM-DD`; un mes que no tenga el formato `AAAA-MM`; `page` menor que 1; `per_page` fuera de 1–100; o un valor no admitido en un parámetro enumerado. Un parámetro ausente SHALL tomar su valor por defecto (`page` 1, `per_page` 20).

#### Scenario: Estado desconocido
- **WHEN** se pide `GET /api/v1/diario?status=HECHO`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Fecha imposible
- **WHEN** se pide `GET /api/v1/historial/2026-02-30`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Paginación fuera de rango
- **WHEN** se pide `GET /api/v1/resumenes?per_page=500`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Página más allá de la última
- **WHEN** se pide `GET /api/v1/resumenes?page=999` y hay 3 resúmenes
- **THEN** la respuesta es `200` con `items` vacío y `total` `3`

#### Scenario: Lectura sin token
- **WHEN** se pide `GET /api/v1/diario` sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized`

### Requirement: Consulta del diario de hoy
El sistema SHALL exponer `GET /api/v1/diario`, que devuelve el día de hoy en la zona horaria de la aplicación: `date`, `entries` (audios recibidos hoy, con el filtro opcional `status`), `summary` (resumen de hoy o `null`), `streak` (`current` y `best`), `week` (`total` y `delta` respecto a la semana anterior) y `top_topic` (`name` y `count` del tema más frecuente del mes, o `null`).

#### Scenario: Día con audios y resumen
- **WHEN** hoy hay dos audios transcritos y un resumen, y se pide `GET /api/v1/diario`
- **THEN** la respuesta es `200`, `entries` tiene los dos audios con su transcripción y `summary` lleva el texto del resumen y sus temas

#### Scenario: Día vacío
- **WHEN** hoy no hay audios ni resumen
- **THEN** la respuesta es `200` con `entries` vacío y `summary` `null`

#### Scenario: Filtro por estado
- **WHEN** hoy hay un audio transcrito y otro en error, y se pide `GET /api/v1/diario?status=ERROR`
- **THEN** `entries` contiene solo el audio en error

### Requirement: Consulta del historial
El sistema SHALL exponer `GET /api/v1/historial`, que para el mes indicado en `month` (`AAAA-MM`; por defecto el mes actual) devuelve `month`, `previous_month`, `next_month` y `days`: una lista, ordenada por fecha, con los días de ese mes que tienen audios o resumen, cada uno con `date`, `audio_count` y `has_summary`. El sistema SHALL exponer `GET /api/v1/historial/{fecha}`, que devuelve `date`, `entries` (audios de ese día, con el filtro opcional `status`) y `summary` (o `null`).

#### Scenario: Mes con actividad
- **WHEN** en octubre de 2026 hay tres audios el día 5 y un resumen ese mismo día, y se pide `GET /api/v1/historial?month=2026-10`
- **THEN** `days` contiene `{"date": "2026-10-05", "audio_count": 3, "has_summary": true}` y no contiene días sin audios ni resumen

#### Scenario: Meses contiguos
- **WHEN** se pide `GET /api/v1/historial?month=2026-01`
- **THEN** `previous_month` es `2025-12` y `next_month` es `2026-02`

#### Scenario: Día concreto
- **WHEN** se pide `GET /api/v1/historial/2026-10-05`
- **THEN** la respuesta es `200` con los audios recibidos ese día y su resumen

#### Scenario: Día sin nada
- **WHEN** se pide `GET /api/v1/historial/2020-01-01` y ese día no tiene audios ni resumen
- **THEN** la respuesta es `200` con `entries` vacío y `summary` `null`

### Requirement: Consulta de resúmenes
El sistema SHALL exponer `GET /api/v1/resumenes`, listado paginado de resúmenes diarios del más reciente al más antiguo, donde cada elemento es el resumen más `audio_count` (audios recibidos ese día). Los parámetros opcionales `from` y `to` (`AAAA-MM-DD`, ambos inclusive) SHALL limitar el listado a ese rango; SHALL enviarse los dos o ninguno, y `from` MUST NOT ser posterior a `to`.

#### Scenario: Listado sin rango
- **WHEN** hay 25 resúmenes y se pide `GET /api/v1/resumenes`
- **THEN** la respuesta lleva los 20 más recientes en `items`, `page` `1`, `per_page` `20` y `total` `25`

#### Scenario: Rango de fechas
- **WHEN** se pide `GET /api/v1/resumenes?from=2026-10-01&to=2026-10-07`
- **THEN** `items` solo contiene resúmenes con `date` entre esos dos días

#### Scenario: Rango incompleto o invertido
- **WHEN** se pide `GET /api/v1/resumenes?from=2026-10-07` o `?from=2026-10-07&to=2026-10-01`
- **THEN** la respuesta es `422` con `code` `validation_failed`

### Requirement: Búsqueda
El sistema SHALL exponer `GET /api/v1/busqueda?q=`, que devuelve `query`, `results` y `reminders`. `results` SHALL ser la búsqueda semántica en transcripciones y resúmenes fusionada por cercanía, donde cada elemento lleva `type` (`transcription` o `daily_summary`), `distance`, `date` y el recurso encontrado (`audio` o `summary`; el otro a `null`). `reminders` SHALL ser la búsqueda textual en recordatorios. `q` es obligatorio y MUST NOT estar vacío. Si la generación del embedding falla, `results` SHALL ir vacío y `reminders` SHALL devolverse igualmente.

#### Scenario: Resultados de los dos tipos
- **WHEN** se pide `GET /api/v1/busqueda?q=dentista` y hay una transcripción y un resumen cercanos
- **THEN** `results` contiene un elemento `transcription` con su `audio` y uno `daily_summary` con su `summary`, ordenados por `distance` ascendente

#### Scenario: Recordatorios por texto
- **WHEN** existe un recordatorio con el texto «Dentista a las 10» y se pide `GET /api/v1/busqueda?q=dentista`
- **THEN** `reminders` contiene ese recordatorio

#### Scenario: Consulta vacía
- **WHEN** se pide `GET /api/v1/busqueda` sin `q` o con `q` en blanco
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Fallo del embedding
- **WHEN** el generador de embeddings falla durante la búsqueda
- **THEN** la respuesta es `200` con `results` vacío y los recordatorios coincidentes en `reminders`

### Requirement: Consulta de estadísticas
El sistema SHALL exponer `GET /api/v1/estadisticas`, que devuelve `range`, `from`, `to`, las métricas del rango (series diarias de audios y de recordatorios, totales, medias, días con resumen, recuento por estado, frecuencia de temas, rachas, día récord y comparativa con el periodo anterior) y `ai_usage` con `totals` y `days`. `range` SHALL admitir `15`, `30` (por defecto), `90`, `365` y `custom`; con `custom` SHALL exigir `from` y `to` válidos con `from` no posterior a `to`. El filtro opcional `status` SHALL aplicarse como en la vista web. Todas las claves SHALL ir en `snake_case`.

#### Scenario: Rango por defecto
- **WHEN** se pide `GET /api/v1/estadisticas`
- **THEN** `range` es `30`, `to` es hoy, y `series` tiene 30 elementos con `date` y `value`

#### Scenario: Rango personalizado
- **WHEN** se pide `GET /api/v1/estadisticas?range=custom&from=2026-10-01&to=2026-10-07`
- **THEN** `from` es `2026-10-01`, `to` es `2026-10-07` y `total_days` es `7`

#### Scenario: Rango personalizado incompleto
- **WHEN** se pide `GET /api/v1/estadisticas?range=custom&from=2026-10-01`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Consumo de IA en snake_case
- **WHEN** se pide `GET /api/v1/estadisticas`
- **THEN** `ai_usage.totals` tiene las claves `prompt_tokens`, `completion_tokens`, `total_tokens`, `avg_tokens_per_summary`, `summaries_with_tokens`, `audio_seconds`, `whisper_ms` y `ollama_ms`

### Requirement: Consulta de recordatorios
El sistema SHALL exponer `GET /api/v1/recordatorios`, listado paginado de recordatorios con uno de estos filtros, excluyentes entre sí: `scope=upcoming` (por defecto; de hoy en adelante, orden ascendente), `scope=history` (anteriores a hoy, orden descendente), `month=AAAA-MM` (los de ese mes) o `date=AAAA-MM-DD` (los de ese día). Combinar `scope`, `month` y `date` SHALL responder `422`.

El sistema SHALL exponer `GET /api/v1/recordatorios/proximos`, que devuelve el aviso de recordatorios cercanos: `count` (recordatorios en los próximos 5 días, hoy incluido), `level` (`urgent` si el más cercano es hoy o mañana, `upcoming` en otro caso, `null` si no hay ninguno), `nearest_date` y `reminders` (los del día más cercano).

#### Scenario: Próximos por defecto
- **WHEN** hay un recordatorio ayer y otro mañana, y se pide `GET /api/v1/recordatorios`
- **THEN** `items` contiene solo el de mañana y `total` es `1`

#### Scenario: Históricos
- **WHEN** se pide `GET /api/v1/recordatorios?scope=history` con un recordatorio ayer y otro mañana
- **THEN** `items` contiene solo el de ayer

#### Scenario: Recordatorios de un mes
- **WHEN** se pide `GET /api/v1/recordatorios?month=2026-10`
- **THEN** `items` contiene los recordatorios con fecha en octubre de 2026 y ninguno de otro mes

#### Scenario: Filtros combinados
- **WHEN** se pide `GET /api/v1/recordatorios?scope=history&month=2026-10`
- **THEN** la respuesta es `422` con `code` `validation_failed`

#### Scenario: Aviso urgente
- **WHEN** hay dos recordatorios mañana y uno dentro de cuatro días, y se pide `GET /api/v1/recordatorios/proximos`
- **THEN** `count` es `3`, `level` es `urgent`, `nearest_date` es mañana y `reminders` contiene los dos de mañana

#### Scenario: Sin recordatorios cercanos
- **WHEN** no hay recordatorios en los próximos 5 días
- **THEN** `count` es `0`, `level` y `nearest_date` son `null` y `reminders` está vacío

### Requirement: Consulta de temas
El sistema SHALL exponer `GET /api/v1/topics`, que devuelve todos los temas en `items`, sin paginar, cada uno con `id`, `name`, `usage_count` (resúmenes que lo usan) y `last_used` (día del resumen más reciente, o `null`), ordenados por uso descendente y nombre, junto con `total`.

#### Scenario: Temas con y sin uso
- **WHEN** existe un tema usado en dos resúmenes y otro sin usar, y se pide `GET /api/v1/topics`
- **THEN** el primero aparece antes, con `usage_count` `2` y su `last_used`, y el segundo con `usage_count` `0` y `last_used` `null`
