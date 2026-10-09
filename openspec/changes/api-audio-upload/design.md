## Context

La captura solo entra por el webhook de Telegram. `AudioRecordingService::receive()` deduplica por `telegram_message_id` y `telegram_file_unique_id`, ambos obligatorios y únicos en `audio_recording`, y Telegram entrega la duración ya calculada. El `TranscribeAudioMessageHandler` nombra el `.txt` exportado con `telegram_file_unique_id` y avisa siempre por Telegram.

La API (`/api/v1`, fase 2) ya tiene autenticación por token, formato único de error y la regla de documentar cada endpoint. nginx admite hoy 1 MB por petición y PHP 2 MB por fichero. Los tests corren en GitHub CI sobre el runner, sin la imagen Docker, así que allí no hay `ffprobe`.

## Goals / Non-Goals

**Goals:**

- Subir un audio por la API y que entre en la misma cadena de transcripción que uno de Telegram.
- Deduplicar subidas repetidas (reintentos de la app tras un corte de red) sin que la app tenga que tratar un error.
- No cambiar el comportamiento del webhook de Telegram.

**Non-Goals:**

- Escuchar o descargar el audio (fase 6) y listar audios (fase 3).
- Deduplicar entre orígenes: un mismo audio enviado por Telegram y por la app son dos registros. Los audios de Telegram no calculan `content_hash`.
- Convertir formatos en servidor: `ffmpeg` se instala solo por `ffprobe`.
- Elegir canal de avisos por origen (se revisa en la fase 8, con el push).

## Decisions

### 1. Duración con `ffprobe` detrás de un puerto

`App\Contract\AudioProbeInterface::probe(string $path): AudioProbeResult` devuelve `format` (`m4a`, `mp3`, `ogg`, `wav`) y `durationSeconds`, y lanza `AudioProbeException` si el fichero no es un audio admitido. Implementación `App\Service\Audio\FfprobeAudioProbe` con `symfony/process` (`ffprobe -v error -show_entries format=format_name,duration:stream=codec_type -of json`). En el entorno `test` se sustituye por un doble, igual que el transcriptor.

- **Por qué un puerto:** misma razón que `TranscriberInterface`: dependencia externa que no existe en CI. Se añade a la lista de puertos de `AGENTS.md`.
- **Por qué el puerto devuelve también el formato:** la validación «por contenido real» y la duración salen de la misma llamada a `ffprobe`; adivinar el tipo con `finfo` clasifica los `.m4a` de forma irregular (`audio/x-m4a`, `video/mp4`).
- **Alternativas descartadas:** clase concreta instalando `ffmpeg` en CI (tests atados al binario); que la app envíe la duración (opción A de D3, descartada por el usuario).
- La implementación real se cubre con un test que se salta si no hay `ffprobe`; en local corre dentro de `diary-php`.

### 2. Dónde vive cada pieza

- `App\Service\AudioUploadService`: recibe la ruta del fichero subido, valida el tamaño (`MAX_BYTES`, 25 MB), calcula el SHA-256 y delega en `AudioRecordingService`. Solo para audios nuevos sondea el fichero y lo mueve a `var/audio/<content_hash>.<formato>`.
- `AudioRecordingService::receiveUpload(string $contentHash, callable $storeFile)`: réplica de `receive()` para el origen `app`. `$storeFile` devuelve ruta y duración y solo se invoca si el audio no existe. `receive()` no cambia, salvo fijar `source = telegram`.
- `App\Controller\Api\AudioController`: lee `file`, llama al servicio y serializa. Los errores de validación del servicio (`InvalidAudioUploadException`) se convierten en `ApiException::validationFailed` (`422`).
- `App\Entity\AudioSource`: enum `telegram` / `app`, junto a `AudioRecordingStatus`.

El límite de 25 MB es una constante de `AudioUploadService` (no una variable de entorno): nginx y PHP tienen su propio tope fijo en la imagen y los tres deben moverse juntos.

