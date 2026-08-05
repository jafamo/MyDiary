## Context

`AudioRecordingRepository` ya tiene `findAllReceivedOn($date)`, `countByDateInRange($from, $to)` y `averageDurationInRange($from, $to)`, todos sin filtro de estado. `entry_log.html.twig` es el partial compartido por Diario e Historial (sin distinción entre ellos desde el change anterior). Estadísticas ya tiene un patrón de filtro por `GET` con `filter-pill` para el rango de fechas (`app_estadisticas?range=...`), totalmente funcional sin JS.

`AudioRecordingStatus` es un enum de 3 valores: `PENDING`, `TRANSCRIBED`, `ERROR`.

## Goals / Non-Goals

**Goals:**
- Filtrar por estado el log de entradas en Diario e Historial.
- Filtrar por estado las métricas por-audio de Estadísticas (serie diaria, media de audios/día, duración media), combinable con el rango de fechas.
- Reutilizar el patrón `filter-pill` ya existente, coherente en las tres vistas.

**Non-Goals:**
- No se filtran por estado el desglose `status_counts` ni el ranking de temas en Estadísticas — el primero pierde sentido (mostraría 100% de un solo estado), el segundo no es una métrica por-estado-de-audio (viene de `DailySummary`/`Topic`, no de `AudioRecording`).
- No se filtran por estado el mini-dashboard de Diario (racha, total semanal, tema del mes) ni el calendario de Historial (marcado de días con/sin resumen) — son agregados de otro tipo, fuera del alcance pedido.
- No se persiste el filtro entre sesiones (cookie/localStorage) — cada vista parte sin filtro (`Todos`) por defecto, como ya ocurre con el rango de Estadísticas.

## Decisions

### 1. Parámetro `status` en query string, valores del enum + `ALL`
`?status=ERROR` (o `PENDING`/`TRANSCRIBED`); ausente o `ALL` = sin filtro. Se valida contra `AudioRecordingStatus::tryFrom()`; un valor inválido se trata como `ALL` (mismo criterio que Estadísticas ya usa para un `range` inválido: recae en el valor por defecto sin error).

### 2. `AudioRecordingRepository` gana `?AudioRecordingStatus $status = null` en los 3 métodos afectados
`findAllReceivedOn(\DateTimeImmutable $date, ?AudioRecordingStatus $status = null)`, `countByDateInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?AudioRecordingStatus $status = null)`, `averageDurationInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, ?AudioRecordingStatus $status = null)`. `null` (por defecto) mantiene el comportamiento actual sin filtrar — parámetro opcional, sin romper las llamadas existentes (Diario ya llama a `findAllReceivedOn($today)` para el mini-dashboard vía otros métodos que no cambian).

Alternativa descartada: añadir métodos nuevos `findAllReceivedOnByStatus()` duplicando la query — se descarta por duplicar lógica de filtrado por fecha ya existente; un parámetro opcional es más simple.

### 3. Diario/Historial: filtro sobre el partial compartido, vía controlador
`DiarioController` y `HistorialController` leen `status` de la query string, lo validan, y pasan `entries`/`selected_entries` ya filtradas al partial (el partial en sí no cambia — sigue recibiendo una lista de `entries`, ahora potencialmente más corta). Los `filter-pill` de estado se añaden encima del log en cada vista, con enlaces que preservan el resto de la query string relevante (en Historial, `year`/`month`/`date`).

### 4. Estadísticas: mismo `status` combinado con `range`/`from`/`to` ya existentes
Los links de estado (`filter-pill`) y el formulario de rango personalizado deben preservar mutuamente sus parámetros (cambiar de estado no debe perder el rango elegido, y viceversa). Se logra pasando siempre ambos grupos de parámetros en cada enlace/formulario (patrón ya usado para el rango, se extiende con `status`).

## Risks / Trade-offs

- **[Trade-off] Un filtro de estado activo en Estadísticas puede dar una media de duración o un gráfico "vacíos" para rangos sin audios en ese estado** — aceptable, mismo comportamiento que ya tiene la vista con rangos sin datos (tiles a 0, gráfico plano).
- **[Riesgo] Romper llamadas existentes a los métodos del repositorio modificados** — mitigado con parámetro opcional al final de la firma y valor por defecto `null` que preserva el comportamiento actual; se ejecuta el suite completo tras el cambio.

## Migration Plan

No aplica (funcionalidad nueva, sin cambios de esquema). Pasos:
1. Añadir el parámetro opcional de estado a los 3 métodos del repositorio, con tests de regresión (comportamiento sin filtro no cambia) y tests nuevos (comportamiento con filtro).
2. Filtro en Diario e Historial (controlador + plantilla).
3. Filtro en Estadísticas (controlador + plantilla), combinado con el rango.
4. Verificación manual en navegador.

## Open Questions

Ninguna bloqueante.
