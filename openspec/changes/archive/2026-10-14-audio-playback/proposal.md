## Why

Los audios se guardan en el servidor pero ninguna ruta los sirve: ni la web ni la app pueden escuchar lo que se grabó, solo leer su transcripción (bloqueo 4 del `ROADMAP.md`). Es la fase 6, la siguiente en el orden recomendado tras subir audio y leer.

## What Changes

- `GET /api/v1/audios/{id}/fichero`: descarga autenticada con token del fichero de un audio, con soporte de `Range` (`206 Partial Content`) para reproducir con salto. `404 not_found` si el audio no existe o su fichero no está en disco.
- `GET /audio/{id}/fichero`: ruta equivalente para la web, con sesión.
- Reproductor `<audio controls preload="none">` en cada entrada del log de Diario e Historial, en cualquier estado (el audio existe aunque la transcripción haya fallado o esté en curso).
- Servicio `AudioFileResponder` compartido por ambas rutas: localiza el fichero, fija el `Content-Type` por extensión (`ogg`, `m4a`, `mp3`, `wav`) y cabeceras de caché privadas.
- Log `audio.file_missing` (warning) cuando un audio existe en BD pero su fichero no está en disco.
- Documentación del endpoint: atributos OpenAPI, `doc/openapi.json`, colección Postman y `doc/api.http`.
- `ROADMAP.md`: se anota como limitación que los audios de Telegram son Ogg/Opus y no se convierten a AAC (iOS < 17.4 y `AVPlayer` no los reproducen).

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `api`: nuevo requisito de descarga del fichero de un audio con soporte de rangos.
- `web-views`: las entradas del log de Diario e Historial incluyen un reproductor del audio servido por una ruta con sesión.

## Impact

- **Código nuevo:** `src/Service/AudioFileResponder.php`, acción `file` en `src/Controller/Api/AudioController.php` y en `src/Controller/AudioRecordingController.php`.
- **Código modificado:** `templates/_partials/entry_log.html.twig` y sus estilos.
- **Documentación:** `doc/openapi.json`, `doc/MyDiary.postman_collection.json`, `doc/api.http`, `Especificaciones.md` (3.5 y 3.7) y `ROADMAP.md`.
- **Sin cambios:** base de datos, dependencias e infraestructura (nginx no cambia: el fichero lo sirve PHP con `BinaryFileResponse`). Sin migraciones ni pasos de despliegue especiales.
