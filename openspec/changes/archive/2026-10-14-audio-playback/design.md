## Context

`AudioRecording::filePath` guarda la ruta absoluta del fichero dentro del contenedor PHP (`var/audio/<clave>.<ext>`, montado desde `${DATA_PATH}/audio`). Los de Telegram son `.ogg` (Opus); los subidos por la app, `m4a`, `mp3`, `ogg` o `wav`. Ninguna ruta los sirve hoy. nginx no tiene montado el directorio de audio.

## Goals / Non-Goals

**Goals:**
- Escuchar un audio desde la app (API con token) y desde la web (sesión), con salto (`Range`).
- Una sola regla para localizar el fichero, su tipo y sus cabeceras, compartida por las dos rutas.

**Non-Goals:**
- Convertir Ogg/Opus a AAC para iOS antiguos o `AVPlayer` (anotado como limitación en `ROADMAP.md`).
- Servir el fichero desde nginx (`X-Accel-Redirect`).
- Exponer la URL del fichero en las respuestas JSON de audio: la app la construye a partir del `id`.

## Decisions

- **`BinaryFileResponse` de Symfony** en lugar de `X-Accel-Redirect`: ya resuelve `Range`/`206`, `Accept-Ranges` y Last-Modified (sin ETag automático, que obligaría a hashear el fichero en cada petición), se prueba con tests funcionales y no exige montar el audio en `diary-nginx` ni recrearlo. Con un usuario y ficheros de ≤25 MB, ocupar un worker PHP-FPM durante la descarga es irrelevante.
- **Servicio `App\Service\AudioFileResponder`** con un método `respond(AudioRecording): BinaryFileResponse` que lanza `AudioFileMissingException` si el fichero no existe (y registra `audio.file_missing`). Fija `Content-Type` por extensión con un mapa propio (`ogg` → `audio/ogg`, `m4a` → `audio/mp4`, `mp3` → `audio/mpeg`, `wav` → `audio/wav`, otro → `application/octet-stream`), `Cache-Control: private, max-age=…` y `Content-Disposition: inline` con nombre `audio-<id>.<ext>` (sin la clave de almacenamiento). `Range` lo resuelve `BinaryFileResponse::prepare()`, que Symfony llama al enviar la respuesta. Cada controlador traduce la excepción: la API a `ApiException::notFound()`, la web a `NotFoundHttpException`. Alternativa descartada: repetir la lógica en ambos controladores.
- **Rutas:** `api_audio_file` (`GET /api/v1/audios/{id}/fichero`, en `Api\AudioController`) y `app_audio_recording_file` (`GET /audio/{audioRecording}/fichero`, en `AudioRecordingController`, junto a `reintentar`). La de la web queda protegida por el `access_control` existente (`^/` → `ROLE_USER`).
- **Logs:** solo el warning `audio.file_missing` con `audio_recording_id` y `audio_source` (planos y con prefijo). Las reproducciones ya aparecen en `http.request`; un log por petición `Range` solo añadiría ruido.
- **Reproductor:** `<audio controls preload="none" src="…">` en `_partials/entry_log.html.twig`, bajo la línea de meta y en cualquier estado. `preload="none"` evita descargar todos los audios del día al abrir la página.

## Risks / Trade-offs

- [Safari < 17.4 y `AVPlayer` no reproducen Ogg/Opus] → fuera de alcance; anotado en `ROADMAP.md`.
- [Ruta absoluta guardada en BD distinta si cambia el montaje] → el fichero ausente da `404` con log `audio.file_missing`, visible en Kibana.
- [Respuestas binarias en `HttpRequestLogListener`] → solo registra ruta y estado; sin cambio.

## Migration Plan

Sin migraciones ni cambios de infraestructura. Despliegue normal; rollback revirtiendo el merge.
