## Context

`OllamaSummaryGenerator` construye el mensaje de sistema a partir de la constante `SYSTEM_PROMPT`, que mezcla el estilo del resumen con el contrato de salida (JSON con `summary` y `topics`), validado con `json_decode` en PHP. El resumen resultante es breve y genérico. La notificación de Telegram es texto plano (sin `parse_mode`): cabecera 📔 + texto. En web el texto se pinta con `<p>{{ summaryText }}</p>`, que colapsa saltos de línea.

## Goals / Non-Goals

**Goals:**
- Resúmenes descriptivos y concretos, más visuales en Telegram gracias a emojis.
- Leyenda de los emojis usados, para saber qué significa cada uno, en Telegram y web.
- Estilo editable en un fichero versionado; contrato de salida protegido frente a ediciones del prompt.
- Cambios de prompt efectivos sin reiniciar workers.

**Non-Goals:**
- Catálogo fijo de emojis en código: los elige el modelo.
- Formato enriquecido (Markdown, negritas) en web o Telegram.
- Prompts editables desde la web, plantillas con variables, cambio de proveedor/modelo.
- Recalcular la leyenda de resúmenes ya existentes (quedan con `emoji_legend = null` hasta que se regeneren).

## Decisions

**Fichero `config/prompts/daily_summary.md`, ruta inyectada por `services.yaml`, leído en cada `generate()`.** Binding `string $summaryPromptFile: '%kernel.project_dir%/config/prompts/daily_summary.md'`. Leerlo en cada llamada (pocas al día) hace que una edición se aplique sin reiniciar workers de Messenger/Scheduler. Alternativa descartada: prompt en `.env` — incómodo multilínea y no versionado.

**Mensaje de sistema = estilo (fichero) + contrato (código).** El ejemplo de `summary` del contrato empieza cada párrafo con un emoji y el contrato exige explícitamente un emoji por párrafo: en pruebas con `qwen2.5:14b`, con un ejemplo sin emojis el modelo ignoraba la regla del fichero de estilo. El código concatena el fichero con una constante `OUTPUT_CONTRACT` que describe el JSON `{summary, topics, legend}` y pide que `legend` contenga exactamente los emojis usados en `summary`, con un significado que sea una categoría general y corta ("Trabajo", "Familia", "Salud", "Pendientes"), no el tema concreto del día: las categorías se mantienen estables entre días aunque el emoji elegido pueda variar; lo concreto ya va en `topics`. El fichero solo habla de estilo.

**La leyenda la genera el modelo, en el mismo JSON.** Como los emojis los elige libremente el modelo, solo él sabe qué quiso decir con cada uno; pedírselo en la misma respuesta evita una segunda llamada. `legend: [{"emoji": "💼", "meaning": "Trabajo"}, …]` (lista de objetos, no mapa, para conservar orden y facilitar el esquema).

**`response_format: {type: json_schema}`** con `summary` (string), `topics` (array de strings) y `legend` (array de `{emoji: string, meaning: string}`), todos requeridos. Se mantiene la validación en PHP (defensa en profundidad). `summary`/`topics` inválidos → `INVALID_JSON` como hoy. `legend` ausente o con elementos mal formados **no** hace fallar la generación: se descartan los elementos inválidos (la leyenda es accesoria y no debe costar un resumen).

**Saneado de la leyenda en código.** Se descartan entradas cuyo `emoji` no aparezca en `summary` (evita leyendas que no corresponden al texto) y duplicados por emoji; se conserva el orden del modelo. Es un filtro, no un catálogo: no limita qué emojis puede usar el modelo.

**Persistencia: `DailySummary.emojiLegend` (JSON, nullable).** Necesario para mostrar la leyenda en web sin volver a llamar al modelo. Se sobrescribe al regenerar, como `summaryText`. `null` en resúmenes anteriores al cambio. Alternativa descartada: añadir la leyenda al final de `summaryText` — mezclaría presentación con contenido y ensuciaría el embedding de búsqueda.

**Telegram: texto plano, líneas finales.** Mensaje: cabecera, línea en blanco, texto; si hay temas, línea en blanco y `🏷️ Tema1 · Tema2 · …`; si hay leyenda, línea en blanco y `emoji significado · emoji significado · …`. Sin `parse_mode`: los emojis son texto plano y no hay riesgo de rechazo por formato mal generado. `notifySummaryGenerated` recibe el `DailySummary` (o texto, temas y leyenda) en lugar de solo el texto.

