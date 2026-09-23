## Why

Los resúmenes diarios salen genéricos ("hablaste de trabajo y familia") y aportan poco al releerlos: el prompt actual solo pide "un resumen breve". Además, el estilo del resumen está fijado en una constante `SYSTEM_PROMPT` dentro de `OllamaSummaryGenerator`, mezclado con el contrato de salida JSON: ajustarlo exige tocar PHP y es fácil romper sin querer el formato que el código espera. Queremos resúmenes más descriptivos y visuales en Telegram, e iterar su estilo de forma cómoda, versionada en el propio proyecto y sin añadir dependencias nuevas (se descarta Open WebUI).

## What Changes

- El prompt de estilo del resumen diario pasa a un fichero versionado: `config/prompts/daily_summary.md`, leído en cada generación.
- El contenido inicial pide un resumen descriptivo: segunda persona, detalles concretos (personas, lugares, cifras, decisiones, pendientes), estado de ánimo si se expresa, orden cronológico, longitud proporcional (1 a 4 párrafos), texto plano, sin inventar ni frases genéricas; un emoji elegido por el modelo al inicio de cada párrafo; temas concretos (2–6) en lugar de categorías.
- El contrato de salida sigue en código y se amplía: `{summary, topics, legend}`, donde `legend` es la lista de emojis usados en el resumen con su significado (`[{emoji, meaning}]`), **elegidos libremente por el modelo**. Se impone con `response_format` `json_schema` (structured outputs) en la llamada a Ollama.
- `DailySummary` guarda la leyenda (nuevo campo JSON `emoji_legend`, nullable; migración).
- La notificación de Telegram añade al final una línea de temas (`🏷️ Tema1 · Tema2`) y la leyenda (`💼 Trabajo · ✅ Pendientes`), cada una omitida si está vacía.
- Las vistas web (Diario, Resúmenes, Búsqueda) muestran el texto respetando párrafos y la leyenda bajo el resumen.
- `TelegramClient::sendMessage` divide los mensajes que superen el límite de Telegram (4096 caracteres) en varios, cortando entre párrafos, para que un resumen largo nunca deje de llegar.
- Se registra en log el tamaño de entrada (`usage.prompt_tokens`) de cada generación y se verifica la ventana de contexto (`num_ctx`) del servidor Ollama, para que un día con muchos audios no se trunque en silencio.
- Si el fichero de prompt no existe o está vacío, la generación falla con `SummaryGenerationException` (`PROMPT_NOT_FOUND`), reutilizando el manejo de errores existente.
- Se mantiene la dependencia de Ollama; no se introduce Open WebUI.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `daily-summary-generation`: estilo del resumen en fichero versionado; contrato de salida en código con `legend`; notificación de Telegram con línea de temas y leyenda; división de mensajes largos; log de tokens de entrada.
- `data-model`: `DailySummary` añade `emoji_legend`.
- `web-views`: el resumen se muestra con párrafos y con la leyenda de emojis debajo.

## Impact

- `src/Contract/SummaryGeneratorInterface.php`: el array devuelto añade `legend`.
- `src/Service/Ollama/OllamaSummaryGenerator.php`: ruta del fichero de prompt, composición del mensaje de sistema, `response_format`, parseo de `legend`.
- `config/services.yaml`: binding `$summaryPromptFile`. Nuevo `config/prompts/daily_summary.md`.
- `src/Entity/DailySummary.php` + migración Doctrine (`emoji_legend` JSON nullable).
- `src/Service/Telegram/TelegramClient.php` + `TelegramClientTest`: división de mensajes largos.
- `src/Service/DailySummaryService.php`: guardar la leyenda y formato del mensaje de Telegram.
- `templates/diario/index.html.twig`, `templates/resumenes/index.html.twig`, `templates/busqueda/index.html.twig` (+ CSS de la leyenda).
- Tests: `OllamaSummaryGeneratorTest`, `DailySummaryServiceTest`, tests funcionales de vistas.
- `Especificaciones.md`: prompt en fichero, contrato con leyenda, nuevo campo y formato del mensaje.
