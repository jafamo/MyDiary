## ADDED Requirements

### Requirement: Prompt de estilo del resumen en fichero versionado
El sistema SHALL leer las instrucciones de estilo del resumen diario desde el fichero `config/prompts/daily_summary.md` del proyecto en cada generación, y SHALL enviarlas al generador como parte del mensaje de sistema. Un cambio en el contenido de ese fichero SHALL reflejarse en la siguiente generación sin modificar código PHP. Si el fichero no existe, no es legible o está vacío, la generación SHALL fallar con un error `PROMPT_NOT_FOUND`, aplicándose el manejo de errores ya definido para la generación (reintentos, log estructurado y notificación de fallo por Telegram).

#### Scenario: El contenido del fichero se envía al modelo
- **WHEN** se genera un resumen diario y `config/prompts/daily_summary.md` contiene unas instrucciones de estilo
- **THEN** el mensaje de sistema enviado a Ollama incluye ese contenido

#### Scenario: Editar el fichero cambia la siguiente generación
- **WHEN** se modifica el contenido de `config/prompts/daily_summary.md` y después se genera un resumen
- **THEN** la petición a Ollama usa el contenido nuevo del fichero, sin cambios en el código

#### Scenario: Fichero de prompt ausente o vacío
- **WHEN** se intenta generar un resumen y el fichero de prompt no existe o está vacío
- **THEN** la generación falla con `SummaryGenerationException` de código `PROMPT_NOT_FOUND` y no se llama a Ollama

### Requirement: Contrato de salida impuesto por código
El sistema SHALL mantener en el código, fuera del fichero de prompt de estilo, la instrucción de formato de salida y SHALL enviar en cada petición a Ollama un `response_format` de tipo `json_schema` que exija un objeto con `summary` (string), `topics` (array de strings) y `legend` (array de objetos `{emoji, meaning}` con los emojis usados en `summary` y su significado). El sistema SHALL fallar con `INVALID_JSON` si `summary` o `topics` no cumplen el contrato.

#### Scenario: La petición incluye el esquema de salida
- **WHEN** se genera un resumen diario
- **THEN** la petición a `/v1/chat/completions` incluye `response_format` con un esquema JSON que requiere `summary`, `topics` y `legend`

#### Scenario: Un prompt de estilo sin mención al JSON no rompe el contrato
- **WHEN** el fichero de prompt de estilo no menciona el formato JSON
- **THEN** el mensaje de sistema enviado sigue incluyendo la instrucción de contrato de salida definida en código

#### Scenario: Respuesta que no cumple el contrato
- **WHEN** Ollama devuelve contenido que no es un JSON con `summary` y `topics`
- **THEN** la generación falla con `SummaryGenerationException` de código `INVALID_JSON`

### Requirement: Leyenda de emojis generada por el modelo
El sistema SHALL tomar la leyenda de emojis de la respuesta del modelo, que elige libremente los emojis del resumen. El sistema SHALL descartar las entradas de la leyenda mal formadas, las duplicadas por emoji y aquellas cuyo emoji no aparezca en el texto del resumen, conservando el orden devuelto por el modelo. Una leyenda ausente o inválida NO SHALL hacer fallar la generación: el resumen se guarda con leyenda vacía.

#### Scenario: Leyenda válida
- **WHEN** el modelo devuelve un resumen con párrafos que empiezan por 💼 y ✅ y `legend` `[{"emoji":"💼","meaning":"Trabajo"},{"emoji":"✅","meaning":"Pendientes"}]`
- **THEN** el resultado de la generación incluye esa leyenda en ese orden

#### Scenario: Entrada de leyenda que no aparece en el texto
- **WHEN** `legend` incluye un emoji que no aparece en `summary`
- **THEN** esa entrada se descarta y el resto de la leyenda se conserva

#### Scenario: Leyenda ausente
- **WHEN** el modelo devuelve `summary` y `topics` válidos pero sin `legend` o con un valor no válido
- **THEN** la generación tiene éxito con leyenda vacía

### Requirement: División de mensajes largos de Telegram
El sistema SHALL dividir en varios mensajes, enviados en orden, cualquier texto enviado por Telegram que supere el límite de longitud de un mensaje (4000 caracteres como límite efectivo). La división SHALL hacerse preferentemente entre párrafos, y solo si un párrafo no cabe por sí solo, por saltos de línea, espacios o, en último caso, por longitud. Ninguna parte SHALL superar el límite y la concatenación de las partes SHALL conservar todo el contenido original.

#### Scenario: Resumen que cabe en un mensaje
- **WHEN** el mensaje del resumen tiene 3000 caracteres
- **THEN** se envía un único mensaje

