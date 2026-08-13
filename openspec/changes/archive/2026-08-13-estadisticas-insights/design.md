## Context

`EstadisticasController` ya resuelve un rango `[$from, $to]` (preset o personalizado) y un filtro de estado opcional, y calcula `$series` (audios por día vía `countByDateInRange`), `$avgAudiosPerDay`, `$avgDurationSeconds`, `$daysWithSummary`, `$statusCounts` y `$topicFrequency`. Los cuatro indicadores nuevos se apoyan en esos mismos datos, salvo la comparación con el periodo anterior, que necesita una segunda consulta sobre un rango previo de igual duración.

## Goals / Non-Goals

**Goals:**
- Calcular racha actual/mejor racha, día récord y comparación con periodo anterior reutilizando la lógica y repositorios existentes.
- Mantener el comportamiento ya definido: todo se recalcula al cambiar rango o filtro de estado, igual que el resto del dashboard.
- Resolver de forma explícita los casos límite (rango sin audios, sin periodo anterior con datos) sin romper la vista.

**Non-Goals:**
- No se añade caché, vistas nuevas, ni un endpoint de API separado — todo vive en el mismo `GET /estadisticas`.
- No se persiste la racha ni el día récord en BD; se calculan al vuelo sobre el rango consultado (igual que el resto de agregados de esta vista).
- No se compara contra "el mismo periodo el año pasado" ni otras variantes — solo el periodo inmediatamente anterior de igual duración.

## Decisions

- **Racha (streak)**: se calcula recorriendo `$series` (ya ordenada por fecha) en memoria, sin query adicional. Racha actual = tramo final de días consecutivos con `value > 0` contando desde el último día del rango hacia atrás; si el último día del rango tiene `value == 0`, la racha actual es 0. Mejor racha = la subsecuencia más larga de días consecutivos con `value > 0` dentro de `$series`. Alternativa descartada: calcularlo con SQL (ventanas/gaps-and-islands) — innecesario dado que `$series` ya está en memoria y el rango máximo son 366 días.
- **Día récord**: `max` sobre `$series` por `value`; en empate, se toma el primero cronológicamente (más antiguo). Si `$series` está vacía o todos los valores son 0, no se muestra el tile (o se muestra en estado vacío explícito).
- **Periodo anterior**: rango previo = `[$from - $totalDays días, $from - 1 día]`, es decir, misma duración exacta, inmediatamente antes de `$from`. Se reutiliza `AudioRecordingRepository::countByDateInRange()` con el mismo `$status` para mantener coherencia con el filtro de estado activo. Alternativa descartada: comparar contra "el mes anterior" de forma fija — no encaja con rangos personalizados ni con los presets de 15/90/365 días.
- **Manejo de rango sin días previos con datos** (p. ej. el primer rango de vida de la app): si el periodo anterior no tiene ningún audio, no se calcula porcentaje (división por cero) — se muestra el tile sin comparación (p. ej. "Sin datos del periodo anterior") en vez de un valor engañoso.
- **Ubicación de la lógica**: todo el cálculo (racha, récord, comparación) vive en `EstadisticasController` como métodos privados, sin extraer un servicio nuevo — no hay una segunda necesidad real de reutilizar esta lógica en otro sitio todavía (regla del CLAUDE.md de no anticipar abstracciones).

## Risks / Trade-offs

- [Cálculo de racha/récord en PHP sobre `$series` en vez de SQL] → Aceptable: el rango máximo soportado es 366 días (preset "1 año"), coste de recorrerlo en memoria es despreciable.
- [Query adicional para el periodo anterior duplica el coste de `countByDateInRange`] → Aceptable: mismo patrón de query ya usado para el rango principal, sin N+1 ni impacto relevante a esta escala (proyecto personal de un usuario).
- [Ambigüedad en "racha" cuando el rango seleccionado no llega hasta hoy, p. ej. un rango personalizado en el pasado] → Mitigación: la "racha actual" se define siempre relativa al último día del rango (`$to`), no a la fecha de hoy, para que el dato tenga sentido también en rangos personalizados históricos.
