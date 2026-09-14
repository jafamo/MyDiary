## Why

El resumen diario se genera a las 21:00 con las transcripciones disponibles hasta ese momento. Si el usuario envía notas de voz después de esa hora (algo habitual, ya que el día no ha terminado), esos audios quedan fuera del resumen enviado y no hay ningún mecanismo que lo detecte ni lo corrija: el usuario tendría que acordarse de pulsar "Regenerar resumen" manualmente en Diario.

## What Changes

- Nuevo comando `app:recheck-daily-summary` que, para el día en curso, compara la fecha de generación del `DailySummary` (`generatedAt`) con la de las `AudioRecording` `TRANSCRIBED` recibidas ese día: si hay alguna transcripción posterior a `generatedAt` (o no existe `DailySummary` porque el disparo de las 21:00 falló), regenera el resumen reutilizando `DailySummaryService::generateForDate` (sin esperar por `PENDING`, igual que la generación bajo demanda desde la web) y lo reenvía por Telegram. Si no hay transcripciones nuevas, no hace nada (no reenvía el resumen sin cambios).
- Nueva entrada en `Schedule.php` (Symfony Scheduler, mismo mecanismo ya usado para el disparo de las 21:00 y el de recordatorios de las 8:00 — no se introduce cron de sistema operativo aparte) que ejecuta `app:recheck-daily-summary` cada 15 minutos entre las 21:00 y las 00:30 `Europe/Madrid`.
- El comando es idempotente y seguro de ejecutar repetidamente: solo regenera y reenvía cuando detecta transcripciones nuevas desde la última generación.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `daily-summary-generation`: se añade un disparo periódico adicional (21:00–00:30, cada 15 min) que detecta audios transcritos posteriores al último resumen del día y lo regenera/reenvía automáticamente; el disparo programado de las 21:00 y la generación manual desde la web no cambian su comportamiento existente.

## Impact

- Código: nuevo `src/Command/RecheckDailySummaryCommand.php`; nuevo método en `DailySummaryService` (o repositorio) para saber si hay `AudioRecording` `TRANSCRIBED` posteriores a `generatedAt`; nueva entrada en `src/Schedule.php`.
- Repositorio: `AudioRecordingRepository` necesita una consulta tipo "existe transcrito recibido después de X para el día Y".
- Sin cambios de esquema de BD ni de infraestructura (reutiliza Symfony Scheduler y Messenger ya configurados).
- Tests: nuevo test de comando (`tests/Command/RecheckDailySummaryCommandTest.php`) y de la nueva lógica de detección en `DailySummaryService`/repositorio.
