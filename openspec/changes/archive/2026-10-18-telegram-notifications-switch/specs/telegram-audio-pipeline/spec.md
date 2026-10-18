## ADDED Requirements

### Requirement: Interruptor de envíos salientes a Telegram
El sistema SHALL enviar mensajes a Telegram solo si `TELEGRAM_NOTIFICATIONS_ENABLED` es verdadera. Si no está definida, SHALL valer `true`. Con `false`, todo mensaje saliente (avisos programados, resumen diario, notificaciones de transcripción o fallo y respuestas del webhook) SHALL omitirse sin llamar a la API de Telegram, y SHALL registrarse un log `info` con `event: telegram.message_skipped`, `telegram_chat_id` y `telegram_text_length`, sin el texto del mensaje. La descarga de audios (`getFile` y descarga del fichero) MUST NOT verse afectada. El entorno `dev` SHALL tenerla a `false` por defecto (`.env.dev`).

#### Scenario: Envíos activados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `true` o no está definida y se envía un mensaje
- **THEN** el sistema llama a `sendMessage` de la API de Telegram como hasta ahora

#### Scenario: Envíos desactivados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `false` y se envía un mensaje (p. ej. el aviso de día sin audios)
- **THEN** el sistema no hace ninguna petición a la API de Telegram y registra `telegram.message_skipped` con `telegram_chat_id` y `telegram_text_length`

#### Scenario: Descarga de audios con envíos desactivados
- **WHEN** `TELEGRAM_NOTIFICATIONS_ENABLED` es `false` y se descarga un audio recibido
- **THEN** `getFile` y la descarga del fichero siguen llamando a Telegram con normalidad
