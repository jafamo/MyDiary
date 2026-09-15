## Context

`DailySummaryService::generateForDate()` ya centraliza generación + guardado + notificación Telegram + embedding, y se invoca desde tres sitios: el Scheduler de las 21:00, la consola (`app:generate-daily-summary --date=`) y el botón "Generar resumen" de Diario. Ninguno de los tres detecta por sí solo si han llegado audios *después* de la última generación del día — simplemente regeneran con lo que haya en ese momento, y siempre reenvían por Telegram aunque el texto no cambie.

`Schedule.php` ya usa Symfony Scheduler (`RecurringMessage::cron`) con `stateful()` + `processOnlyLastMissedRun(true)` para dos disparos (21:00 resumen, 8:00 recordatorios). Este cambio añade un tercer disparo al mismo mecanismo, no un cron de sistema operativo distinto.

## Goals / Non-Goals

**Goals:**
- Detectar, entre 21:00 y 00:30 `Europe/Madrid`, si han llegado audios transcritos después de la última generación del `DailySummary` del día.
- Regenerar y reenviar el resumen automáticamente solo cuando hay contenido nuevo que resumir.
- No reenviar el resumen por Telegram cuando no hay transcripciones nuevas (evitar spam de mensajes idénticos cada 15 min).
- Reutilizar la lógica de generación existente (`DailySummaryService::generateForDate`) sin duplicarla.

**Non-Goals:**
- No cambia el comportamiento del disparo de las 21:00 ni el de la generación manual desde la web.
- No introduce un cron de sistema operativo aparte del Scheduler de Symfony.
- No cubre audios recibidos después de las 00:30 (quedan para el resumen del día siguiente o para regeneración manual).
- No cambia el esquema de `daily_summary` ni añade nuevas columnas: `generatedAt` ya existe y es la referencia temporal suficiente.

## Decisions

### 1. Nuevo comando `app:recheck-daily-summary`, no reutilizar `app:generate-daily-summary` directamente
El comando existente siempre regenera y siempre notifica. Añadir un flag de "solo si hay novedades" a `GenerateDailySummaryCommand` mezclaría dos políticas de notificación distintas en el mismo comando. Un comando nuevo y pequeño, que decide si delega en `DailySummaryService::generateForDate()`, mantiene cada comando con una responsabilidad clara.

**Alternativa descartada**: añadir un `--only-if-new` a `GenerateDailySummaryCommand`. Descartada porque complica un comando que hoy es simple y ya está bien testeado.

### 2. Criterio de "hay novedades": `AudioRecording.receivedAt > DailySummary.generatedAt`
Se añade `AudioRecordingRepository::existsTranscribedReceivedAfter(\DateTimeImmutable $date, \DateTimeImmutable $after): bool`, que reutiliza los mismos límites de día que `findTranscribedReceivedOn` (vía `DateRange::dayBoundaries`) y añade `receivedAt > :after`. Si no existe `DailySummary` para el día (p. ej. porque el disparo de las 21:00 falló, ver requirement "Manejo de errores en la generación"), se considera que hay novedades si existe *cualquier* transcripción del día — el `after` en ese caso no se aplica.

**Alternativa descartada**: comparar contra `updatedAt` de `AudioRecording` (cuándo pasó a `TRANSCRIBED`) en vez de `receivedAt`. Descartada porque `receivedAt` ya es el campo usado por todo el resto del flujo de resumen diario (`findTranscribedReceivedOn`) y no introduce un campo nuevo que mantener.

### 3. El comando delega en `DailySummaryService::generateForDate($date, waitForPending: false)`
Igual que la generación bajo demanda desde la web, el recheck tardío no debe esperar por `PENDING` (ya está fuera de la ventana natural del disparo de las 21:00). Esto reutiliza tal cual el guardado, reintentos, notificación de fallo, cabecera con fecha y regeneración de embedding ya especificados en `daily-summary-generation`.

### 4. Nueva entrada en `Schedule.php`, cron cada 15 min entre 21:00 y 00:30 `Europe/Madrid`
Cron no permite expresar directamente un rango que cruza medianoche en una sola expresión con el paso deseado, así que se usan dos `RecurringMessage::cron` (mismo mecanismo `stateful`/`processOnlyLastMissedRun` ya en uso):
- `'*/15 21,22,23 * * *'` (21:00–23:45)
- `'0,15,30 0 * * *'` (00:00–00:30)

### 5. Idempotencia y solapamiento
`generateForDate` ya es seguro de re-ejecutar (actualiza el `DailySummary` existente en vez de duplicar, ver "Re-ejecución actualiza en vez de duplicar"). El `recheck` añade una comprobación previa barata (una query `EXISTS`) para no invocar Ollama ni reenviar Telegram si no hace falta. No se necesita locking adicional: `stateful()` + `processOnlyLastMissedRun(true)` en el Scheduler ya evitan que se acumulen ejecuciones perdidas, y una ejecución del recheck tarda lo mismo que la query `EXISTS` cuando no hay novedades (sub-segundo), muy por debajo del intervalo de 15 min.

## Risks / Trade-offs

- **[Riesgo] Ollama tarda y se solapan dos ejecuciones del recheck** (p. ej. una regeneración de >15 min) → Mitigación: mismo patrón que ya usa `Schedule.php` (`stateful`, `processOnlyLastMissedRun`); si ocurriera, `generateForDate` sigue siendo idempotente (actualiza, no duplica) y como mucho se reenvía el resumen una vez de más — no un fallo de datos.
- **[Riesgo] Coste de Ollama**: hasta 14 comprobaciones adicionales por noche, pero solo generan carga real en Ollama cuando hay novedades (la comprobación en sí es una query SQL barata) → Mitigación: ninguna adicional necesaria, el coste ya es proporcional al uso real.
- **[Trade-off] Ventana fija 21:00–00:30**: audios después de las 00:30 no se recogen hasta el resumen del día siguiente o una regeneración manual → aceptado explícitamente como Non-Goal.

## Migration Plan

- Sin migración de BD. Despliegue estándar: mergear, desplegar, el nuevo cron del Scheduler empieza a aplicar esa misma noche.
- Rollback: revertir el commit que añade las entradas a `Schedule.php` (o comentar esas dos líneas) desactiva el recheck sin afectar al resto del flujo de resumen diario.

## Open Questions

(ninguna pendiente)
