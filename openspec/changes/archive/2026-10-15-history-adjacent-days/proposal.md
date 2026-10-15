## Why

En el Historial de la app (pantalla de un día) el usuario quiere saltar al día con actividad anterior o siguiente sin recorrer los días vacíos, que pueden ser meses. `GET /api/v1/historial/{fecha}` no da ese dato y `GET /api/v1/historial?month=` solo cubre un mes: la app tendría que pedir mes a mes hasta encontrarlo.

## What Changes

- `GET /api/v1/historial/{fecha}` añade `previous_date` y `next_date`: el día más cercano antes y después de `fecha` con audios o resumen (el mismo criterio que `days` del calendario), o `null` si no hay. No dependen del filtro `status`. Campos nuevos: no rompe a los clientes actuales.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `api`: el detalle del historial incluye el día con actividad anterior y el siguiente.

## Impact

- **Código modificado:** `Api\HistorialController::day`, `HistorialService` (nuevo `adjacentDays`), `AudioRecordingRepository` y `DailySummaryRepository` (día con actividad más cercano).
- **Documentación:** `doc/openapi.json` (regenerado), `Especificaciones.md` y `CHANGELOG.md`.
- Sin migración.
