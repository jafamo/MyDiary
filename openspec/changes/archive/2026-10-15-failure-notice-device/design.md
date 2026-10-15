## Decisiones

- **Copia del nombre, no relación con `api_token`.** Los tokens se revocan (se borran) y el aviso puede llegar después; además el nombre del dispositivo es lo único que interesa. `device_name` `varchar(100)` como `api_token.name`.
- **`retrying` actualiza el dispositivo.** El que reenvía es quien está esperando el resultado; es lo mismo que hace el webhook con `telegram_message_id`.
- **Texto del aviso** (elegido por el usuario):

  ```
  No se pudo transcribir la nota ❌
  📱 Google Pixel 7 (Android 16)
  🕐 15/10 05:02 · 0:14
  ```

  Origen: `📱 <device_name>`, `📱 App` (audio de la app sin dispositivo) o `💬 Telegram`. Hora en `LocalTimezone`, duración con `UsageFormatter::audioDuration`.
- **Respuesta al mensaje original** en los de Telegram con `reply_parameters` y `allow_sending_without_reply`, para que un mensaje borrado no impida el aviso.
- **Fuera de alcance:** exponer `device_name` en la API o en la web.
