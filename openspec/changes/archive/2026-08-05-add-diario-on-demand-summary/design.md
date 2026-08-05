## Context

`DailySummaryService::generateForDate(\DateTimeImmutable $date)` ya centraliza toda la lógica (esperar pendientes, recoger transcripciones, llamar a `SummaryGeneratorInterface` con reintentos, guardar/actualizar `DailySummary` + `Topic`, notificar fallo por Telegram) y hoy la usan dos disparadores: el `Scheduler` a las 21:00 y el comando manual `app:generate-daily-summary` (que acepta `--date`). `DiarioController` ya calcula `today` y pasa `daily_summary` a la plantilla; el panel de resumen solo se muestra `{% if daily_summary %}`.

El punto de fricción es `waitForPendingTranscriptions()`: hace un `sleep()` bloqueante de hasta ~3 minutos (`pendingWaitIntervalSeconds` × `pendingWaitMaxAttempts`) si hay `AudioRecording` `PENDING`. Eso es aceptable para un comando CLI/Scheduler de fondo, pero no para una petición HTTP síncrona disparada por un botón — bloquearía el worker de PHP-FPM y probablemente superaría el timeout del navegador/proxy.

## Goals / Non-Goals

**Goals:**
- Botón en Diario que genera (o regenera) el `DailySummary` del día actual sin esperar a las 21:00.
- Reutilizar `DailySummaryService::generateForDate()` sin duplicar su lógica de generación/reintentos/guardado.
- No bloquear la petición HTTP con el `sleep()` de espera por pendientes.

**Non-Goals:**
- No se añade el botón a Historial (días pasados) — el servicio ya soporta cualquier fecha vía el comando por consola si hiciera falta; la web solo cubre "hoy", que es el caso de uso pedido.
- No se usa Symfony Messenger para esto (restricción explícita del proyecto: solo para Telegram → transcripción). La generación sigue siendo síncrona dentro de la petición.
- No se añade sistema de flash messages / notificaciones en la UI para el caso de fallo — ya existe el aviso por Telegram (*"No se pudo generar el resumen de hoy ⚠️"*), consistente con el resto de fallos de la app.

## Decisions

### 1. `generateForDate()` gana un parámetro `waitForPending: bool = true`
`public function generateForDate(\DateTimeImmutable $date, bool $waitForPending = true): void`. Cuando es `false`, se salta `waitForPendingTranscriptions()` por completo y se genera inmediatamente con las transcripciones `TRANSCRIBED` que ya existan en ese momento. El comando por consola y el Scheduler siguen llamando sin este parámetro (usan el valor por defecto `true`, comportamiento sin cambios). El nuevo controlador web lo llama con `false`.

Alternativa descartada: crear un método nuevo `generateForDateNow()` duplicando el cuerpo — se descarta por duplicar lógica de generación/guardado que ya está bien encapsulada; un parámetro booleano es más simple y ya sigue el patrón del proyecto de "servicios con métodos claros, sin generalizar de más".

### 2. Nuevo `DailySummaryController::generate()`, `POST /diario/resumen`
Sigue el mismo patrón ya establecido por `AudioRecordingController::retry()` y `TranscriptionController::delete()`: sin `AbstractController`, CSRF manual vía `CsrfTokenManagerInterface` con `token_id` propio (`daily_summary_generate`), redirige a `Referer` tras completarse. No necesita parámetro de fecha en la URL — siempre opera sobre "hoy" (`DateRange::nowInMadrid()`), igual que hace `DiarioController` para calcular `today`.

### 3. Ejecución síncrona, sin límite artificial de tiempo
Sin la espera por pendientes, el peor caso pasa a ser `generationMaxAttempts` (3) × `generationRetryDelaySeconds` (5s) ≈ 15s más la llamada real a Ollama — aceptable de forma síncrona para un proyecto personal de un solo usuario. Si en el futuro esto resulta molesto, sería el momento de reconsiderar (no ahora, por regla general del proyecto de no anticipar complejidad).

## Risks / Trade-offs

- **[Riesgo] Doble disparo simultáneo (botón + Scheduler de las 21:00 solapados)**: `saveDailySummary()` ya hace upsert por fecha (`findOneByDate` + update si existe), así que el resultado final es el de la última ejecución en completarse; no se duplican filas. Aceptable sin locking adicional, mismo criterio que ya se aceptó para el reintento manual de transcripción.
- **[Riesgo] Generar el resumen antes de que terminen de llegar audios del día**: es un trade-off consciente y explícito del propio botón ("a demanda" implica que el usuario decide el momento, viendo en Diario si aún hay `PENDING`). Si luego llegan más audios, puede volver a pulsar el botón para regenerarlo.
- **[Trade-off] Sin feedback de progreso en la UI durante los ~15s**: no hay JS ni polling; el usuario ve la página recargarse tras la redirección. Igual de simple que el resto de acciones (retry/delete), coherente con "funciona sin JS".

## Migration Plan

No aplica (funcionalidad nueva, sin cambios de esquema). Pasos:
1. Añadir el parámetro `waitForPending` a `DailySummaryService::generateForDate()` con test de regresión sobre el comando/Scheduler (comportamiento sin cambios).
2. `DailySummaryController::generate()` con test.
3. Botón en `templates/diario/index.html.twig`.
4. Verificación manual en navegador.

## Open Questions

Ninguna bloqueante.
