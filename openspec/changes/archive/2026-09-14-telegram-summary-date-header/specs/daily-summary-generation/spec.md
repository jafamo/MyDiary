## MODIFIED Requirements

### Requirement: Notificación por Telegram del resumen generado
El sistema SHALL enviar por Telegram al `authorizedChatId` una cabecera con icono y la fecha del resumen en español, seguida de una línea en blanco y el texto del `DailySummary`, inmediatamente después de guardarlo (o actualizarlo) con éxito, tanto en el disparo programado como en la ejecución manual por consola y en la generación bajo demanda desde la web. La cabecera SHALL tener el formato `📔 Resumen día: <día> de <mes> de <año>` (p. ej. `📔 Resumen día: 22 de septiembre de 2026`), usando el nombre del mes en español y la fecha del `DailySummary` (no la fecha de envío). Un fallo al enviar esta notificación SHALL registrarse en logs estructurados y NO SHALL revertir ni invalidar el `DailySummary` ya guardado.

#### Scenario: Notificación tras generación exitosa
- **WHEN** el `DailySummary` de una fecha se genera y guarda con éxito
- **THEN** el sistema envía por Telegram al `authorizedChatId` la cabecera con la fecha correspondiente seguida del texto del resumen

#### Scenario: Notificación tras regeneración
- **WHEN** el usuario regenera el `DailySummary` de una fecha que ya tenía uno
- **THEN** el sistema envía por Telegram la cabecera con la fecha correspondiente seguida del texto del resumen actualizado, sustituyendo al anterior

#### Scenario: Fallo de envío no afecta al resumen guardado
- **WHEN** el `DailySummary` se guarda con éxito pero el envío del mensaje a la API de Telegram falla (p. ej. error de red)
- **THEN** el `DailySummary` permanece guardado sin cambios, y el fallo de envío se registra en logs estructurados sin propagarse como error de generación
