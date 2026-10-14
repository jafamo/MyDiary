## 1. Servicio compartido

- [x] 1.1 Crear `AudioFileMissingException` y `AudioFileResponder` en `src/Service/` (tipo por extensión, caché privada, `Content-Disposition` inline sin la clave, `prepare()` para `Range`, warning `audio.file_missing`)
- [x] 1.2 Tests unitarios de `AudioFileResponder`: tipos por extensión, fichero ausente lanza excepción y registra el log

## 2. API

- [x] 2.1 Acción `GET /api/v1/audios/{id}/fichero` (`api_audio_file`) en `Api\AudioController`, `404 not_found` si falta el audio o el fichero, con atributos OpenAPI
- [x] 2.2 Tests funcionales: descarga completa, `Range` → `206`, audio inexistente, fichero ausente y sin token
- [x] 2.3 Regenerar `doc/openapi.json` (`make openapi`) y añadir la petición a `doc/MyDiary.postman_collection.json` y `doc/api.http`

## 3. Web

- [x] 3.1 Acción `GET /audio/{audioRecording}/fichero` (`app_audio_recording_file`) en `AudioRecordingController`
- [x] 3.2 Reproductor `<audio controls preload="none">` en `templates/_partials/entry_log.html.twig` y estilos
- [x] 3.3 Tests funcionales: descarga con sesión, sin sesión redirige a `/login`, fichero ausente `404`, el Diario incluye el reproductor

## 4. Documentación y verificación

- [x] 4.1 Actualizar `Especificaciones.md` (3.5 y 3.7) y `ROADMAP.md` (fase 6 hecha, bloqueo 4 resuelto, limitación Ogg/Opus → AAC)
- [x] 4.2 `make cs-check`, `make phpstan` y `make test` en verde
