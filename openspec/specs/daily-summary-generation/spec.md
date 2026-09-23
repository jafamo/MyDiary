## Purpose

Generación programada (y bajo demanda) del resumen diario y sus temas a partir de las transcripciones del día, vía Ollama.
## Requirements
### Requirement: Disparo diario a las 21:00 Europe/Madrid
El sistema SHALL ejecutar automáticamente `app:generate-daily-summary` cada día a las 21:00 en la zona horaria `Europe/Madrid`, vía Symfony Scheduler.

#### Scenario: Disparo automático
- **WHEN** el reloj del sistema alcanza las 21:00 `Europe/Madrid`
- **THEN** el Scheduler dispara la ejecución de `app:generate-daily-summary` sin intervención manual

### Requirement: Espera corta por transcripciones pendientes
El sistema SHALL comprobar si existen `AudioRecording` del día en curso en estado `PENDING`, y SHALL esperar con reintentos periódicos durante una ventana corta antes de continuar, para no excluir transcripciones casi listas — **únicamente cuando la generación se dispara por el Scheduler o por consola**. El disparo manual desde la web SHALL omitir esta espera (ver "Generación bajo demanda desde la web").

#### Scenario: Continúa tras agotar la ventana de espera
- **WHEN** siguen existiendo `AudioRecording` `PENDING` del día tras agotarse la ventana de espera
- **THEN** el comando continúa igualmente, generando el resumen solo con las transcripciones ya disponibles en estado `TRANSCRIBED`

### Requirement: Generación bajo demanda desde la web
El sistema SHALL permitir disparar la generación (o regeneración) del `DailySummary` del día actual desde la vista Diario, sin esperar al disparo programado de las 21:00. Esta vía SHALL reutilizar la misma lógica de generación, reintentos, guardado y notificación de fallo que el disparo programado, y SHALL omitir la espera por `AudioRecording` `PENDING` (a diferencia del disparo programado), generando inmediatamente con las transcripciones `TRANSCRIBED` disponibles en ese momento.

#### Scenario: Generación manual exitosa desde Diario
- **WHEN** el usuario pulsa "Generar resumen" en Diario y hay transcripciones `TRANSCRIBED` para el día actual
- **THEN** el sistema genera (o regenera) el `DailySummary` de hoy de inmediato, sin esperar por audios `PENDING`

#### Scenario: Generación manual sin esperar pendientes
- **WHEN** el usuario pulsa "Generar resumen" y existen `AudioRecording` `PENDING` del día
- **THEN** el sistema genera el resumen igualmente con las transcripciones ya disponibles, sin bloquear la petición esperando a que terminen los pendientes

### Requirement: Generación de resumen y temas vía Ollama
El sistema SHALL recoger todas las transcripciones en estado `TRANSCRIBED` del día en curso, SHALL llamar al servicio de generación configurado (`SummaryGeneratorInterface`) para obtener un resumen, una lista de temas y una leyenda de emojis, y SHALL guardar o actualizar el `DailySummary` de esa fecha con sus `Topic` asociados y su leyenda.

#### Scenario: Generación exitosa, primera vez del día
- **WHEN** el comando se ejecuta para una fecha sin `DailySummary` previo y hay transcripciones `TRANSCRIBED` ese día
- **THEN** se crea un `DailySummary` con `summary_text`, `generated_at`, `emoji_legend`, y sus `Topic` asociados (creando los que no existan ya por `name`)

#### Scenario: Re-ejecución actualiza en vez de duplicar
- **WHEN** el comando se ejecuta para una fecha que ya tiene `DailySummary`
- **THEN** el `DailySummary` existente se actualiza (no se crea un segundo registro para la misma fecha) y sus asociaciones de `Topic` y su `emoji_legend` se reemplazan por las del nuevo resultado

### Requirement: Manejo de errores en la generación
El sistema SHALL reintentar la llamada al generador de resumen un número corto de veces con espera breve si falla. Si todos los intentos fallan, SHALL registrar el error en logs estructurados, SHALL no crear ni modificar el `DailySummary` de esa fecha, y SHALL notificar al usuario *"No se pudo generar el resumen de hoy ⚠️"* por Telegram, sin reintento automático al día siguiente.

#### Scenario: Fallo tras agotar los reintentos
- **WHEN** todas las tentativas de generación fallan para una fecha dada
- **THEN** no se persiste ningún `DailySummary` para esa fecha, se registra un log de error con contexto estructurado, y se envía la notificación de fallo por Telegram

