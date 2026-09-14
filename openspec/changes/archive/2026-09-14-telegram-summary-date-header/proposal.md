## Why

El mensaje de Telegram con el resumen diario contiene únicamente el texto generado, sin ninguna referencia a qué día corresponde. Al recibir varios resúmenes a lo largo del tiempo (o revisar el historial de chat), no es inmediato identificar la fecha de cada uno sin abrir la app y consultar el Diario.

## What Changes

- Anteponer al texto del resumen enviado por Telegram una cabecera con icono y fecha en español, con el formato `📔 Resumen día: <día> de <mes> de <año>` (p. ej. `📔 Resumen día: 22 de septiembre de 2026`), seguida de una línea en blanco antes del texto del resumen.
- Aplica únicamente al mensaje de éxito (`notifySummaryGenerated`); el mensaje de fallo (`"No se pudo generar el resumen de hoy ⚠️"`) no cambia.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `daily-summary-generation`: el requisito "Notificación por Telegram del resumen generado" pasa a exigir que el mensaje incluya una cabecera con fecha (en español) e icono antes del texto del resumen.

## Impact

- `src/Service/DailySummaryService.php` (`notifySummaryGenerated`).
- `tests/Service/DailySummaryServiceTest.php` (aserciones sobre el texto exacto enviado a Telegram).
