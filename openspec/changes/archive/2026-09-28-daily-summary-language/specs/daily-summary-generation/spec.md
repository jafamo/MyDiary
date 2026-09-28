## ADDED Requirements

### Requirement: Resumen diario en castellano
El sistema SHALL pedir al modelo que escriba el resumen, los temas y los significados de la leyenda en castellano, y SHALL rechazar como fallo de generación (código `WRONG_LANGUAGE`) cualquier respuesta cuyo resumen, temas o leyenda contengan letras de un alfabeto no latino. Ese fallo SHALL seguir la política de reintentos de "Manejo de errores en la generación" (3 intentos en total): si ninguno llega en castellano, no se guarda el resumen y se notifica el fallo.

#### Scenario: Respuesta en japonés y reintento correcto
- **WHEN** el primer intento devuelve un resumen en japonés y el segundo en castellano
- **THEN** se guarda el resumen del segundo intento

#### Scenario: Tres respuestas en otro alfabeto
- **WHEN** los 3 intentos devuelven texto con letras no latinas
- **THEN** no se crea ni modifica el `DailySummary`, se registra `daily_summary.generation_failed` con `error_code: WRONG_LANGUAGE` y se envía la notificación de fallo por Telegram

#### Scenario: Castellano con emojis y acentos
- **WHEN** la respuesta está en castellano con emojis, tildes y `ñ`
- **THEN** se acepta

### Requirement: Log de cada intento fallido de generación
El sistema SHALL registrar un log `warning` con `event: daily_summary.generation_attempt_failed`, `error_code`, `error_message`, `attempt_number` y `max_attempts` por cada intento de generación del resumen que falle.

#### Scenario: Intento fallido seguido de éxito
- **WHEN** el primer intento falla y el segundo tiene éxito
- **THEN** queda un único log `daily_summary.generation_attempt_failed` con `attempt_number: 1`
