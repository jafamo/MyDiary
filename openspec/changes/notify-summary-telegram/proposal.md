## Why

Hoy el resumen diario solo se puede consultar entrando en la vista Diario. El usuario quiere recibirlo también por Telegram en el momento en que se genera, igual que ya recibe la notificación cuando falla la generación, para poder leerlo sin abrir la web.

## What Changes

- Tras generar (o regenerar) con éxito el `DailySummary` del día, el sistema envía el texto del resumen por Telegram al `authorizedChatId`, además de guardarlo en BD como hasta ahora.
- Se aplica tanto al disparo programado (Scheduler 21:00), como a la ejecución manual por consola y a la generación bajo demanda desde la web — las tres vías comparten la misma lógica en `DailySummaryService::generateForDate`.
- Si el envío por Telegram falla (p. ej. error de red con la API de Telegram), el `DailySummary` ya guardado en BD NO se revierte; el fallo de notificación se registra en logs pero no se trata como fallo de generación.
- No cambia el formato del mensaje de fallo existente ("No se pudo generar el resumen de hoy ⚠️"); se añade un nuevo mensaje de éxito con el propio texto del resumen.

## Capabilities

### New Capabilities

(ninguna)

### Modified Capabilities

- `daily-summary-generation`: se añade el requisito de notificar por Telegram el resumen generado tras un guardado exitoso (nuevo requirement + scenario), sin alterar los requisitos existentes de generación, reintentos o manejo de errores.

## Impact

- `src/Service/DailySummaryService.php`: tras `saveDailySummary`, invocar `TelegramClient::sendMessage` con el texto del resumen; envolver en try/catch para no propagar fallos de red de Telegram como fallo de generación.
- Reutiliza `TelegramClient` y `$authorizedChatId`, ya inyectados en el servicio para el mensaje de fallo — sin nuevas dependencias ni configuración.
- Tests: `tests/` para `DailySummaryService` (o su suite equivalente) — cubrir que se llama a `sendMessage` con el texto del resumen en el caso de éxito, y que un fallo de Telegram no impide que el `DailySummary` quede guardado.