### 3. Deduplicación por `content_hash`

- Existe y está en `ERROR` → se reinicia a `PENDING` y se relanza (`retrying`), reutilizando `retryAfterError()`.
- Existe en `PENDING` o `TRANSCRIBED` → `duplicate`, sin tocar nada.
- No existe → `created`.

El hash se calcula antes de sondear: un duplicado no ejecuta `ffprobe` ni escribe en disco.

### 4. Contrato de la respuesta

Cuerpo plano, igual en los tres casos:

```json
{"id": 42, "status": "PENDING", "source": "app", "duration_seconds": 37, "received_at": "2026-10-09T08:15:00Z", "result": "created"}
```

`201` para `created` y `200` para `duplicate` y `retrying`. Errores con el formato único: `422 validation_failed` si falta `file`, la subida llega incompleta, supera 25 MB o no es un audio admitido. Se descartó `409` para el duplicado: obligaría a la app a tratar como error un reintento legítimo.

### 5. Migración

Una migración sobre `audio_recording`: los dos identificadores de Telegram pasan a `NULL`-ables (los índices únicos se conservan; Postgres admite varios `NULL`), `source VARCHAR NOT NULL DEFAULT 'telegram'` (rellena las filas existentes) y `content_hash VARCHAR(64) NULL` con índice único.

### 6. Fichero de la transcripción y logs

`AudioRecording::getStorageKey()` devuelve `telegram_file_unique_id` o, si no existe, `content_hash`; el handler lo usa para nombrar el `.txt`. Los logs de transcripción añaden `audio_source` y solo incluyen `telegram_file_unique_id` cuando tiene valor.

Log de subida, canal `app`, nivel `info`: `event` = `audio.uploaded`, `audio_recording_id`, `audio_source`, `audio_content_hash`, `audio_upload_result` (`created` / `duplicate` / `retrying`). Campos planos y con prefijo `audio_`, sin reutilizar `result` ni `status`.

### 7. Límites de infraestructura

`docker/nginx/default.conf`: `client_max_body_size 30m`. `docker/php/conf.d/uploads.ini` copiado en la imagen: `upload_max_filesize = 25M`, `post_max_size = 30M`. El margen de 5 MB cubre el envoltorio multipart. `docker/php/Dockerfile` añade el paquete `ffmpeg`.

## Risks / Trade-offs

- [Whisper vía Open WebUI podría no aceptar `.m4a`] → tarea de verificación con un audio real antes de dar el cambio por cerrado; si falla, se decide con el usuario (convertir con `ffmpeg` o limitar formatos).
- [Una petición mayor de 30 MB la corta nginx con un `413` en HTML, fuera del formato de error de la API] → documentado en OpenAPI; la app valida el tamaño antes de subir.
- [Whisper tiene un timeout de 120 s: un audio muy largo dentro de los 25 MB puede acabar en `ERROR`] → mismo comportamiento que hoy con Telegram; queda visible y reintentable.
- [La imagen PHP crece con `ffmpeg`] → aceptado; se instala con `--no-install-recommends`.
- [Dos peticiones simultáneas con el mismo fichero] → el índice único de `content_hash` rechaza la segunda (`500`); con un solo usuario no se trata de forma especial.

## Migration Plan

1. En `diary-prod`, tras actualizar el código: reconstruir la imagen PHP y recrear `diary-php` y `diary-messenger-worker` (`ffmpeg` y límites de PHP).
2. `make composer-install` (`symfony/process`) y `make migrate`.
3. Recrear `diary-nginx` (`client_max_body_size`).

Rollback: la migración `down` elimina `source` y `content_hash` y vuelve a exigir los identificadores de Telegram; solo es posible si no hay audios con origen `app`.

## Open Questions

- Compatibilidad de Whisper con `.m4a` (ver riesgos): se resuelve durante la implementación.
