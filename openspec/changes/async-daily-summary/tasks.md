## 1. Modelo de datos

- [ ] 1.1 Enum `DailySummaryGenerationStatus`, entidad `DailySummaryGeneration` (con `STALL_AFTER_MINUTES` e `isStalled()`) y su repositorio (`findOneByDate`)
- [ ] 1.2 Migración que crea `daily_summary_generation` con `date` única
- [ ] 1.3 Tests unitarios de `isStalled()`

## 2. Generación y estado

- [ ] 2.1 `DailySummaryService::generateForDate()` registra `RUNNING`, `DONE`, `FAILED` y `NO_TRANSCRIPTIONS` según el diseño
- [ ] 2.2 `GenerateDailySummaryMessage`, su handler (error inesperado → `FAILED` `UNEXPECTED` + `UnrecoverableMessageHandlingException`) y routing a `async`
- [ ] 2.3 `DailySummaryGenerationRequester` (`NoTranscriptionsToSummarizeException`, idempotencia, atasco, log `daily_summary.generation_requested`)
- [ ] 2.4 Tests: estados en `DailySummaryServiceTest`, handler, requester (encola, repetida, atascada, sin transcripciones, log)

## 3. API

- [ ] 3.1 `SummaryGenerationPresenter` y esquema `SummaryGeneration` en `nelmio_api_doc.yaml`
- [ ] 3.2 `POST /api/v1/diario/resumen` (`202` / `409`) y `summary_generation` en `GET /api/v1/diario`, con atributos OpenAPI
- [ ] 3.3 Tests funcionales: aceptada, repetida, sin transcripciones, sin token, estado en el diario (null, `QUEUED`, `FAILED`, `STALLED`)

## 4. Web

- [ ] 4.1 `DailySummaryController` usa el requester (flash sin transcripciones) y nueva ruta `GET /diario/resumen/estado`
- [ ] 4.2 Plantilla de Diario: aviso en curso / fallido, botón desactivado o «Reintentar», flash; `public/js/diario.js` con el sondeo
- [ ] 4.3 Tests funcionales: el botón encola y redirige, sin transcripciones, vista en curso y fallida, estado con y sin sesión

## 5. Documentación y verificación

- [ ] 5.1 `make openapi`, colección Postman y `doc/api.http`
- [ ] 5.2 `AGENTS.md` (restricción de Messenger), `Especificaciones.md` (decisiones, flujo del resumen, 3.7, modelo de datos) y `ROADMAP.md` (fase 7, bloqueo 5, D4)
- [ ] 5.3 `make cs-check`, `make phpstan` y `make test` en verde
