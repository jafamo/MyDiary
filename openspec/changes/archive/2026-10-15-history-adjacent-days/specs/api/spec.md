## MODIFIED Requirements

### Requirement: Consulta del historial
El sistema SHALL exponer `GET /api/v1/historial`, que para el mes indicado en `month` (`AAAA-MM`; por defecto el mes actual) devuelve `month`, `previous_month`, `next_month` y `days`: una lista, ordenada por fecha, con los días de ese mes que tienen audios o resumen, cada uno con `date`, `audio_count` y `has_summary`. El sistema SHALL exponer `GET /api/v1/historial/{fecha}`, que devuelve `date`, `entries` (audios de ese día, con el filtro opcional `status`), `summary` (o `null`), `previous_date` y `next_date`: el día más cercano anterior y posterior a `fecha` con audios o resumen, o `null` si no hay; no dependen de `status`.

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

#### Scenario: Días con actividad anterior y siguiente
- **WHEN** hay un audio el 2026-07-20 y un resumen el 2026-10-02, y se pide `GET /api/v1/historial/2026-09-15`
- **THEN** `previous_date` es `2026-07-20` y `next_date` es `2026-10-02`

#### Scenario: Sin actividad a un lado
- **WHEN** no hay audios ni resúmenes después de `fecha`
- **THEN** `next_date` es `null`
