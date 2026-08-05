## 1. Preparación

- [x] 1.1 Confirmar/instalar `symfony/http-client`
- [x] 1.2 Instalar `symfony/monolog-bundle` y configurar `config/packages/monolog.yaml` con handler de fichero + `monolog.formatter.json`, escribiendo a `/var/log/php-diary/app-%kernel.environment%.log`
- [x] 1.3 Añadir variables a `.env`/`.env.example`: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_AUTHORIZED_CHAT_ID`, `TELEGRAM_WEBHOOK_TOKEN`, `APP_PUBLIC_URL=https://diary.jfarinos.keenetic.pro`, `OPENWEBUI_STT_BASE_URL` (ya prevista, confirmar valor real `192.168.4.200:9006`), `OPENWEBUI_API_KEY` — todos los valores reales configurados (`TELEGRAM_AUTHORIZED_CHAT_ID=8121663`)

## 2. Cliente de Telegram

- [x] 2.1 `src/Service/Telegram/TelegramClient.php`: `sendMessage(int $chatId, string $text)`, `getFile(string $fileId): array`, `downloadFile(string $filePath): string` (ruta local del fichero descargado)
- [x] 2.2 Tests unitarios de `TelegramClient` con `MockHttpClient`

## 3. Transcripción (Open WebUI)

- [x] 3.1 `src/Contract/TranscriberInterface.php` y `TranscriptionException` (con `errorCode` y `errorMessage` descriptivo)
- [x] 3.2 `src/Service/Whisper/WhisperTranscriber.php` implementa `TranscriberInterface` contra Open WebUI, timeout generoso configurado (120s), mensajes de error descriptivos en castellano
- [x] 3.3 Tests unitarios de `WhisperTranscriber` con `MockHttpClient` (éxito, timeout, error HTTP) — verificar contenido de `errorMessage`, no solo `errorCode`

## 4. Flujo de recepción (3.1)

- [x] 4.1 `src/Service/AudioRecordingService.php`: método `receive()` con la lógica de dedupe (mensaje, fichero, estados `ERROR`/`PENDING`/`TRANSCRIBED`) y creación de `AudioRecording`
- [x] 4.2 `src/Controller/TelegramWebhookController.php`: valida token de URL, parsea update, whitelist de `chat_id` (log estructurado si se rechaza), delega en `AudioRecordingService`, traduce resultado a respuesta Telegram
- [x] 4.3 Tests unitarios de `AudioRecordingService` (los 4 casos: nuevo, duplicado mensaje, duplicado fichero, reintento tras error)
- [x] 4.4 Test del controlador (`KernelTestCase` + cliente HTTP de test) para token inválido, chat no autorizado, y audio nuevo

## 5. Flujo de transcripción (3.2)

- [x] 5.1 `src/Message/TranscribeAudioMessage.php` (solo `audioRecordingId`)
- [x] 5.2 `src/MessageHandler/TranscribeAudioMessageHandler.php`: llama al transcriptor, guarda `Transcription` (BD + export a `var/transcriptions/`), marca `TRANSCRIBED`, notifica por Telegram
- [x] 5.3 Configurar `retry_strategy` del transporte `async` en `config/packages/messenger.yaml` (3 reintentos, backoff x2, 5s/10s/20s, tope 60s) y routing de `TranscribeAudioMessage` → `async`
- [x] 5.4 `src/EventListener/TranscriptionFailureListener.php`: escucha `WorkerMessageFailedEvent`, si `willRetry() === false` y el mensaje es `TranscribeAudioMessage`, marca `ERROR` con `error_code`/`error_message` (descriptivo), notifica *"No se pudo transcribir este audio ❌"*, y registra log JSON estructurado (`event: transcription.retry_exhausted`, `audio_recording_id`, `error_code`, `error_message`, `exception_class`)
- [x] 5.5 Log estructurado de cada intento fallido dentro del handler antes de relanzar la excepción (`event: transcription.attempt_failed`)
- [x] 5.6 Tests unitarios del handler (éxito) y del listener (fallo definitivo vs. reintento en curso)

## 6. Comando de registro del webhook

- [x] 6.1 `src/Command/SetTelegramWebhookCommand.php` (`app:telegram:set-webhook`)
- [x] 6.2 Test del comando (mock de `TelegramClient`/HTTP)

## 7. Verificación

- [x] 7.1 `make test` pasa en verde con todos los tests nuevos — 37 tests, 92 assertions
- [x] 7.2 `make cs-check` pasa sin errores sobre el código nuevo — 0/43 ficheros con violaciones
- [x] 7.3 Verificar que `${LOGS_PATH}/php/app-dev.log` (o equivalente) contiene líneas JSON válidas tras forzar un evento real (chat rechazado vía curl al webhook) — JSON válido confirmado, incluye `event`, `chat_id`, `telegram_message_id` como campos estructurados. *(Hallazgo)*: `php-fpm` corre como `www-data` (uid 33) dentro de `diary-php`, distinto del usuario root usado por `docker compose exec`; hubo que `chown -R 33:33` los bind mounts `logs/php`, `logs/messenger-worker`, `data/audio`, `data/transcriptions` para que las peticiones HTTP reales puedan escribir logs/ficheros (mismo patrón de permisos ya documentado para `diary-redis` en el change de Docker)
- [x] 7.4 Prueba manual end-to-end (parcial): `app:telegram:set-webhook` ejecutado con éxito contra `https://diary.jfarinos.keenetic.pro`, Telegram confirma el registro (`getWebhookInfo` → `ok: true`). **Bloqueado más allá de esto**: `https://diary.jfarinos.keenetic.pro` responde actualmente con la página del propio router Keenetic (`Server: Web server`, `Ndm-Sysmode: router`), no con `diary-nginx` — falta configurar el port-forward/reverse-proxy en el router hacia el puerto 9008 de esta máquina. Confirmado que `localhost:9008` sí procesa correctamente el webhook (log JSON de la tarea 7.3). Pendiente de que el usuario resuelva el enrutamiento de red antes de poder enviar un audio real desde Telegram.
