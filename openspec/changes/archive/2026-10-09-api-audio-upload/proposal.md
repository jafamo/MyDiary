## Why

Hoy un audio solo puede entrar por el bot de Telegram: `AudioRecording` exige `telegram_message_id` y `telegram_file_unique_id`, y no hay ningún endpoint que reciba un fichero. Es el bloqueo 3 del `ROADMAP.md` y la fase 5, la primera que hace útil la app de iPhone: grabar y subir un audio sin pasar por Telegram.

## What Changes

- Nuevo endpoint `POST /api/v1/audios` (multipart, campo `file`): guarda el fichero, crea el `AudioRecording` en `PENDING` y despacha `TranscribeAudioMessage`. A partir de ahí el audio sigue la misma cadena que uno de Telegram.
- Respuesta siempre con el audio (`id`, `status`, `source`, `duration_seconds`, `received_at`) y un campo `result`: `created` (`201`), `duplicate` (`200`) o `retrying` (`200`, si el audio existente estaba en `ERROR` y se relanza).
- Modelo de datos (`audio_recording`): `telegram_message_id` y `telegram_file_unique_id` pasan a opcionales (siguen siendo únicos cuando existen); nuevo `source` (`telegram` / `app`; los existentes quedan como `telegram`); nuevo `content_hash` (SHA-256 del fichero, único) para deduplicar subidas repetidas.
- Duración calculada en servidor con `ffprobe` (decisión D3 del roadmap, opción B), detrás de un puerto `AudioProbeInterface` con implementación real y un doble para tests. La imagen PHP incorpora `ffmpeg`.
- Validación de la subida por el contenido real del fichero: formatos `m4a`, `mp3`, `ogg` y `wav`, tamaño máximo de 25 MB.
- Límites de subida: `client_max_body_size 30m` en nginx y `upload_max_filesize = 25M` / `post_max_size = 30M` en la imagen PHP (hoy 1 MB y 2 MB).
- Avisos: un audio de la app se comporta igual que uno de Telegram; el handler no distingue el origen y sigue enviando por Telegram «Transcripción lista» y el aviso de fallo.
- El fichero de texto de una transcripción se nombra con el `content_hash` cuando el audio no tiene `telegram_file_unique_id`.
- Logs: evento `audio.uploaded` con campos planos `audio_source`, `audio_content_hash` y `audio_upload_result`.
- Documentación del endpoint en el mismo cambio: atributos OpenAPI, `doc/openapi.json`, colección Postman y `doc/api.http`.

## Capabilities

### New Capabilities

Ninguna: la subida es un endpoint más de la capacidad `api`.

### Modified Capabilities

- `api`: nuevo requisito de subida de audio (`POST /api/v1/audios`), con su validación, deduplicación y respuestas.
- `data-model`: `AudioRecording` deja de exigir los identificadores de Telegram y gana `source` y `content_hash`.
- `telegram-audio-pipeline`: la transcripción asíncrona admite audios sin identificadores de Telegram (nombre del fichero exportado) y avisa igual sea cual sea el origen.
- `structured-logging`: nuevo log de subida de audio por la API.
- `docker-infrastructure`: la imagen PHP incluye `ffmpeg` y los límites de subida de nginx y PHP permiten audios de 25 MB.

## Impact

- **Código:** `AudioRecording`, nuevo enum `AudioSource`, `AudioRecordingRepository`, `AudioRecordingService`, nuevo `AudioUploadService`, puerto `App\Contract\AudioProbeInterface` + `FfprobeAudioProbe`, nuevo `Api\AudioController`, `TranscribeAudioMessageHandler` y `TranscriptionFailureListener` (nombre de fichero y campos de log).
- **Base de datos:** una migración sobre `audio_recording`.
- **Dependencias:** `symfony/process`; `ffmpeg` en la imagen Docker de PHP.
- **Infraestructura:** `docker/php/Dockerfile`, nuevo `.ini` de PHP y `docker/nginx/default.conf`.
- **Documentación:** `Especificaciones.md` (3.1, 3.2, 3.7, 4 y 6), `AGENTS.md` (lista de puertos), `ROADMAP.md`, `doc/openapi.json`, `doc/MyDiary.postman_collection.json`, `doc/api.http` y `CHANGELOG.md`.
- **Despliegue:** reconstruir la imagen PHP, recrear `diary-php`, `diary-messenger-worker` y `diary-nginx`, `make composer-install` y `make migrate`.
- **Sin cambios:** el webhook de Telegram, las vistas web y el resto de endpoints.
