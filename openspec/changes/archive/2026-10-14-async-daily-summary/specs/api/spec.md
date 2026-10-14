## ADDED Requirements

### Requirement: Petición del resumen de hoy por la API
El sistema SHALL exponer `POST /api/v1/diario/resumen`, sin cuerpo, que pide la generación del resumen de hoy según el requisito de generación bajo demanda y responde `202` con el estado de la generación (`summary_generation`). Si ya había una generación en curso no atascada, SHALL responder `202` con esa generación sin encolar otra. Si hoy no hay transcripciones, SHALL responder `409` con `code` `conflict`.

#### Scenario: Petición aceptada
- **WHEN** se envía `POST /api/v1/diario/resumen` y hay transcripciones de hoy
- **THEN** la respuesta es `202` con `status` `QUEUED` y `requested_at`, y se ha despachado un `GenerateDailySummaryMessage`

#### Scenario: Petición repetida
- **WHEN** se envía `POST /api/v1/diario/resumen` dos veces seguidas
- **THEN** las dos respuestas son `202` con el mismo `requested_at` y solo se ha despachado un mensaje

#### Scenario: Sin transcripciones
- **WHEN** se envía `POST /api/v1/diario/resumen` y hoy no hay transcripciones
- **THEN** la respuesta es `409` con `code` `conflict`

#### Scenario: Sin token
- **WHEN** se envía `POST /api/v1/diario/resumen` sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized` y no se despacha nada

### Requirement: Estado de la generación en el diario de hoy
`GET /api/v1/diario` SHALL incluir `summary_generation`: `null` si hoy no ha habido ninguna generación, o un objeto con `status` (`QUEUED`, `RUNNING`, `DONE` o `FAILED`), `requested_at`, `started_at`, `finished_at` (instantes UTC o `null`), `error_code` y `error_message`. Una generación atascada SHALL aparecer como `FAILED` con `error_code` `STALLED`.

#### Scenario: Sin generaciones hoy
- **WHEN** se pide `GET /api/v1/diario` y hoy no se ha pedido ni ejecutado ninguna generación
- **THEN** `summary_generation` es `null`

#### Scenario: Generación en cola
- **WHEN** se pide `GET /api/v1/diario` después de un `POST /api/v1/diario/resumen` aceptado y antes de que el worker lo procese
- **THEN** `summary_generation.status` es `QUEUED`

#### Scenario: Generación fallida
- **WHEN** la última generación de hoy falló
- **THEN** `summary_generation.status` es `FAILED` con su `error_code` y `error_message`
