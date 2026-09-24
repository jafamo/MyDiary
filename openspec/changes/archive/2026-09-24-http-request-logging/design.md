## Context

- nginx ya escribe su access log en JSON con `status` numérico; Filebeat lo indexa en el mismo data stream que los logs de PHP, así que `status` está mapeado como número.
- En `prod`, `monolog.yaml` usa `fingers_crossed` (`action_level: error`, `buffer_size: 50`) para todo menos `deprecation`: los INFO se descartan si no hay un error en la misma petición o mensaje del worker (el worker resetea el handler entre mensajes). Por eso en Kibana solo aparecen algunos INFO del worker: los que estaban en el buffer cuando hubo un error.
- El webhook de Telegram (`TelegramWebhookController`) es la única "API": responde 200 `{"ok":true}` siempre (404 si el token no coincide). La transcripción ocurre en el worker (`TranscribeAudioMessageHandler`), fuera de cualquier petición HTTP.

## Goals / Non-Goals

**Goals:**
- Un `status` HTTP numérico por petición en los logs de PHP, en éxitos y en errores.
- Rastro explícito de la creación de audios y transcripciones.
- Que esos INFO lleguen a Kibana en `prod`.

**Non-Goals:**
- Cambiar los códigos de respuesta del webhook (se queda en 200; Telegram solo mira si es 2xx y su documentación usa 200).
- Correlación por `request_id` entre líneas de la misma petición.
- Cambiar el nivel de detalle del resto de canales (`doctrine`, `request`, `security`...), que siguen en `fingers_crossed`.

## Decisions

- **`kernel.terminate` en vez de `kernel.response`**: se ejecuta después de enviar la respuesta (no suma latencia) y ya tiene el código final, incluido el de las excepciones convertidas en respuesta por el `ErrorListener`. Symfony solo lo lanza para la petición principal.
- **Canal `http` propio** (`#[WithMonologChannel('http')]`, declarado en `monolog.channels`): permite enrutarlo a un handler sin buffer y filtrarlo en Kibana con `channel: http`. Alternativa descartada: añadir `status` a los logs del canal `request` de Symfony con un processor: esos logs se emiten antes de conocer la respuesta.
- **Duración** desde `REQUEST_TIME_FLOAT` del servidor hasta `kernel.terminate`, en ms enteros.
- **Handler `structured` sin buffer en `prod`** para `http`, `app` y `messenger` (`level: info`), excluidos del `fingers_crossed` principal para no duplicar. Coste: un error del canal `app` ya no vuelca el buffer DEBUG de otros canales (`doctrine`...); se asume, porque los errores de `app` ya llevan su propio contexto (`event`, `error_code`...). Los errores no capturados se registran en el canal `request` y siguen activando el volcado.
- **Webhook**: el log `audio_recording.received` se escribe en el controlador (conoce el resultado y el código HTTP) con `result` en minúsculas derivado del nombre del caso del enum. Solo para audios de chats autorizados; los updates sin audio no se registran (ya quedan en la línea `http.request`).

## Risks / Trade-offs

- [Más volumen en `prod`] → una línea por petición y los INFO del scheduler cada 15 min; asumible con un solo usuario y 60 días de retención.
- [Escaneos de bots contra rutas inexistentes generan `warning` 404] → se filtran fácilmente en Kibana por `status`; nginx ya los registraba igual.
- [Dos handlers escriben al mismo fichero] → escrituras en modo append de líneas completas; ya pasa hoy entre `diary-php` y `diary-messenger-worker`.
