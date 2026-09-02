## Context

`DailySummaryService::generateForDate` ya centraliza las tres vías de generación (Scheduler 21:00, comando manual, y disparo bajo demanda desde la web) y ya inyecta `TelegramClient` y `authorizedChatId` para el mensaje de fallo (`MESSAGE_FAILED`). No hace falta ninguna dependencia ni configuración nueva: solo añadir el envío en el camino de éxito.

## Goals / Non-Goals

**Goals:**
- Enviar el texto del `DailySummary` recién guardado por Telegram al chat autorizado, cada vez que la generación tiene éxito (incluye regeneraciones).
- Que un fallo al enviar el mensaje de Telegram no afecte a la generación/guardado ya completado del resumen.

**Non-Goals:**
- No se cambia el formato ni contenido del mensaje de fallo existente.
- No se añade reintento para el envío de la notificación de éxito (a diferencia de la generación del resumen, que ya tiene sus propios reintentos).
- No se envían los `Topic` por separado ni se formatea el mensaje más allá del texto plano del resumen (se puede añadir formato en un cambio posterior si se pide).

## Decisions

- **Dónde enganchar el envío**: dentro de `saveDailySummary`, justo después del `flush()`, en vez de en `generateForDate` tras llamar a `saveDailySummary`. Motivo: mantiene juntos "guardar" y "notificar tras guardar" y evita que quien llame a `saveDailySummary` en el futuro (si se extrae o reutiliza) se olvide de notificar. Alternativa considerada: notificar en `generateForDate` tras el `saveDailySummary()` — más simple de leer pero separa dos pasos que conceptualmente van juntos; se descarta por preferencia de cohesión, ambas son válidas.
- **Manejo de errores del envío**: el envío se envuelve en un `try/catch` de `\Throwable` (la llamada usa `HttpClientInterface`, que puede lanzar excepciones de transporte), registrando un log de error (`daily_summary.telegram_notification_failed`) pero sin relanzar. El `DailySummary` ya está persistido en ese punto — un fallo de Telegram no debe deshacer el guardado ni marcarse como fallo de generación (que tiene su propio mensaje y semántica distintos).
- **Reutilización**: se reutiliza `$this->telegramClient` y `$this->authorizedChatId`, ya presentes en el constructor — no se añade ninguna nueva dependencia.

## Risks / Trade-offs

- [Riesgo] Mensajes largos: Telegram limita los mensajes a 4096 caracteres; un resumen muy largo podría fallar al enviarse → Mitigación: se captura como cualquier otro fallo de envío (log + continuar), no bloquea el guardado. Si se detecta que ocurre en la práctica, se puede truncar en un cambio posterior.
- [Riesgo] Doble notificación en regeneraciones: si el usuario regenera manualmente el resumen desde la web, se reenvía el mensaje completo cada vez → Aceptado como comportamiento esperado (el usuario pidió explícitamente regenerar).