### Requirement: Comando ejecutable manualmente
El sistema SHALL permitir ejecutar `app:generate-daily-summary` manualmente (no solo por el Scheduler), opcionalmente para una fecha distinta a hoy.

#### Scenario: Ejecución manual para una fecha concreta
- **WHEN** se ejecuta `bin/console app:generate-daily-summary --date=2026-08-01`
- **THEN** el comando genera (o regenera) el `DailySummary` correspondiente al 1 de agosto de 2026, no al día actual

### Requirement: Notificación por Telegram del resumen generado
El sistema SHALL enviar por Telegram al `authorizedChatId` una cabecera con icono y la fecha del resumen en español, seguida de una línea en blanco y el texto del `DailySummary`; si el resumen tiene `Topic`, una línea en blanco y la línea `🏷️ <tema1> · <tema2> · ...`; si tiene leyenda de emojis, una línea en blanco y la línea `<emoji> <significado> · <emoji> <significado> · ...`; y, al final, una línea en blanco y la línea de métricas `🧮 <n> audios · <total> tokens (<entrada> entrada + <salida> salida) · ⏱️ <tiempo> · 🤖 <modelo>`, inmediatamente después de guardarlo (o actualizarlo) con éxito, tanto en el disparo programado como en la ejecución manual por consola y en la generación bajo demanda desde la web. `<n>` es el número de transcripciones enviadas al generador (en singular, `1 audio`, si solo hay una); los números SHALL formatearse con separador de miles `.`; `<tiempo>` se expresa en segundos (`38 s`) si es menor de un minuto y en minutos y segundos (`1 min 05 s`) en caso contrario. Si faltan los tokens, la parte de tokens SHALL omitirse del mensaje; el modelo SHALL mostrarse siempre. La cabecera SHALL tener el formato `📔 Resumen día: <día> de <mes> de <año>` (p. ej. `📔 Resumen día: 22 de septiembre de 2026`), usando el nombre del mes en español y la fecha del `DailySummary` (no la fecha de envío). Un fallo al enviar esta notificación SHALL registrarse en logs estructurados y NO SHALL revertir ni invalidar el `DailySummary` ya guardado.

#### Scenario: Notificación tras generación exitosa
- **WHEN** el `DailySummary` de una fecha se genera y guarda con éxito
- **THEN** el sistema envía por Telegram al `authorizedChatId` la cabecera con la fecha correspondiente seguida del texto del resumen, la línea de temas, la leyenda de emojis y la línea de métricas

#### Scenario: Temas y leyenda al final del mensaje
- **WHEN** el `DailySummary` se genera con los temas `Informe de ventas` y `Gimnasio del barrio` y la leyenda `💼 Trabajo`, `✅ Pendientes`
- **THEN** el mensaje de Telegram contiene `🏷️ Informe de ventas · Gimnasio del barrio`, una línea en blanco y `💼 Trabajo · ✅ Pendientes`, seguido de una línea en blanco y la línea de métricas

#### Scenario: Resumen sin temas ni leyenda
- **WHEN** el `DailySummary` se genera sin `Topic` y con leyenda vacía
- **THEN** el mensaje de Telegram contiene solo la cabecera, el texto del resumen y la línea de métricas

#### Scenario: Línea de métricas con tokens y modelo
- **WHEN** el resumen se genera a partir de 5 transcripciones, con 2.980 tokens de entrada, 432 de salida, en 38.000 ms con el modelo `qwen2.5:7b`
- **THEN** el mensaje de Telegram termina con `🧮 5 audios · 3.412 tokens (2.980 entrada + 432 salida) · ⏱️ 38 s · 🤖 qwen2.5:7b`

#### Scenario: Línea de métricas sin tokens
- **WHEN** el resumen se genera a partir de 2 transcripciones, en 12.000 ms con el modelo `qwen2.5:7b`, y Ollama no devuelve `usage`
- **THEN** el mensaje de Telegram termina con `🧮 2 audios · ⏱️ 12 s · 🤖 qwen2.5:7b`

#### Scenario: Notificación tras regeneración
- **WHEN** el usuario regenera el `DailySummary` de una fecha que ya tenía uno
- **THEN** el sistema envía por Telegram la cabecera con la fecha correspondiente seguida del texto del resumen actualizado y las métricas de la nueva generación, sustituyendo al anterior

#### Scenario: Fallo de envío no afecta al resumen guardado
- **WHEN** el `DailySummary` se guarda con éxito pero el envío del mensaje a la API de Telegram falla (p. ej. error de red)
- **THEN** el `DailySummary` permanece guardado sin cambios, y el fallo de envío se registra en logs estructurados sin propagarse como error de generación

