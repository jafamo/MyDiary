## MODIFIED Requirements

### Requirement: Generación bajo demanda desde la web
El sistema SHALL permitir pedir la generación (o regeneración) del `DailySummary` del día actual desde la vista Diario y desde la API, sin esperar al disparo programado de las 21:00. La petición SHALL encolar un `GenerateDailySummaryMessage` en el transporte asíncrono y responder sin esperar a la generación. El worker SHALL generar el resumen con la misma lógica de generación, reintentos, guardado y notificaciones que el disparo programado, y SHALL omitir la espera por `AudioRecording` `PENDING`, generando con las transcripciones `TRANSCRIBED` disponibles al procesar el mensaje. Si hoy no hay transcripciones `TRANSCRIBED`, la petición SHALL rechazarse sin encolar nada. Si ya hay una generación de hoy en `QUEUED` o `RUNNING` que no está atascada, la petición SHALL devolver ese estado sin encolar otra.

#### Scenario: Generación manual encolada
- **WHEN** se pide la generación de hoy y hay transcripciones `TRANSCRIBED` para el día actual
- **THEN** la petición termina sin llamar al generador, la generación de hoy queda en `QUEUED` y se despacha un `GenerateDailySummaryMessage` con la fecha de hoy

#### Scenario: El worker genera el resumen
- **WHEN** el worker procesa un `GenerateDailySummaryMessage` de hoy y existen `AudioRecording` `PENDING` del día
- **THEN** genera (o regenera) el `DailySummary` de hoy con las transcripciones ya disponibles, sin esperar a los pendientes, y la generación queda en `DONE`

#### Scenario: Petición repetida mientras se genera
- **WHEN** se pide la generación de hoy y ya hay una en `QUEUED` o `RUNNING` pedida hace menos de 15 minutos
- **THEN** no se despacha otro mensaje y se devuelve el estado de la generación en curso

#### Scenario: Sin transcripciones hoy
- **WHEN** se pide la generación de hoy y no hay ninguna transcripción `TRANSCRIBED` del día
- **THEN** la petición se rechaza, no se despacha ningún mensaje y no se crea ni modifica el estado de la generación

## ADDED Requirements

### Requirement: Estado de la generación del resumen diario
El sistema SHALL guardar, en una fila por día (`daily_summary_generation`), el estado de la última generación del resumen de ese día: `QUEUED` (pedida bajo demanda y pendiente del worker), `RUNNING`, `DONE` o `FAILED`, con `requested_at`, `started_at`, `finished_at`, `error_code` y `error_message`. `DailySummaryService` SHALL actualizarlo en toda generación que tenga transcripciones, sea bajo demanda, programada, del recheck o del comando: `RUNNING` al empezar, `DONE` al guardar el resumen y `FAILED` con el código y el mensaje del fallo si se agotan los intentos. Si al procesar una petición `QUEUED` ya no hay transcripciones, SHALL quedar `FAILED` con `error_code` `NO_TRANSCRIPTIONS`. Una generación en `QUEUED` o `RUNNING` cuyo `requested_at` tiene más de 15 minutos SHALL considerarse atascada: se presenta como `FAILED` con `error_code` `STALLED` y admite una petición nueva.

#### Scenario: Generación programada correcta
- **WHEN** el disparo de las 21:00 genera el resumen de hoy
- **THEN** la generación de hoy queda en `DONE` con `started_at` y `finished_at`

#### Scenario: Generación fallida
- **WHEN** todos los intentos de generar el resumen de hoy fallan
- **THEN** la generación de hoy queda en `FAILED` con el `error_code` y el `error_message` del último intento, y el `DailySummary` existente (si lo hay) no cambia

#### Scenario: Generación atascada
- **WHEN** la generación de hoy lleva en `RUNNING` desde una petición de hace 20 minutos
- **THEN** se presenta como `FAILED` con `error_code` `STALLED` y una petición nueva encola otra generación

#### Scenario: Sin transcripciones en una generación programada
- **WHEN** el disparo de las 21:00 se ejecuta y hoy no hay transcripciones
- **THEN** no se crea ninguna fila de estado para hoy

### Requirement: Log de la petición de generación
Cada petición de generación bajo demanda aceptada SHALL registrar un log `info` con `event` `daily_summary.generation_requested`, `daily_summary_date` (`AAAA-MM-DD`) y `daily_summary_request_source` (`web` o `api`). Una petición que devuelve una generación ya en curso MUST NOT registrarlo.

#### Scenario: Petición desde la API
- **WHEN** se encola una generación desde `POST /api/v1/diario/resumen`
- **THEN** se registra `daily_summary.generation_requested` con `daily_summary_request_source` `api`
