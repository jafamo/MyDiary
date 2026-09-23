## Why

Hoy no hay forma de saber cuánto "cuesta" cada transcripción o cada resumen diario: solo se registra en logs el número de tokens de entrada del resumen (`daily_summary.prompt_tokens`), sin persistirlo ni mostrarlo. Guardar y mostrar estas métricas permite vigilar el consumo de los modelos locales (Whisper vía Open WebUI y Ollama), detectar resúmenes anormalmente caros o transcripciones lentas, y comparar modelos si se cambian.

## What Changes

- **Transcripciones**: al transcribir un audio se guarda en `Transcription` el tiempo de proceso (ms) de la llamada que tuvo éxito y el modelo usado. Whisper vía Open WebUI no devuelve tokens, así que la métrica de una transcripción es duración del audio (ya existente) + tiempo de proceso + velocidad (× tiempo real, derivada).
- **Resúmenes diarios**: al generar un resumen se guardan en `DailySummary` los tokens de entrada (`prompt_tokens`), tokens de salida (`completion_tokens`), el tiempo de generación (ms) y el modelo de Ollama usado. El total se deriva (entrada + salida).
- **Telegram**:
  - El mensaje "Transcripción lista ✅" añade una línea final con la duración del audio y el tiempo de proceso (p. ej. `🎙️ 1:42 de audio · ⏱️ transcrito en 14 s`).
  - La notificación del resumen diario añade una línea final con el número de audios, tokens (total con desglose entrada/salida), tiempo de generación y el modelo usado (p. ej. `🧮 5 audios · 3.412 tokens (2.980 entrada + 432 salida) · ⏱️ 38 s · 🤖 qwen2.5:7b`).
- **Web**:
  - Cada entrada transcrita del log del Diario muestra un pie con tiempo de proceso, velocidad y modelo.
  - Cada resumen (vista Resúmenes y panel de resumen del Diario) muestra tokens de entrada, salida y total, tiempo de generación y modelo.
  - Nueva sección **"Consumo IA"** en Estadísticas, sobre el rango de fechas seleccionado: tiles (tokens totales con desglose entrada/salida, media de tokens por resumen, minutos de audio transcrito, tiempo total de proceso Whisper/Ollama), gráfico de barras apiladas entrada/salida por día y tabla por día (audios, minutos de audio, proceso Whisper, tokens del resumen).
- Los registros anteriores a este cambio no tienen métricas: se muestran como "—" y se excluyen de medias; no hay backfill.
- Maqueta de referencia aprobada: https://claude.ai/artifact/PzhwTV65kv8dXsrsRHBKrU

## Capabilities

### New Capabilities
- `ai-usage-metrics`: captura y persistencia de métricas de consumo de IA (tiempo de proceso y modelo en transcripciones; tokens de entrada/salida, tiempo y modelo en resúmenes) y su presentación en la sección "Consumo IA" de Estadísticas.

### Modified Capabilities
- `data-model`: `Transcription` añade `processing_ms` y `model` (nullable); `DailySummary` añade `prompt_tokens`, `completion_tokens`, `generation_ms` y `model` (nullable).
- `telegram-audio-pipeline`: el mensaje de transcripción completada incluye duración del audio y tiempo de proceso.
- `daily-summary-generation`: la notificación por Telegram del resumen incluye audios, tokens, tiempo de generación y modelo.
- `web-views`: el log del Diario muestra las métricas de cada transcripción y el panel de resumen sus métricas; Estadísticas añade la sección "Consumo IA".
- `summaries-view`: cada resumen muestra sus métricas de tokens, tiempo y modelo.

## Impact

- **Entidades y migración**: `Transcription`, `DailySummary` + una migración Doctrine (columnas nullable, sin backfill).
- **Servicios**: `WhisperTranscriber` / `TranscribeAudioMessageHandler` (medición de tiempo y mensaje Telegram), `OllamaSummaryGenerator` (devolver `usage` y modelo en lugar de solo loguear), `SummaryGeneratorInterface` (el resultado incluye métricas opcionales), `DailySummaryService` (persistencia y mensaje Telegram).
- **Web**: `templates/_partials/entry_log.html.twig`, plantillas de Diario y Resúmenes, `EstadisticasController` + repositorios (agregados por día), `templates/estadisticas/index.html.twig`, `public/css/app.css`.
- **Docs**: `Especificaciones.md` (modelo de datos y flujos).
- Sin dependencias nuevas.