### Requirement: Generación del embedding al generar el resumen diario
El sistema SHALL generar y guardar el embedding vectorial del `summaryText` de un `DailySummary` inmediatamente después de guardarlo (flujo 3.5, `DailySummaryService::saveDailySummary`). Un fallo al generar el embedding SHALL registrarse en logs estructurados y NO SHALL impedir que el `DailySummary` se guarde ni que se notifique por Telegram.

#### Scenario: Embedding generado junto con el resumen diario
- **WHEN** un resumen diario se genera con éxito
- **THEN** el sistema guarda también su embedding vectorial correspondiente al `summaryText`

#### Scenario: Fallo al generar el embedding no bloquea el guardado del resumen
- **WHEN** el resumen diario se genera con éxito pero la generación del embedding falla (p. ej. error de red con Ollama)
- **THEN** el `DailySummary` queda guardado igualmente, sin embedding, y el fallo se registra en logs estructurados

### Requirement: Regeneración del embedding al regenerar el resumen diario
El sistema SHALL regenerar el embedding de un `DailySummary` cada vez que su `summaryText` se sobrescribe (p. ej. al volver a generar el resumen del mismo día), para que el embedding no quede desincronizado con el texto vigente.

#### Scenario: Embedding actualizado tras regenerar el resumen
- **WHEN** el resumen diario de una fecha ya existente se vuelve a generar con un `summaryText` distinto
- **THEN** el sistema regenera su embedding a partir del nuevo `summaryText`

### Requirement: Recheck tardío entre las 21:00 y las 00:30 Europe/Madrid
El sistema SHALL ejecutar automáticamente `app:recheck-daily-summary` cada 15 minutos entre las 21:00 y las 00:30 en la zona horaria `Europe/Madrid`, vía Symfony Scheduler. Para cada ejecución, el comando SHALL determinar si existen `AudioRecording` en estado `TRANSCRIBED` recibidas ese día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente de ese día (o, si no existe `DailySummary` para ese día, si existe cualquier `AudioRecording` `TRANSCRIBED` de ese día). Cuando existan tales transcripciones nuevas, el sistema SHALL regenerar el `DailySummary` del día reutilizando `DailySummaryService::generateForDate` sin esperar por `AudioRecording` `PENDING` (igual que la generación bajo demanda desde la web), y SHALL reenviar la notificación por Telegram con el resumen actualizado. Cuando no existan transcripciones nuevas, el sistema SHALL no regenerar ni reenviar nada.

#### Scenario: Audio nuevo tras el resumen de las 21:00 dispara regeneración
- **WHEN** el recheck se ejecuta y existe al menos una `AudioRecording` `TRANSCRIBED` del día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente
- **THEN** el sistema regenera el `DailySummary` del día con todas las transcripciones disponibles y reenvía por Telegram la cabecera con la fecha seguida del texto del resumen actualizado

#### Scenario: Sin audios nuevos, no se reenvía nada
- **WHEN** el recheck se ejecuta y no existe ninguna `AudioRecording` `TRANSCRIBED` del día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente
- **THEN** el sistema no regenera el `DailySummary` ni envía ningún mensaje por Telegram

#### Scenario: El resumen de las 21:00 falló y llegan audios después
- **WHEN** el recheck se ejecuta, no existe `DailySummary` para el día en curso, y existe al menos una `AudioRecording` `TRANSCRIBED` de ese día
- **THEN** el sistema genera el `DailySummary` del día (como si fuera la primera generación) y lo envía por Telegram

#### Scenario: Ejecuciones repetidas sin novedades no duplican envíos
- **WHEN** el recheck se ejecuta varias veces seguidas dentro de la ventana 21:00–00:30 sin que lleguen audios nuevos entre ejecuciones
- **THEN** solo la primera ejecución que detectó novedades (si la hubo) regenera y reenvía; las siguientes no vuelven a enviar el mismo resumen

### Requirement: Comando `app:recheck-daily-summary` ejecutable manualmente
El sistema SHALL permitir ejecutar `app:recheck-daily-summary` manualmente (no solo por el Scheduler), aplicando la misma lógica de detección de novedades y regeneración condicional para el día actual.

#### Scenario: Ejecución manual detecta y regenera
- **WHEN** se ejecuta `bin/console app:recheck-daily-summary` y hay transcripciones nuevas desde el último `DailySummary` de hoy
- **THEN** el comando regenera el `DailySummary` de hoy y lo reenvía por Telegram, igual que si lo hubiera disparado el Scheduler

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

