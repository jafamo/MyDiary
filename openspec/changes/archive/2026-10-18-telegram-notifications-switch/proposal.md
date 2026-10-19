## Why

Producción y el stack Docker local usan el mismo `docker-compose.yml`, y el `messenger-worker` local también consume `scheduler_default`. Con el mismo bot y chat en el `.env` local, cada aviso programado llegaba dos veces a Telegram: los logs muestran `idle_day.notified` en producción y en local el 16/10 y el 17/10 a las 13:00 UTC (15:00 en Madrid). Hace falta poder apagar los envíos a Telegram por entorno, y en el futuro también en producción, sin tocar código.

## What Changes

- Nueva variable `TELEGRAM_NOTIFICATIONS_ENABLED` (booleana). Por defecto vale `true` en `config/services.yaml`, así que producción no cambia si no se define.
- Con `false`, `TelegramClient::sendMessage()` no llama a la API de Telegram y registra un log `telegram.message_skipped`. Afecta a todos los mensajes salientes: avisos programados, resumen diario, fallos y respuestas del webhook.
- `getFile()` y `downloadFile()` (descarga de audios) no se ven afectados.
- `.env.dev` (versionado y cargado solo con `APP_ENV=dev`) la pone a `false`, `.env.test` a `true` y `.env.example` la documenta.

## Capabilities

### New Capabilities

### Modified Capabilities
- `telegram-audio-pipeline`: se añade un requisito con el interruptor global de envíos salientes a Telegram.

## Impact

- `src/Service/Telegram/TelegramClient.php`, `config/services.yaml`, `.env.dev`, `.env.test`, `.env.example`.
- Tests: `tests/Service/Telegram/TelegramClientTest.php`.
- `Especificaciones.md`: variable documentada.
- Despliegue: ninguno obligatorio. Para silenciar producción, `TELEGRAM_NOTIFICATIONS_ENABLED=false` en su `.env.local` y reiniciar `diary-php` y `diary-messenger-worker`.