#### Scenario: Resumen que supera el límite
- **WHEN** el mensaje del resumen tiene 6000 caracteres repartidos en varios párrafos
- **THEN** se envían dos o más mensajes en orden, cortados entre párrafos, ninguno de más de 4000 caracteres, y entre todos contienen el texto completo

#### Scenario: Párrafo más largo que el límite
- **WHEN** un único párrafo supera los 4000 caracteres
- **THEN** ese párrafo se divide en partes de como máximo 4000 caracteres, cortando preferentemente en espacios

### Requirement: Registro del tamaño de entrada del generador
El sistema SHALL registrar en logs estructurados, en cada generación exitosa del resumen diario, el número de tokens de entrada que informa Ollama (`usage.prompt_tokens`), cuando esté disponible, para poder detectar días cercanos al límite de la ventana de contexto.

#### Scenario: Tokens de entrada registrados
- **WHEN** Ollama responde con `usage.prompt_tokens`
- **THEN** se registra un log `daily_summary.prompt_tokens` con ese valor y el número de transcripciones enviadas

## MODIFIED Requirements

### Requirement: Generación de resumen y temas vía Ollama
El sistema SHALL recoger todas las transcripciones en estado `TRANSCRIBED` del día en curso, SHALL llamar al servicio de generación configurado (`SummaryGeneratorInterface`) para obtener un resumen, una lista de temas y una leyenda de emojis, y SHALL guardar o actualizar el `DailySummary` de esa fecha con sus `Topic` asociados y su leyenda.

#### Scenario: Generación exitosa, primera vez del día
- **WHEN** el comando se ejecuta para una fecha sin `DailySummary` previo y hay transcripciones `TRANSCRIBED` ese día
- **THEN** se crea un `DailySummary` con `summary_text`, `generated_at`, `emoji_legend`, y sus `Topic` asociados (creando los que no existan ya por `name`)

#### Scenario: Re-ejecución actualiza en vez de duplicar
- **WHEN** el comando se ejecuta para una fecha que ya tiene `DailySummary`
- **THEN** el `DailySummary` existente se actualiza (no se crea un segundo registro para la misma fecha) y sus asociaciones de `Topic` y su `emoji_legend` se reemplazan por las del nuevo resultado

### Requirement: Notificación por Telegram del resumen generado
El sistema SHALL enviar por Telegram al `authorizedChatId` una cabecera con icono y la fecha del resumen en español, seguida de una línea en blanco y el texto del `DailySummary`; si el resumen tiene `Topic`, una línea en blanco y la línea `🏷️ <tema1> · <tema2> · ...`; y si tiene leyenda de emojis, una línea en blanco y la línea `<emoji> <significado> · <emoji> <significado> · ...`, inmediatamente después de guardarlo (o actualizarlo) con éxito, tanto en el disparo programado como en la ejecución manual por consola y en la generación bajo demanda desde la web. La cabecera SHALL tener el formato `📔 Resumen día: <día> de <mes> de <año>` (p. ej. `📔 Resumen día: 22 de septiembre de 2026`), usando el nombre del mes en español y la fecha del `DailySummary` (no la fecha de envío). Un fallo al enviar esta notificación SHALL registrarse en logs estructurados y NO SHALL revertir ni invalidar el `DailySummary` ya guardado.

#### Scenario: Notificación tras generación exitosa
- **WHEN** el `DailySummary` de una fecha se genera y guarda con éxito
- **THEN** el sistema envía por Telegram al `authorizedChatId` la cabecera con la fecha correspondiente seguida del texto del resumen, la línea de temas y la leyenda de emojis

#### Scenario: Temas y leyenda al final del mensaje
- **WHEN** el `DailySummary` se genera con los temas `Informe de ventas` y `Gimnasio del barrio` y la leyenda `💼 Trabajo`, `✅ Pendientes`
- **THEN** el mensaje de Telegram termina con `🏷️ Informe de ventas · Gimnasio del barrio`, una línea en blanco y `💼 Trabajo · ✅ Pendientes`

#### Scenario: Resumen sin temas ni leyenda
- **WHEN** el `DailySummary` se genera sin `Topic` y con leyenda vacía
- **THEN** el mensaje de Telegram contiene solo la cabecera y el texto del resumen

#### Scenario: Notificación tras regeneración
- **WHEN** el usuario regenera el `DailySummary` de una fecha que ya tenía uno
- **THEN** el sistema envía por Telegram la cabecera con la fecha correspondiente seguida del texto del resumen actualizado, sustituyendo al anterior

#### Scenario: Fallo de envío no afecta al resumen guardado
- **WHEN** el `DailySummary` se guarda con éxito pero el envío del mensaje a la API de Telegram falla (p. ej. error de red)
- **THEN** el `DailySummary` permanece guardado sin cambios, y el fallo de envío se registra en logs estructurados sin propagarse como error de generación
