## 1. Infraestructura y dependencias

- [ ] 1.1 `docker/php/Dockerfile`: añadir `ffmpeg` y copiar `docker/php/conf.d/uploads.ini` (`upload_max_filesize = 25M`, `post_max_size = 30M`)
- [ ] 1.2 `docker/nginx/default.conf`: `client_max_body_size 30m`
- [ ] 1.3 Reconstruir la imagen local y recrear `diary-php`, `diary-messenger-worker` y `diary-nginx`; comprobar `ffprobe -version` y los valores de PHP
- [ ] 1.4 `composer require symfony/process`

## 2. Modelo de datos

- [ ] 2.1 Enum `App\Entity\AudioSource` (`telegram` / `app`)
- [ ] 2.2 `AudioRecording`: identificadores de Telegram opcionales, `source`, `contentHash` y `getStorageKey()`
- [ ] 2.3 `AudioRecordingRepository::findOneByContentHash()`
- [ ] 2.4 Migración de `audio_recording` (columnas nullable, `source` con `DEFAULT 'telegram'`, `content_hash` único), aplicada en las BD local y de test
- [ ] 2.5 Tests de la entidad y del repositorio (varios audios sin identificadores de Telegram, unicidad de `content_hash`)

## 3. Sondeo del audio

- [ ] 3.1 `App\Contract\AudioProbeInterface`, `AudioProbeResult` y `AudioProbeException`
- [ ] 3.2 `App\Service\Audio\FfprobeAudioProbe` con `symfony/process`: formato (`m4a`, `mp3`, `ogg`, `wav`) y duración; rechaza lo que no sea un audio admitido
- [ ] 3.3 Doble de `AudioProbeInterface` para el entorno `test`, registrado como el del transcriptor
- [ ] 3.4 Ficheros de audio mínimos en `tests/fixtures/audio/` y test de `FfprobeAudioProbe` que se salta si no hay `ffprobe`

## 4. Servicios

- [ ] 4.1 `AudioRecordingService::receiveUpload()` (creado / duplicado / reintento tras `ERROR`) y `source = telegram` en `receive()`
- [ ] 4.2 `AudioUploadService`: tamaño máximo (`MAX_BYTES`), SHA-256, sondeo y guardado en `var/audio/<content_hash>.<formato>`; `InvalidAudioUploadException`
- [ ] 4.3 Tests de ambos servicios: audio nuevo, duplicado, reintento, fichero demasiado grande, fichero no admitido (sin registro ni fichero guardado), y que `receive()` no cambia de comportamiento

## 5. Pipeline de transcripción

- [ ] 5.1 `TranscribeAudioMessageHandler`: nombre del `.txt` con `getStorageKey()`; `audio_source` en los logs y `telegram_file_unique_id` solo si existe
- [ ] 5.2 `TranscriptionFailureListener`: mismos campos de log
- [ ] 5.3 Tests: audio de origen `app` transcrito (fichero `<content_hash>.txt`, mismo aviso por Telegram) y logs de fallo con y sin identificador de Telegram

## 6. Endpoint

- [ ] 6.1 `Api\AudioController::upload` (`POST /api/v1/audios`): lectura de `file`, errores `422`, respuesta `201` / `200` con `result` y log `audio.uploaded`
- [ ] 6.2 Atributos OpenAPI: cuerpo multipart, respuestas `201` / `200`, errores `401` / `422` y nota del `413` de nginx
- [ ] 6.3 Tests funcionales: sin token, sin fichero, fichero no admitido, demasiado grande, audio nuevo (mensaje despachado), duplicado, reintento tras `ERROR`, formato `ogg` y log `audio.uploaded`

## 7. Documentación de la API

- [ ] 7.1 `make openapi` y `doc/openapi.json` actualizado
- [ ] 7.2 Petición de subida en `doc/MyDiary.postman_collection.json` y en `doc/api.http`
- [ ] 7.3 `tests/Doc/ApiDocumentationTest.php` en verde

## 8. Verificación con Whisper

- [ ] 8.1 Comprobar con un `.m4a` real que Whisper vía Open WebUI lo transcribe; si no, parar y decidirlo con el usuario

## 9. Documentación del proyecto y cierre

- [ ] 9.1 `Especificaciones.md`: 3.1 (captura desde la app), 3.2 (origen y nombre del fichero), 3.7 (endpoint), 4 (puerto `AudioProbeInterface`) y 6 (`audio_recording`)
- [ ] 9.2 `AGENTS.md`: `AudioProbeInterface` en la lista de puertos
- [ ] 9.3 `ROADMAP.md`: fase 5 hecha con el nombre del change, D3 decidida (opción B) y bloqueo 3 resuelto
- [ ] 9.4 `CHANGELOG.md` bajo `## [Sin publicar]`: Añadido, Cambiado, Migraciones y Despliegue (reconstruir imagen, recrear contenedores, `make composer-install`, `make migrate`)
- [ ] 9.5 `make cs-check`, `make phpstan` y `make test` en verde
