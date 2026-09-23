## Context

- La transcripción la hace `WhisperTranscriber` contra `POST /api/v1/audio/transcriptions` de Open WebUI. La respuesta solo trae `text`, sin tokens ni modelo real, y el modelo se envía fijo como `whisper-1`. `TranscribeAudioMessageHandler` llama al transcriptor, crea la `Transcription` y avisa por Telegram. Los reintentos los gestiona Messenger, así que cada intento es una ejecución distinta del handler.
- El resumen lo genera `OllamaSummaryGenerator` contra `POST /v1/chat/completions` (compatible con OpenAI). La respuesta trae `usage.prompt_tokens`, `usage.completion_tokens`, `usage.total_tokens` y `model`. Hoy solo se registra `prompt_tokens` en un log (`daily_summary.prompt_tokens`). `DailySummaryService::generateWithRetries()` hace los reintentos dentro del mismo proceso.
- `AudioRecording.durationSeconds` ya existe, así que la duración del audio no hay que capturarla.
- El cambio `summary-prompt-file` (publicado en la 0.11.0) sigue sin archivar y modifica los mismos requisitos (`DailySummary`, notificación por Telegram). Las deltas de este cambio parten de su versión.

## Goals / Non-Goals

**Goals:**
- Guardar en BD las métricas de cada transcripción (tiempo de proceso y modelo) y de cada resumen (tokens de entrada y salida, tiempo y modelo).
- Mostrarlas en Telegram, en el Diario, en Resúmenes y en una sección "Consumo IA" de Estadísticas, siguiendo la maqueta aprobada.

**Non-Goals:**
- Tokens de los embeddings (`OllamaEmbeddingGenerator`). Son baratos y no los ha pedido el usuario.
- Coste económico, porque los modelos son locales.
- Rellenar métricas de registros antiguos.
- Contar tokens del texto transcrito con un tokenizador propio.

## Decisions

**1. Columnas en las entidades existentes, sin entidad `AiUsage` aparte.**
Hay una sola métrica por transcripción y por resumen, con relación 1:1, así que lo natural son columnas nullable en `Transcription` y `DailySummary`. Una tabla de eventos de uso sería más general, pero no hace falta por ahora (regla del proyecto: no anticipar patrones). El total de tokens no se guarda porque es `prompt + completion`, y tampoco la velocidad, que es `durationSeconds / processing_ms`.

**2. Medir el tiempo en quien orquesta la llamada, no en el adaptador HTTP.**
- En `TranscribeAudioMessageHandler` se envuelve `transcribe()` con `hrtime(true)`. Como cada reintento de Messenger es una ejecución nueva del handler, lo que se guarda es solo el intento que tuvo éxito.
- En `DailySummaryService::generateWithRetries()` se mide cada intento y se devuelve la duración del que tuvo éxito junto con el resultado.
- *Alternativa descartada:* medir dentro de `WhisperTranscriber` y `OllamaSummaryGenerator`. Habría que ampliar la firma de `TranscriberInterface` para devolver un objeto resultado, y cada implementación futura tendría que repetir la medición.

**3. Cómo llegan los tokens y el modelo desde los adaptadores.**
- `SummaryGeneratorInterface::generate()` amplía su array de retorno con `usage: array{promptTokens: ?int, completionTokens: ?int, model: string}`. Ya devuelve un array con forma, así que es el cambio más pequeño y coherente. `OllamaSummaryGenerator` toma el modelo de `data.model` y, si no viene, de `$ollamaModel`. El log `daily_summary.prompt_tokens` se mantiene, porque lo exige la spec de `summary-prompt-file`.
- `TranscriberInterface` añade `getModel(): string`. `WhisperTranscriber` pasa a leer el modelo de un parámetro (`WHISPER_MODEL`, por defecto `whisper-1`) en vez de tenerlo fijo en el código, y lo devuelve en este método. *Alternativa descartada:* un DTO de resultado para `transcribe()`. Obligaría a tocar todos los tests y dobles del transcriptor por un único dato que es constante.

**4. Formateo compartido entre Twig y Telegram.**
Un único servicio, `UsageFormatter`, con `duration(int $ms): string` (`14 s` / `1 min 05 s`), `number(int): string` (separador `.`) y `speed(int $audioSeconds, int $ms): string` (`7,3×`). Lo usan el handler, `DailySummaryService` y una extensión Twig con filtros `|ai_duration`, `|ai_speed` y `|ai_number`. Así la web y Telegram muestran exactamente lo mismo, y el formato se prueba una vez.

**5. Agregados de Estadísticas con SQL agrupado por día.**
En `DailySummaryRepository` y `AudioRecordingRepository` se añaden métodos que devuelven, para el rango, filas por día con `SUM(prompt_tokens)`, `SUM(completion_tokens)`, `SUM(generation_ms)`, `SUM(processing_ms)`, `SUM(duration_seconds)` y `COUNT(*)` de audios transcritos. Los días se agrupan con `Europe/Madrid`, como en el resto de Estadísticas. El controlador rellena los días vacíos y calcula los tiles. Las medias usan solo registros con valor no nulo, porque `SUM`/`AVG` de SQL ya ignoran los `NULL`. La sección no aplica el filtro de estado, ya que solo tienen sentido los audios transcritos.

**6. Gráfico.**
Barras apiladas en SVG renderizadas en Twig, igual que el gráfico actual de Estadísticas, sin librerías nuevas. Se colorean con los tokens CSS existentes (`--accent` para la entrada y `--accent-dusk` para la salida) y llevan "Ver como tabla", reutilizando el patrón del gráfico de audios.

## Risks / Trade-offs

- [Open WebUI ignora el `model` que enviamos y usa el Whisper que tenga configurado, así que `whisper-1` puede no reflejar el modelo real] → Se guarda lo que se configura en `WHISPER_MODEL`. Si el usuario quiere el nombre real, basta con poner ese valor en `.env`.
- [El tiempo de proceso incluye red y cola de Open WebUI/Ollama, no solo la inferencia] → Es aceptable: lo que interesa es el tiempo de espera real. Se documenta como "tiempo de proceso".
- [Si Ollama cambia de modelo, las cifras de tokens no son comparables entre días] → Se guarda el modelo por resumen, así que la diferencia se puede ver.
- [Doble cambio abierto sobre la spec `daily-summary-generation`] → Archivar `summary-prompt-file` antes de archivar este.

## Migration Plan

1. Una migración Doctrine añade las columnas nullable (`transcription.processing_ms`, `transcription.model`, `daily_summary.prompt_tokens`, `daily_summary.completion_tokens`, `daily_summary.generation_ms`, `daily_summary.model`). No toca datos y se aplica en caliente.
2. Añadir `WHISPER_MODEL=whisper-1` a `.env` / `.env.example`.
3. Rollback: basta con la migración `down()` (elimina las columnas). Los datos de métricas se pierden, pero no afecta al resto.

## Open Questions

- Ninguna bloqueante.
