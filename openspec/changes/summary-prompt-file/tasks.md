## 1. Fichero de prompt

- [x] 1.1 Crear `config/prompts/daily_summary.md` con el contenido inicial definido en `design.md` (sin formato JSON)
- [x] 1.2 Añadir el binding `string $summaryPromptFile: '%kernel.project_dir%/config/prompts/daily_summary.md'` en `config/services.yaml`

## 2. Modelo de datos

- [x] 2.1 Añadir `emojiLegend` (JSON, nullable, `list<array{emoji: string, meaning: string}>`) a `DailySummary` con getter/setter
- [x] 2.2 Generar la migración Doctrine y aplicarla en dev y test

## 3. Generador

- [x] 3.1 Ampliar el tipo de retorno de `SummaryGeneratorInterface::generate()` con `legend`
- [x] 3.2 Añadir el argumento de constructor `string $summaryPromptFile` a `OllamaSummaryGenerator`
- [x] 3.3 Sustituir `SYSTEM_PROMPT` por `OUTPUT_CONTRACT` (JSON `{summary, topics, legend}`, leyenda = emojis usados, cada uno con una categoría general y corta, no el tema concreto)
- [x] 3.4 Leer el fichero en cada `generate()`; si no existe, no es legible o está vacío (tras `trim`), lanzar `SummaryGenerationException('PROMPT_NOT_FOUND', ...)` antes de llamar a Ollama
- [x] 3.5 Componer el mensaje de sistema como contenido del fichero + `OUTPUT_CONTRACT`
- [x] 3.6 Añadir `response_format` `json_schema` con `summary`, `topics` y `legend` requeridos
- [x] 3.7 Parsear y sanear `legend`: descartar mal formadas, duplicadas y emojis ausentes del texto; leyenda inválida → lista vacía sin fallar
- [x] 3.8 Registrar en log `daily_summary.prompt_tokens` con `usage.prompt_tokens` si viene en la respuesta (el generador recibe `LoggerInterface`)

## 4. Tests del generador

- [x] 4.1 Adaptar los tests existentes de `OllamaSummaryGeneratorTest` al nuevo constructor (fichero de prompt temporal) y a `legend`
- [x] 4.2 Test: el mensaje de sistema contiene el contenido del fichero y `OUTPUT_CONTRACT`; cambiar el fichero entre dos llamadas cambia el prompt
- [x] 4.3 Test: el payload incluye `response_format` con el esquema que requiere `summary`, `topics` y `legend`
- [x] 4.4 Test: fichero ausente y fichero vacío lanzan `PROMPT_NOT_FOUND` sin petición HTTP
- [x] 4.5 Test: saneado de la leyenda (válida, emoji ausente del texto, duplicados, ausente/invalid → vacía)
- [x] 4.6 Test: se registra `prompt_tokens` cuando la respuesta incluye `usage`

## 5. Servicio y Telegram

- [x] 5.1 `DailySummaryService` guarda `legend` en `emojiLegend` al crear y al regenerar
- [x] 5.2 `notifySummaryGenerated` añade `\n\n🏷️ Tema1 · Tema2` si hay temas y `\n\n💼 Trabajo · ✅ Pendientes` si hay leyenda
- [x] 5.3 Actualizar los tests de `DailySummaryServiceTest` (texto exacto del mensaje, persistencia de la leyenda, casos con/sin temas y leyenda) y los dobles de `SummaryGeneratorInterface`

## 6. Mensajes largos de Telegram

- [x] 6.1 `TelegramClient::sendMessage` divide textos de más de 4000 caracteres (`mb_strlen`) en varias partes enviadas en orden: por párrafos, luego saltos de línea, espacios y longitud
- [x] 6.2 Tests en `TelegramClientTest`: texto corto → una petición; texto largo con párrafos → varias peticiones cortadas entre párrafos y sin pérdida de contenido; párrafo único gigante → partes ≤ 4000

## 7. Vistas web

- [x] 7.1 Renderizar la leyenda bajo `summaryText` en `templates/diario/index.html.twig` (los saltos de línea ya se respetaban por `white-space: pre-line`)
- [x] 7.2 Ídem en `templates/resumenes/index.html.twig` y `templates/busqueda/index.html.twig`
- [x] 7.3 Estilo discreto para la leyenda (texto pequeño/atenuado) coherente con el CSS existente
- [x] 7.4 Test funcional: en Diario un resumen con dos párrafos se muestra separado, con su leyenda, y el texto sigue escapado; un resumen sin leyenda no la muestra

## 8. Documentación y verificación

- [x] 8.1 Actualizar `Especificaciones.md`: prompt en `config/prompts/daily_summary.md`, contrato JSON con `legend`, campo `emoji_legend`, formato del mensaje de Telegram y división de mensajes largos
- [x] 8.2 Ejecutar `make test` y `make cs-check`
- [x] 8.3 Comprobar que la versión de Ollama del servidor soporta `response_format` `json_schema` y que `config/prompts/` llega al contenedor de producción
- [x] 8.4 Comprobar la ventana de contexto efectiva de `qwen2.5:14b` en el servidor (`OLLAMA_CONTEXT_LENGTH` / `ollama show`); medir `prompt_tokens` de un día con muchos audios; si no hay margen, subirla en el servidor y documentarlo en `Especificaciones.md`
- [ ] 8.5 Generar un resumen real con `bin/console app:generate-daily-summary` y revisar texto, emojis, leyenda y temas
