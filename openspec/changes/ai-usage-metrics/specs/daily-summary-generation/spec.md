## MODIFIED Requirements

### Requirement: Notificación por Telegram del resumen generado
El sistema SHALL enviar por Telegram al `authorizedChatId` una cabecera con icono y la fecha del resumen en español, seguida de una línea en blanco y el texto del `DailySummary`; si el resumen tiene `Topic`, una línea en blanco y la línea `🏷️ <tema1> · <tema2> · ...`; si tiene leyenda de emojis, una línea en blanco y la línea `<emoji> <significado> · <emoji> <significado> · ...`; y, al final, una línea en blanco y la línea de métricas `🧮 <n> audios · <total> tokens (<entrada> entrada + <salida> salida) · ⏱️ <tiempo> · 🤖 <modelo>`, inmediatamente después de guardarlo (o actualizarlo) con éxito, tanto en el disparo programado como en la ejecución manual por consola y en la generación bajo demanda desde la web. `<n>` es el número de transcripciones enviadas al generador; los números SHALL formatearse con separador de miles `.`; `<tiempo>` se expresa en segundos (`38 s`) si es menor de un minuto y en minutos y segundos (`1 min 05 s`) en caso contrario. Si faltan los tokens, la parte de tokens SHALL omitirse del mensaje; el modelo SHALL mostrarse siempre. La cabecera SHALL tener el formato `📔 Resumen día: <día> de <mes> de <año>` (p. ej. `📔 Resumen día: 22 de septiembre de 2026`), usando el nombre del mes en español y la fecha del `DailySummary` (no la fecha de envío). Un fallo al enviar esta notificación SHALL registrarse en logs estructurados y NO SHALL revertir ni invalidar el `DailySummary` ya guardado.

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
