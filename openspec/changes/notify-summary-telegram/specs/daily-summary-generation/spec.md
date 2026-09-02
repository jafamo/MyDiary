## ADDED Requirements

### Requirement: Notificación por Telegram del resumen generado
El sistema SHALL enviar por Telegram al `authorizedChatId` el texto del `DailySummary` inmediatamente después de guardarlo (o actualizarlo) con éxito, tanto en el disparo programado como en la ejecución manual por consola y en la generación bajo demanda desde la web. Un fallo al enviar esta notificación SHALL registrarse en logs estructurados y NO SHALL revertir ni invalidar el `DailySummary` ya guardado.

#### Scenario: Notificación tras generación exitosa
- **WHEN** el `DailySummary` de una fecha se genera y guarda con éxito
- **THEN** el sistema envía por Telegram al `authorizedChatId` el texto del resumen

#### Scenario: Notificación tras regeneración
- **WHEN** el usuario regenera el `DailySummary` de una fecha que ya tenía uno
- **THEN** el sistema envía por Telegram el texto del resumen actualizado, sustituyendo al anterior

#### Scenario: Fallo de envío no afecta al resumen guardado
- **WHEN** el `DailySummary` se guarda con éxito pero el envío del mensaje a la API de Telegram falla (p. ej. error de red)
- **THEN** el `DailySummary` permanece guardado sin cambios, y el fallo de envío se registra en logs estructurados sin propagarse como error de generación