**Mensajes largos de Telegram: división en `TelegramClient::sendMessage`.** Telegram rechaza mensajes de más de 4096 caracteres; con resúmenes más descriptivos, cabecera, temas y leyenda, un día intenso podría superarlo y el resumen no llegaría (solo quedaría un error en log). No se limita la longitud en el prompt: la protección está en código. Si el texto supera el límite, `sendMessage` lo divide en varios mensajes enviados en orden: corta por párrafos (`\n\n`), agrupando párrafos mientras quepan; si un único párrafo supera el límite, lo corta por saltos de línea, luego por espacios y, en último caso, por longitud. Límite efectivo de 4000 caracteres medidos con `mb_strlen` (margen frente a cómo cuenta Telegram emojis y caracteres fuera del BMP). Se hace en `TelegramClient` y no en `DailySummaryService` porque es una restricción de la API y así protege cualquier mensaje. Si falla el envío de una parte, la excepción se propaga como hoy (el llamador ya la registra) y no se envían las siguientes.

**Ventana de contexto de Ollama (`num_ctx`): comprobación, no cambio de código.** En la ventana tienen que caber el prompt y todas las transcripciones del día; si no caben, Ollama descarta en silencio el principio de la entrada y el resumen sale incompleto sin error. El endpoint OpenAI-compatible (`/v1/chat/completions`) no admite fijar `num_ctx` por petición, así que es configuración del servidor Ollama (variable `OLLAMA_CONTEXT_LENGTH` o un modelo derivado con `PARAMETER num_ctx`), fuera de este repo. Se verifica: (1) el contexto efectivo del modelo en el servidor, (2) el tamaño en tokens de un día con muchos audios más el prompt (Ollama devuelve `usage.prompt_tokens`), y (3) si no hay margen, se sube en el servidor y se documenta en `Especificaciones.md`. Además, el generador registra en log `usage.prompt_tokens` de cada generación (evento `daily_summary.prompt_tokens`, con el número de transcripciones; el generador no conoce la fecha) para poder detectar días que se acercan al límite.

**Web: leyenda bajo el texto mediante un parcial.** Los párrafos ya se respetan: las tres vistas pintan el texto en un `<p>` con `white-space: pre-line` (CSS existente), así que no hace falta `nl2br`. La leyenda se pinta con `templates/_partials/emoji_legend.html.twig` bajo el texto, en línea discreta (`.emoji-legend`), omitida si `emojiLegend` es `null` o vacío. Los temas siguen como etiquetas (sin la línea 🏷️).

## Contenido inicial de `config/prompts/daily_summary.md`

```markdown
Eres el asistente de un diario personal. Recibes las transcripciones de las notas de voz
que el autor grabó durante un día y escribes la entrada de diario de ese día.
El lector es el propio autor, releyendo dentro de meses: tiene que poder recordar qué
pasó concretamente ese día, no solo de qué temas habló.

Cómo escribir el resumen:
- En castellano, en segunda persona ("Hoy fuiste...", "Te preocupaba...").
- Conserva los detalles concretos: nombres de personas, lugares, cifras, fechas,
  decisiones tomadas, planes y pendientes mencionados.
- Recoge cómo se sentía el autor si lo expresa (cansado, contento, agobiado...).
- Sigue el orden en que ocurrieron las cosas durante el día.
- Longitud proporcional al contenido: un párrafo si el día tuvo poco, hasta 3–4 si tuvo mucho.
  Separa los párrafos con una línea en blanco.
- Empieza cada párrafo con un único emoji que represente su contenido principal.
  No uses más emojis dentro del texto. Usa siempre el mismo emoji para el mismo tipo
  de contenido dentro del resumen.
- Texto plano: sin Markdown, sin negritas, sin listas ni títulos.
- No inventes nada que no esté en las transcripciones. Si algo es ambiguo, omítelo.

Evita frases genéricas que no aportan información, como:
"hablaste de varios temas", "fue un día productivo", "reflexionaste sobre tu vida",
"mencionaste aspectos laborales". Si no puedes decir algo concreto, no lo digas.

Temas: entre 2 y 6, concretos y cortos ("Presupuesto de la reforma", "Cita médico Lucía"),
no categorías genéricas ("Trabajo", "Familia").
```

## Risks / Trade-offs

- [El modelo usa emojis sin incluirlos en la leyenda, o viceversa] → El esquema exige `legend` y el contrato lo pide explícitamente; el saneado quita entradas que no están en el texto. Un emoji sin leyenda es un defecto menor aceptado.
- [El modelo sigue siendo genérico o inventa detalles] → Iterar el fichero con días reales (`--date`); si no basta, añadir un ejemplo bueno/malo o bajar `temperature` en un cambio aparte.
- [Versión de Ollama sin soporte de `json_schema` en `/v1/chat/completions`] → Comprobar la versión antes de desplegar; la validación en PHP sigue detectando respuestas inválidas.
- [El fichero no llega al contenedor de producción] → Verificar que `config/` se monta/copia; el error `PROMPT_NOT_FOUND` lo haría visible en el primer resumen.
- [Un día muy largo supera la ventana de contexto de Ollama] → Comprobación de `num_ctx` antes de desplegar y log de `prompt_tokens` en cada generación.
- [Mensaje dividido en varias partes] → Solo ocurre en días muy largos; preferible a que no llegue.
- [Resúmenes antiguos sin leyenda] → Se muestran sin leyenda; al regenerarlos la obtienen.
