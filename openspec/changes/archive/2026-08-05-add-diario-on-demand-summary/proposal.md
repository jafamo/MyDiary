## Why

Hoy el resumen diario solo se genera automáticamente a las 21:00 (o manualmente por consola, `app:generate-daily-summary`). Si el usuario quiere ver el resumen antes de esa hora, o si la generación automática falló y no quiere esperar al día siguiente ni tiene acceso a la consola del servidor, no tiene forma de disparar la generación desde la web.

## What Changes

- Nuevo botón "Generar resumen" en la vista Diario, visible cuando el día actual todavía no tiene `DailySummary` o cuando lo tiene pero se quiere regenerar (p. ej. tras corregir manualmente una transcripción).
- Nueva ruta `POST /diario/resumen` que reutiliza `DailySummaryService::generateForDate()` para el día actual, sin esperar a las 21:00.
- `DailySummaryService::generateForDate()` gana un parámetro para omitir la espera por transcripciones `PENDING` (`waitForPending: bool`, `true` por defecto para no romper el comando programado): el disparo manual desde la web no debe bloquear la petición HTTP hasta 3 minutos esperando audios que todavía se están transcribiendo.
- Se ejecuta de forma síncrona dentro de la petición (sin Symfony Messenger, que en este proyecto está reservado a la cadena Telegram → transcripción) — el fallo de generación ya notifica por Telegram como hoy; no se añade UI de error nueva.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `daily-summary-generation`: nuevo requisito de disparo manual desde la web (además del ya existente disparo manual por consola), y ajuste del requisito de espera por pendientes para que sea opcional según el origen del disparo.
- `web-views`: la vista Diario gana el botón "Generar resumen".

## Impact

- Código: `src/Service/DailySummaryService.php` (nuevo parámetro), nuevo `src/Controller/DailySummaryController.php` (o método añadido a un controlador existente), `templates/diario/index.html.twig`.
- BD: sin cambios de esquema.
- Sin cambios de infraestructura (no se usa Messenger para esto, por restricción explícita del proyecto).
