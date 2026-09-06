## 1. Implementación

- [x] 1.1 En `DailySummaryService::saveDailySummary`, tras el `flush()`, enviar el texto del resumen por Telegram vía `$this->telegramClient->sendMessage((int) $this->authorizedChatId, $summaryText)`.
- [x] 1.2 Envolver ese envío en un `try/catch (\Throwable)` y registrar un log de error (`event: daily_summary.telegram_notification_failed`, con la fecha) si falla, sin relanzar la excepción.

## 2. Tests

- [x] 2.1 Test: generación exitosa llama a `TelegramClient::sendMessage` con el `authorizedChatId` y el texto del resumen guardado.
- [x] 2.2 Test: regeneración de un `DailySummary` existente también dispara el envío con el texto actualizado.
- [x] 2.3 Test: si `TelegramClient::sendMessage` lanza una excepción, el `DailySummary` queda igualmente guardado (no se relanza la excepción hacia el llamador) y se registra el log de error.

## 3. Verificación

- [x] 3.1 `make cs-check` y `make test` en verde.
- [x] 3.2 Actualizar `Especificaciones.md` si describe el flujo de notificación de resumen, para reflejar el nuevo envío de éxito.
