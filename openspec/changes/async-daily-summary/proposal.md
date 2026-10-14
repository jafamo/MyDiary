## Why

El resumen bajo demanda se genera dentro de la petición: hasta 3 intentos contra Ollama, de hasta 120 s cada uno, mientras nginx corta a los 130 s (bloqueo 5 del `ROADMAP.md`). En la web el botón deja la página colgada y, si Ollama tarda, la petición se corta aunque el resumen acabe guardándose. En el móvil sería peor. Es la fase 7 del `ROADMAP.md`, la siguiente tras la escritura por la API.

## What Changes

- **Decisión D4 (opción A):** la generación bajo demanda pasa a ser asíncrona por Symfony Messenger, con un mensaje `GenerateDailySummaryMessage` en la cola `async` (Redis) que ya consume `diary-messenger-worker`. **Amplía la restricción de arquitectura** «Messenger solo para Telegram → transcripción»: se actualizan `AGENTS.md` y `Especificaciones.md`.
- Nueva tabla `daily_summary_generation` (una fila por día) con el estado de la última generación del día: `QUEUED`, `RUNNING`, `DONE` o `FAILED`, cuándo se pidió, empezó y terminó, y el motivo del fallo. La actualiza `DailySummaryService` en todas las generaciones (bajo demanda, 21:00, recheck y comando), así que la app también ve si falló la de las 21:00.
- Una generación `QUEUED` o `RUNNING` pedida hace más de 15 minutos se considera atascada (worker caído o reiniciado): se muestra como `FAILED` con `error_code` `STALLED` y admite una petición nueva.
- `POST /api/v1/diario/resumen`: encola la generación del resumen de hoy y responde `202` con el estado. Si ya hay una en curso, responde `202` con esa misma sin encolar otra. Si hoy no hay transcripciones, `409 conflict`.
- `GET /api/v1/diario` añade `summary_generation` (estado de la generación de hoy o `null`).
- Web: el botón «Generar resumen» usa el mismo mecanismo y vuelve al instante. Mientras se genera, Diario muestra «Generando el resumen…» con el botón desactivado y un JS mínimo consulta el estado en una ruta con sesión (`GET /diario/resumen/estado`) y recarga la página al terminar. Si falla, muestra el aviso con la hora y el botón «Reintentar». Sin transcripciones hoy, un aviso en lugar de no hacer nada.
- Las notificaciones de Telegram no cambian: resumen generado y «No se pudo generar el resumen de hoy ⚠️».
- Log `daily_summary.generation_requested` (info) con `daily_summary_date` y `daily_summary_request_source` (`web` / `api`).
- Documentación del endpoint: atributos OpenAPI, `doc/openapi.json`, colección Postman y `doc/api.http`.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `daily-summary-generation`: la generación bajo demanda pasa a ser asíncrona y el estado de cada generación queda guardado.
- `api`: nuevo `POST /api/v1/diario/resumen` y campo `summary_generation` en el diario de hoy.
- `web-views`: el botón de Diario encola la generación y la vista muestra el estado en curso o el fallo.

## Impact

- **Código nuevo:** entidad `DailySummaryGeneration` + enum de estado + repositorio, migración, `GenerateDailySummaryMessage` y su handler, servicio que pide la generación (`DailySummaryGenerationRequester`), presenter de la API, ruta web de estado y `public/js/diario.js`.
- **Código modificado:** `DailySummaryService` (registra el estado), `DailySummaryController` (web), `Api\DiarioController`, `templates/diario/index.html.twig`, `config/packages/messenger.yaml` (routing).
- **Documentación:** `AGENTS.md` y `Especificaciones.md` (restricción de Messenger, 3.x y 3.7, modelo de datos), `ROADMAP.md` (fase 7, bloqueo 5), `doc/`.
- **Despliegue:** migración nueva (`make migrate`). `make deploy` ya reinicia `diary-messenger-worker`, que así carga el handler nuevo.
