## Context

`DailySummaryService::generateForDate()` hace hasta 3 intentos contra Ollama (timeout de 120 s cada uno, 5 s entre intentos) y, si acierta, guarda el resumen, genera su embedding y avisa por Telegram. Lo llaman el scheduler (21:00 y recheck, como `RunCommandMessage` en `diary-messenger-worker`), el comando y el botón de Diario, este último dentro de la petición web con `waitForPending: false`. nginx corta a los 130 s. Messenger ya tiene un transporte `async` en Redis consumido por el mismo worker que ejecuta el scheduler, uno detrás de otro.

## Goals / Non-Goals

**Goals:**
- Ninguna petición HTTP espera a Ollama.
- La web y la app ven si el resumen de hoy está en cola, generándose, hecho o fallido, también cuando falla el de las 21:00.
- Un único camino para pedir el resumen desde la web y la API.

**Non-Goals:**
- Pedir el resumen de otro día distinto de hoy desde la web o la API (el comando `--date` sigue).
- Notificaciones push (fase 8) ni cambios en los mensajes de Telegram.
- Historial de generaciones: solo se guarda la última de cada día.
- Cancelar una generación en curso.

## Decisions

- **D4 = A, elegido por el usuario:** Messenger asíncrono. Se amplía la restricción en `AGENTS.md` («Messenger solo para lo async real: la cadena Telegram → transcripción y la generación del resumen bajo demanda; no como bus general»).
- **Estado en tabla propia, elegido por el usuario:** entidad `DailySummaryGeneration` (`daily_summary_generation`): `id`, `date` (única), `status` (enum `DailySummaryGenerationStatus`: `QUEUED`, `RUNNING`, `DONE`, `FAILED`), `requested_at`, `started_at`, `finished_at`, `error_code`, `error_message`. Una fila por día que se reescribe en cada generación. Columnas en `daily_summary` no sirven porque la primera generación del día no tiene fila, y Redis no se ve por SQL y se pierde al reiniciar.
- **Quién escribe el estado:** `DailySummaryService::generateForDate()` marca `RUNNING` cuando hay transcripciones, y después `DONE` o `FAILED` (con el `error_code`/`error_message` de `SummaryGenerationException`). Así se cubren todas las vías sin tocar el comando ni el scheduler. Sin transcripciones: si la fila de hoy estaba `QUEUED`, pasa a `FAILED` `NO_TRANSCRIPTIONS`; si no, no se crea fila (no hay nada que mostrar). `requested_at` en las generaciones programadas es el inicio.
- **Petición:** servicio `DailySummaryGenerationRequester::request(\DateTimeImmutable $date, string $source): DailySummaryGenerationRequest` (resultado con la generación y si se encoló). Comprueba que hay transcripciones (`NoTranscriptionsToSummarizeException` si no), devuelve la generación en curso si no está atascada, y si no la pone en `QUEUED`, limpia los datos anteriores, hace `flush`, despacha `GenerateDailySummaryMessage(date: 'AAAA-MM-DD')` y registra `daily_summary.generation_requested`. Lo usan `DailySummaryController` (web) y `Api\DiarioController`.
- **Atasco:** `DailySummaryGeneration::STALL_AFTER_MINUTES = 15` (el peor caso normal es ~6,5 min de Ollama más la cola) y `isStalled(\DateTimeImmutable $now)`. No se escribe nada al detectarlo: el presenter y la plantilla muestran `FAILED`/`STALLED`, y el requester permite una petición nueva. Si el worker se recupera tarde y termina, escribe `DONE` igualmente.
- **Handler `GenerateDailySummaryMessageHandler`:** llama a `generateForDate($date, waitForPending: false)`. Los fallos de Ollama ya los gestiona el servicio y no llegan como excepción. Ante un error inesperado marca `FAILED` con `error_code` `UNEXPECTED` y lanza `UnrecoverableMessageHandlingException` para que Messenger no reintente (cada reintento serían otros 3 intentos contra Ollama y otro aviso por Telegram).
- **Web:** `POST /diario/resumen` usa el requester y redirige a Diario. Sin transcripciones, un flash `summary_notice` que Diario muestra. La plantilla pinta el estado (en curso / fallido) con `data-summary-generation-status`, y `public/js/diario.js` (cargado solo en Diario, como `estadisticas.js`) consulta `GET /diario/resumen/estado` (`{"status": "..."}`, con sesión) cada 5 s mientras el estado sea `QUEUED` o `RUNNING` y recarga la página cuando cambia. Sin JS, el aviso se ve igual y basta recargar.
- **API:** `POST /api/v1/diario/resumen` → `202` con `SummaryGenerationPresenter::present()`; `409` sin transcripciones. `GET /api/v1/diario` añade `summary_generation`. Esquema `SummaryGeneration` en `nelmio_api_doc.yaml`.
- **Logs:** `daily_summary.generation_requested` con `daily_summary_date` y `daily_summary_request_source`, planos y con prefijo. Los fallos ya registran `daily_summary.generation_failed`.

## Risks / Trade-offs

- [El worker procesa en serie: una petición durante el disparo de las 21:00 (que puede esperar 3 min por pendientes) se queda en cola] → Aceptable. Si pasan 15 min, se marca atascada y se puede volver a pedir.
- [Reinicio del worker con un mensaje en curso] → El estado queda en `RUNNING` hasta el umbral de 15 min y luego aparece como `STALLED`. Redis conserva el mensaje sin confirmar y el worker podría reprocesarlo al arrancar, lo que reescribe el estado y el resumen sin daño.
- [Ampliar Messenger fuera de la transcripción] → Queda acotado y documentado: no se convierte en bus general.

## Migration Plan

Migración `Version2026101410000` que crea `daily_summary_generation`. En producción: `make migrate` y `make deploy` (reinicia `diary-messenger-worker`). Rollback: revertir el merge y `doctrine:migrations:migrate prev`; los mensajes `GenerateDailySummaryMessage` que queden en Redis sin handler se descartarían.
