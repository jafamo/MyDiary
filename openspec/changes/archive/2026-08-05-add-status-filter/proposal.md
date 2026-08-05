## Why

Diario e Historial muestran todos los audios del día mezclados sin importar su estado (`PENDING`/`TRANSCRIBED`/`ERROR`), y Estadísticas no permite aislar las métricas de un solo estado. Cuando hay varios audios en `ERROR` es incómodo localizarlos entre el resto, y no hay forma de ver, por ejemplo, cómo ha evolucionado en el tiempo la tasa de fallos.

## What Changes

- Diario e Historial ganan un filtro por estado (Todos / Pendiente / Transcrito / Error) sobre el log de entradas del día, con el mismo patrón de `filter-pill` ya usado en Estadísticas (enlaces `GET`, sin JS obligatorio).
- Estadísticas gana el mismo filtro por estado, combinable con el rango de fechas ya existente. Al seleccionar un estado concreto, se recalculan sobre ese estado: la serie "audios por día", la media de audios/día, y la duración media. El desglose de estados (`status_counts`) y el ranking de temas **no** se filtran por estado — no son métricas por-audio-individual (el desglose ya muestra la distribución completa, y los temas vienen de `DailySummary`, no de `AudioRecording`).
- `AudioRecordingRepository` gana un parámetro opcional de estado en `findAllReceivedOn()`, `countByDateInRange()` y `averageDurationInRange()`.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `web-views`: los requisitos "Vista Diario con mini-dashboard", "Vista Historial con calendario" y "Vista Estadísticas con filtro de rango y gráfico" ganan la capacidad de filtrar por estado.

## Impact

- Código: `src/Repository/AudioRecordingRepository.php`, `src/Controller/DiarioController.php`, `src/Controller/HistorialController.php`, `src/Controller/EstadisticasController.php`, `templates/diario/index.html.twig`, `templates/historial/index.html.twig`, `templates/estadisticas/index.html.twig`.
- BD: sin cambios de esquema.
