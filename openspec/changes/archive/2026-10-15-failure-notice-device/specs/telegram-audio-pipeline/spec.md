## MODIFIED Requirements

### Requirement: Reintentos con backoff y fallo definitivo
El sistema SHALL reintentar automáticamente `TranscribeAudioMessage` un número limitado de veces con backoff si la transcripción falla. Al agotar los reintentos, SHALL marcar el `AudioRecording` como `ERROR` con `error_code`/`error_message`, y SHALL notificar al usuario por Telegram con un mensaje de tres líneas: «No se pudo transcribir la nota ❌», el origen (`📱 <device_name>` en los audios de la app, `📱 App` si no tienen dispositivo y `💬 Telegram` en los de Telegram) y `🕐 <dd/mm> <HH:MM> · <m:ss>` con el día y la hora de `received_at` en la zona horaria local y la duración. En los audios de Telegram el aviso SHALL enviarse como respuesta al mensaje original y MUST enviarse igualmente si ese mensaje ya no existe.

#### Scenario: Fallo agotando reintentos
- **WHEN** todas las tentativas de transcripción de un `TranscribeAudioMessage` fallan (según el `retry_strategy` configurado)
- **THEN** el `AudioRecording` correspondiente pasa a `ERROR` con `error_code` (clave corta) y `error_message` (frase descriptiva en castellano, no solo una clave) describiendo la causa, y se notifica al usuario por Telegram con el aviso de fallo

#### Scenario: Fallo transitorio con reintento posterior exitoso
- **WHEN** la primera tentativa de transcripción falla pero un reintento posterior tiene éxito
- **THEN** el `AudioRecording` termina en `TRANSCRIBED`, no en `ERROR`, y no se envía la notificación de fallo

#### Scenario: Aviso de un audio de la app
- **WHEN** se agotan los reintentos de un audio de la app con `device_name` «Google Pixel 7 (Android 16)», recibido el 2026-10-15 a las 03:02 UTC y de 14 segundos
- **THEN** el aviso dice «No se pudo transcribir la nota ❌», «📱 Google Pixel 7 (Android 16)» y «🕐 15/10 05:02 · 0:14», y no responde a ningún mensaje

#### Scenario: Aviso de un audio de la app sin dispositivo
- **WHEN** se agotan los reintentos de un audio de la app sin `device_name`
- **THEN** la línea de origen del aviso es «📱 App»

#### Scenario: Aviso de un audio de Telegram
- **WHEN** se agotan los reintentos de un audio de Telegram
- **THEN** la línea de origen del aviso es «💬 Telegram» y el aviso se envía como respuesta a su `telegram_message_id`, permitiendo el envío aunque el mensaje original ya no exista
