# ai-usage-metrics Specification

## Purpose
TBD - created by archiving change ai-usage-metrics. Update Purpose after archive.
## Requirements
### Requirement: Captura de métricas de transcripción
El sistema SHALL medir, al transcribir un audio, el tiempo de reloj en milisegundos de la llamada al transcriptor que tuvo éxito, y SHALL guardarlo en `Transcription.processing_ms` junto con el nombre del modelo de transcripción configurado en `Transcription.model`. Los intentos fallidos previos (reintentos) NO SHALL sumarse al tiempo guardado. Editar manualmente el contenido de una transcripción NO SHALL modificar sus métricas; reintentar un audio en `ERROR` que termina transcrito SHALL guardar las métricas del intento exitoso.

#### Scenario: Transcripción exitosa guarda tiempo y modelo
- **WHEN** el handler transcribe un audio y la llamada al transcriptor tarda 14.200 ms
- **THEN** la `Transcription` creada tiene `processing_ms = 14200` y `model` con el modelo configurado

#### Scenario: Edición manual conserva las métricas
- **WHEN** el usuario edita el texto de una transcripción con `processing_ms = 14200`
- **THEN** tras guardar, `processing_ms` sigue siendo 14200 y `model` no cambia

### Requirement: Captura de métricas del resumen diario
El sistema SHALL obtener de la respuesta de Ollama (`usage.prompt_tokens`, `usage.completion_tokens` y `model`) los tokens de entrada, los tokens de salida y el modelo usado, SHALL medir el tiempo de reloj en milisegundos de la llamada de generación que tuvo éxito, y SHALL guardarlos en `DailySummary` (`prompt_tokens`, `completion_tokens`, `model`, `generation_ms`) cada vez que el resumen se crea o se regenera. Si la respuesta no incluye `usage`, los campos de tokens SHALL quedar a `null` sin que la generación falle. Si la respuesta no incluye `model`, SHALL usarse el modelo configurado.

#### Scenario: Resumen generado guarda tokens, tiempo y modelo
- **WHEN** Ollama responde con `usage.prompt_tokens = 2980`, `usage.completion_tokens = 432` y `model = "qwen2.5:7b"`, y la llamada tarda 38.000 ms
- **THEN** el `DailySummary` guardado tiene `prompt_tokens = 2980`, `completion_tokens = 432`, `model = "qwen2.5:7b"` y `generation_ms = 38000`

#### Scenario: Regeneración sustituye las métricas
- **WHEN** se regenera el resumen de un día que ya tenía métricas
- **THEN** las métricas del `DailySummary` se sustituyen por las de la nueva generación

#### Scenario: Respuesta sin `usage`
- **WHEN** Ollama responde con un resumen válido pero sin el objeto `usage`
- **THEN** el `DailySummary` se guarda con `prompt_tokens` y `completion_tokens` a `null` y la generación se considera exitosa

### Requirement: Presentación de métricas ausentes
El sistema SHALL mostrar "—" en cualquier vista donde una métrica de consumo sea `null` (registros anteriores a este cambio o respuestas sin `usage`), y SHALL excluir esos registros del cálculo de medias. En los mensajes de Telegram, las partes de la línea de métricas cuyo valor sea `null` SHALL omitirse.

#### Scenario: Transcripción antigua sin métricas en el Diario
- **WHEN** el Diario muestra una transcripción con `processing_ms = null`
- **THEN** el pie de métricas muestra "—" en lugar del tiempo de proceso y de la velocidad

#### Scenario: Media que ignora resúmenes sin tokens
- **WHEN** en el rango hay 3 resúmenes con 3.000, 4.000 y `null` tokens totales
- **THEN** la media de tokens por resumen mostrada es 3.500

### Requirement: Sección "Consumo IA" en Estadísticas
El sistema SHALL mostrar en Estadísticas una sección "Consumo IA" calculada sobre el rango de fechas seleccionado, independiente del filtro de estado, con:
- **Tiles**: tokens totales del rango (con desglose entrada/salida), media de tokens por resumen (con el número de resúmenes que tienen tokens), duración total del audio transcrito, y tiempo total de proceso (con desglose Whisper/Ollama).
- **Gráfico** de barras apiladas por día con tokens de entrada y de salida del resumen de cada día, con leyenda, y un "Ver como tabla" accesible.
- **Tabla por día**, de la fecha más reciente a la más antigua, con: número de audios transcritos, duración total del audio, tiempo total de proceso Whisper y tokens totales del resumen.

Los días sin datos SHALL aparecer en el gráfico sin barra y en la tabla con "—".

#### Scenario: Consumo del rango seleccionado
- **WHEN** el usuario selecciona el rango "15 días" y en ese rango hay 10 resúmenes con tokens que suman 32.600 de entrada y 4.700 de salida
- **THEN** el tile de tokens muestra 37.300 con el desglose 32.600 entrada · 4.700 salida

#### Scenario: El filtro de estado no afecta a Consumo IA
- **WHEN** el usuario aplica el filtro de estado "Error" en Estadísticas
- **THEN** la sección "Consumo IA" muestra los mismos valores que sin filtro de estado

#### Scenario: Día sin resumen
- **WHEN** un día del rango no tiene `DailySummary`
- **THEN** ese día aparece en el gráfico sin barra y en la tabla con "—" en la columna de tokens

