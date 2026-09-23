## 1. Modelo de datos

- [ ] 1.1 Añadir `processingMs` (int, nullable) y `model` (string, nullable) a `Transcription` con getters/setters
- [ ] 1.2 Añadir `promptTokens`, `completionTokens`, `generationMs` (int, nullable) y `model` (string, nullable) a `DailySummary`, con getters/setters y un helper `getTotalTokens(): ?int`
- [ ] 1.3 Generar la migración Doctrine (columnas nullable, sin backfill) y aplicarla en dev y test
- [ ] 1.4 Tests de entidad: valores por defecto `null` y `getTotalTokens()` (null si falta alguno de los dos)

## 2. Formateo compartido

- [ ] 2.1 Crear `App\Service\UsageFormatter` con `duration(int $ms)` (`14 s` / `1 min 05 s`), `number(int)` (separador `.`) y `speed(int $audioSeconds, int $ms)` (`7,3×`)
- [ ] 2.2 Crear una extensión Twig con los filtros `ai_duration`, `ai_number` y `ai_speed` que delegan en `UsageFormatter` y devuelven `—` si reciben `null`
- [ ] 2.3 Tests unitarios de `UsageFormatter` (límites 59 s / 60 s, miles, velocidad con un decimal)

## 3. Transcripción

- [ ] 3.1 Añadir `getModel(): string` a `TranscriberInterface`; en `WhisperTranscriber`, leer el modelo de `WHISPER_MODEL` (binding en `config/services.yaml`, `whisper-1` en `.env` y `.env.example`) y usarlo también en la petición
- [ ] 3.2 En `TranscribeAudioMessageHandler`, medir con `hrtime(true)` la llamada a `transcribe()` y guardar `processingMs` y `model` en la `Transcription`
- [ ] 3.3 Añadir al mensaje de Telegram la línea `🎙️ <m:ss> de audio · ⏱️ transcrito en <tiempo>`
- [ ] 3.4 Actualizar los dobles de `TranscriberInterface` en los tests y añadir tests del handler: métricas guardadas y línea de Telegram (`🎙️ 1:42 de audio · ⏱️ transcrito en 14 s`)
- [ ] 3.5 Verificar que la edición manual (`TranscriptionController`) no toca las métricas (test)

## 4. Resumen diario

- [ ] 4.1 Ampliar el retorno de `SummaryGeneratorInterface::generate()` con `usage: array{promptTokens: ?int, completionTokens: ?int, model: string}`
- [ ] 4.2 En `OllamaSummaryGenerator`, devolver `usage` a partir de `usage.prompt_tokens`, `usage.completion_tokens` y `model` (usar `$ollamaModel` si falta `model`), sin romper si falta `usage`; mantener el log `daily_summary.prompt_tokens`
- [ ] 4.3 En `DailySummaryService::generateWithRetries()`, medir cada intento y devolver el `generationMs` del que tuvo éxito; guardar tokens, tiempo y modelo en `saveDailySummary()` (sustituyéndolos al regenerar)
- [ ] 4.4 Añadir al final de la notificación de Telegram la línea `🧮 <n> audios · <total> tokens (<entrada> entrada + <salida> salida) · ⏱️ <tiempo> · 🤖 <modelo>`, omitiendo la parte de tokens si falta
- [ ] 4.5 Tests: generador con y sin `usage`, persistencia en creación y regeneración, y línea de Telegram en los dos casos de la spec

## 5. Web: Diario y Resúmenes

- [ ] 5.1 En `templates/_partials/entry_log.html.twig`, añadir a las entradas `TRANSCRIBED` el pie de métricas (tiempo de proceso, velocidad y modelo)
- [ ] 5.2 Añadir el pie de métricas (entrada, salida, total, tiempo y modelo) al panel de resumen del Diario y a cada resumen de `/resumenes`
- [ ] 5.3 Estilos `.metrics` en `public/css/app.css` (mono, tamaño pequeño, borde discontinuo superior, `tabular-nums`) en los dos temas
- [ ] 5.4 Tests funcionales de `DiarioController` y `SummariesController`: métricas visibles y `—` en registros sin métricas; sin pie en `PENDING`/`ERROR`

## 6. Web: Estadísticas "Consumo IA"

- [ ] 6.1 Métodos de repositorio con agregados diarios en el rango (`Europe/Madrid`): tokens de entrada/salida y `generation_ms` de `DailySummary`; número de audios transcritos, `duration_seconds` y `processing_ms` de `AudioRecording`/`Transcription`
- [ ] 6.2 En `EstadisticasController`, rellenar los días vacíos y calcular los tiles (tokens totales con desglose, media por resumen ignorando `null`, audio transcrito y tiempo de proceso Whisper/Ollama) sin aplicar el filtro de estado
- [ ] 6.3 En `templates/estadisticas/index.html.twig`, añadir la sección "Consumo IA": tiles, gráfico SVG de barras apiladas entrada/salida con leyenda y "Ver como tabla", y tabla por día de más reciente a más antigua
- [ ] 6.4 Tests funcionales de `EstadisticasController`: totales del rango, media que ignora `null`, filtro de estado sin efecto y día sin resumen con `—`

## 7. Cierre

- [ ] 7.1 Actualizar `Especificaciones.md` (modelo de datos, mensajes de Telegram y Estadísticas)
- [ ] 7.2 `make test` y `make cs-check` en verde
- [ ] 7.3 Prueba real: enviar un audio por Telegram y generar un resumen con `bin/console app:generate-daily-summary`, y revisar los mensajes y las tres vistas web
