## Context

`TelegramClient::sendMessage()` es el único punto por el que salen mensajes a Telegram (comandos programados, `DailySummaryService`, `TranscribeAudioMessageHandler`, `TranscriptionFailureListener`, `TelegramWebhookController`). El scheduler de Symfony corre en `diary-messenger-worker` tanto en producción como en local.

## Goals / Non-Goals

**Goals:**
- Apagar todos los envíos salientes a Telegram con una sola variable de entorno.
- Desactivados por defecto en dev, activos en producción y en test sin tocar su configuración.

**Non-Goals:**
- No se desactiva el scheduler ni la lógica de los comandos: siguen ejecutándose y registrando sus eventos (`idle_day.notified`, etc.).
- No afecta a la descarga de audios (`getFile`/`downloadFile`).

## Decisions

- **Interruptor en `TelegramClient`** frente a comprobarlo en cada aviso programado o desactivar el scheduler (elegido por el usuario). Es un único punto que cubre también los avisos futuros. Las alternativas dejaban la comprobación repartida en varios sitios o apagaban también la generación de resúmenes.
- **Bool por argumento del constructor** (`bool $notificationsEnabled`, enlazado con `%env(bool:TELEGRAM_NOTIFICATIONS_ENABLED)%`) y valor por defecto `env(TELEGRAM_NOTIFICATIONS_ENABLED): 'true'` en `parameters`, igual que `IDLE_DAY_*`.
- **Log al omitir**: `info` en el canal de la app con `event: telegram.message_skipped`, `telegram_chat_id` y `telegram_text_length`. Son campos planos con prefijo, y no se registra el texto. El `LoggerInterface` se inyecta en `TelegramClient`.
- **`false` en `.env.dev`**: está versionado y Symfony solo lo carga con `APP_ENV=dev`. Producción (`APP_ENV=prod`) no lo lee. Para activar los envíos en local se usa `.env.dev.local`. `.env.local` no sirve porque `.env.dev` se carga después.

## Risks / Trade-offs

- [Desactivado en producción por error → no llegan avisos] → El valor por defecto es `true` y cada mensaje omitido deja un log `telegram.message_skipped`, visible en Kibana.
- [En dev el bot tampoco responde a los audios] → En local el webhook no llega igualmente; se activa con `.env.dev.local` si hace falta.
