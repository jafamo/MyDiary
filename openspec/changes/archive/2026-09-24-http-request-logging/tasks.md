## 1. Log por petición HTTP

- [x] 1.1 Declarar el canal `http` en `config/packages/monolog.yaml`
- [x] 1.2 Crear `src/EventListener/HttpRequestLogListener.php` (`kernel.terminate`, excluye `/_*`, nivel según código)
- [x] 1.3 Tests del listener: 200 → info, 404 → warning, 500 → error, `/_wdt` sin log

## 2. Eventos de negocio

- [x] 2.1 Log `audio_recording.received` en `TelegramWebhookController` y tests
- [x] 2.2 Log `transcription.created` en `TranscribeAudioMessageHandler` y tests

## 3. Configuración de prod

- [x] 3.1 Handler `structured` sin buffer para `http`, `app` y `messenger` en `when@prod`, excluyéndolos del `fingers_crossed` principal
- [x] 3.2 Comprobar con `APP_ENV=prod` que una petición 200 escribe su línea `http` una sola vez

## 4. Verificación y documentación

- [x] 4.1 `make test`, `make cs-check`, `make phpstan` en verde
- [x] 4.2 Actualizar `Especificaciones.md` (logs/Kibana), el `Purpose` de `openspec/specs/structured-logging/spec.md` y `CHANGELOG.md` (`[Sin publicar]`)
