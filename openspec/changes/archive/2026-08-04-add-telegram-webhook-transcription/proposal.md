## Why

Es el paso 3 del orden de construcción de `Especificaciones.md` (sección 9): con Symfony/Doctrine/entidades ya en pie, toca la funcionalidad central del producto — recibir audios por Telegram y transcribirlos. Sin esto no hay datos que mostrar en ninguna vista futura.

## What Changes

- Añadir `TelegramWebhookController`: endpoint `POST /telegram/webhook/{token}` que recibe los updates de Telegram, valida el token en la URL (evita que cualquiera descubra el endpoint por fuerza bruta) y el `chat_id` autorizado (`TELEGRAM_AUTHORIZED_CHAT_ID`), aplica el dedupe por `telegram_message_id` (reintento del propio webhook) y por `telegram_file_unique_id` (reenvío del usuario, incluyendo el caso de reintento automático sobre un `AudioRecording` en `ERROR`), descarga el audio, crea el `AudioRecording`, responde rápido al usuario y despacha `TranscribeAudioMessage` — flujo 3.1 completo.
- Añadir `TelegramClient` (servicio propio sobre `symfony/http-client`, sin SDK de terceros): `sendMessage`, `getFile`, descarga de fichero.
- Añadir `TranscriberInterface` + `WhisperTranscriber` (implementación contra Open WebUI, `OPENWEBUI_STT_BASE_URL`).
- Añadir `TranscribeAudioMessage` + `TranscribeAudioMessageHandler`: llama al transcriptor, guarda `Transcription` (BD + export a fichero), pasa `AudioRecording` a `TRANSCRIBED`, notifica al usuario — flujo 3.2, pasos 1-5.
- Configurar `retry_strategy` del transporte `async` de Messenger (reintentos con backoff) y un listener que, al agotarse los reintentos, marca el `AudioRecording` como `ERROR` con `error_code`/`error_message`, y notifica al usuario — flujo 3.2, paso 6.
- Añadir `AudioRecordingService` para encapsular la lógica de creación/dedupe/reintento (mantiene el controlador fino, según convención de `CLAUDE.md`: servicios de aplicación normales, sin CQRS).
- Añadir comando `app:telegram:set-webhook` para registrar el webhook en Telegram (mismo patrón que los comandos `app:user:*` ya existentes). Dominio público: `https://diary.jfarinos.keenetic.pro`.
- Instalar y configurar `symfony/monolog-bundle` (previsto en el stack de `Especificaciones.md` pero no instalado todavía) con salida en JSON estructurado — el usuario visualiza logs en Kibana y necesita contexto (`event`, `audio_recording_id`, `error_code`, etc.) para localizar el origen de cada fallo sin depender de texto libre.

## Capabilities

### New Capabilities
- `telegram-audio-pipeline`: recepción de audios por webhook de Telegram, deduplicación, descarga, transcripción asíncrona vía Messenger, y manejo de errores/reintentos técnicos.

### Modified Capabilities
(ninguna — no cambia el comportamiento de `docker-infrastructure`, `symfony-application-bootstrap`, `data-model`, `code-style-enforcement` ni `automated-testing`, solo se construye sobre ellas)

## Impact

- Ficheros nuevos: `src/Controller/TelegramWebhookController.php`, `src/Service/AudioRecordingService.php`, `src/Service/Telegram/TelegramClient.php`, `src/Service/Whisper/WhisperTranscriber.php`, `src/Contract/TranscriberInterface.php`, `src/Message/TranscribeAudioMessage.php`, `src/MessageHandler/TranscribeAudioMessageHandler.php`, listener de fallo de Messenger, tests correspondientes.
- Ficheros modificados: `config/packages/messenger.yaml` (routing + `retry_strategy`), `.env`/`.env.example` (variables de Telegram/Open WebUI ya previstas en `Especificaciones.md` sección 7, pendientes de fijar valores reales de `TELEGRAM_BOT_TOKEN` y `TELEGRAM_AUTHORIZED_CHAT_ID`).
- No incluye: resumen diario (Ollama/Scheduler, paso 4), vistas Twig (paso 5), ni edición/eliminación/botón "Reintentar" desde la web (paso 6, requiere las vistas). El reintento automático por reenvío de Telegram (3.1 paso 4, dedupe por `telegram_file_unique_id`) sí se incluye porque es parte del propio webhook.
- Requiere acceso de red real a `api.telegram.org` y a `192.168.4.200:9006` (Open WebUI) desde `diary-php`/`diary-messenger-worker` — a verificar durante la implementación.
