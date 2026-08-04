## Why

Es el paso 4 del orden de construcción de `Especificaciones.md` (sección 9): con el webhook y la transcripción ya en pie, toca el resumen diario automático — la pieza que convierte los audios sueltos del día en algo consultable de un vistazo (flujo 3.3).

## What Changes

- Instalar `symfony/scheduler` (previsto en el stack, no instalado todavía) y configurar un disparador diario a las 21:00 `Europe/Madrid` que ejecuta el comando `app:generate-daily-summary` (vía `RunCommandMessage` nativo de Scheduler, tal como describe `Especificaciones.md`: "Symfony Scheduler dispara el comando").
- Añadir `DailySummaryService::generateForDate()`: encapsula la lógica completa del flujo 3.3 (espera corta si hay `AudioRecording` `PENDING` del día, recoge transcripciones `TRANSCRIBED` del día, llama a Ollama, guarda/actualiza `DailySummary` y sus `Topic`, maneja errores).
- Añadir `GenerateDailySummaryCommand` (`app:generate-daily-summary`), que delega en `DailySummaryService` — invocable tanto por el Scheduler como manualmente (útil para pruebas/backfill).
- Añadir `SummaryGeneratorInterface` + `OllamaSummaryGenerator` (implementación contra Ollama, API compatible OpenAI, `OLLAMA_BASE_URL` ya prevista) — el segundo punto de extensión ya anticipado en `CLAUDE.md` junto a `TranscriberInterface`.
- Manejo de errores: reintentos cortos síncronos contra Ollama; si siguen fallando, log estructurado (Monolog JSON, mismo patrón que el change anterior), no se crea `DailySummary` ese día, y se notifica *"No se pudo generar el resumen de hoy ⚠️"* por Telegram. Sin reintento automático al día siguiente.

## Capabilities

### New Capabilities
- `daily-summary-generation`: generación programada (y bajo demanda) del resumen diario y sus temas a partir de las transcripciones del día, vía Ollama.

### Modified Capabilities
- `docker-infrastructure`: `diary-messenger-worker` pasa a consumir también el transporte del Scheduler (`scheduler_default`), no solo `async` — necesario para que el disparo diario de las 21:00 se ejecute realmente.

(sin cambios en `data-model`, `telegram-audio-pipeline`, `symfony-application-bootstrap`, `code-style-enforcement` ni `automated-testing`)

## Impact

- Ficheros nuevos: `src/Contract/SummaryGeneratorInterface.php`, `src/Contract/SummaryGenerationException.php`, `src/Service/Ollama/OllamaSummaryGenerator.php`, `src/Service/DailySummaryService.php`, `src/Command/GenerateDailySummaryCommand.php`, `config/packages/scheduler.yaml` (o configuración equivalente en PHP), tests correspondientes.
- Ficheros modificados: `composer.json` (`symfony/scheduler`), `.env`/`.env.example` (`OLLAMA_BASE_URL` ya prevista, se confirma valor real `192.168.4.200:11434`; nueva `OLLAMA_MODEL`), `src/Repository/AudioRecordingRepository.php` y `src/Repository/DailySummaryRepository.php` (finders por fecha).
- No incluye: vistas Twig que muestren el resumen (paso 5), ni edición manual del resumen — solo la generación automática/bajo demanda.
