## 1. Reutilizar la lógica de reintento

- [x] 1.1 Extraer `AudioRecordingService::retryAfterError(AudioRecording $audioRecording): void` con la lógica ya existente (reset a `PENDING`, limpiar `error_code`/`error_message`, `flush`, despachar `TranscribeAudioMessage`).
- [x] 1.2 Actualizar `AudioRecordingService::receive()` para llamar a `retryAfterError()` en la rama `ERROR` del dedupe, sin cambiar su comportamiento observable.
- [x] 1.3 Ejecutar `tests/Service/AudioRecordingServiceTest.php` para confirmar que el flujo de reenvío por Telegram no cambia (test de regresión).

## 2. Reintento manual desde la web

- [x] 2.1 Añadir ruta `POST /audio/{audioRecording}/reintentar` en un nuevo `AudioRecordingController::retry()` que llama a `AudioRecordingService::retryAfterError()` si el estado es `ERROR`, o responde 409 en caso contrario.
- [x] 2.2 Añadir test `tests/Controller/AudioRecordingControllerTest.php` cubriendo: reintento exitoso sobre `ERROR`, y rechazo (409, sin cambios) sobre `PENDING`/`TRANSCRIBED`.

## 3. Edición de transcripciones

- [x] 3.1 Crear `src/Form/TranscriptionEditType.php` con el campo `content` (`TextareaType`).
- [x] 3.2 Añadir ruta `POST /transcripcion/{audioRecording}/editar` en un nuevo `TranscriptionController::edit()`: valida que el `AudioRecording` esté `TRANSCRIBED`, actualiza `content`/`updated_at`/`edited_manually`, y regenera el fichero de export.
- [x] 3.3 Añadir test `tests/Controller/TranscriptionControllerTest.php` cubriendo: edición correcta (contenido, `edited_manually`, fichero regenerado) y rechazo sobre un `AudioRecording` sin transcripción (`PENDING`/`ERROR`).

## 4. Eliminación en cascada

- [x] 4.1 Añadir ruta `POST /transcripcion/{audioRecording}/eliminar` en `TranscriptionController::delete()`: válido para `TRANSCRIBED` o `ERROR`; borra `Transcription` (si existe), `AudioRecording`, y los ficheros de audio/export en filesystem, tolerando ficheros ya ausentes.
- [x] 4.2 Añadir tests cubriendo: borrado de un audio `TRANSCRIBED` (con `Transcription` y ambos ficheros), borrado de un audio `ERROR` (sin `Transcription`), y borrado cuando el fichero de audio ya no existe en disco.

## 5. Plantillas: acciones inline en Diario e Historial

- [x] 5.1 Actualizar `templates/_partials/entry_log.html.twig` para mostrar, por entrada: formulario de edición inline o enlace a edición (si `TRANSCRIBED`), botón "Eliminar" con confirmación y token CSRF (si `TRANSCRIBED` o `ERROR`), y botón "Reintentar" con token CSRF (si `ERROR`).
- [x] 5.2 Quitar el modo "solo lectura" de `templates/historial/index.html.twig` para que reutilice el partial con las mismas acciones que Diario. (Ya reutilizaba el mismo partial sin ninguna bandera de solo-lectura; al añadir las acciones en 5.1, Historial las hereda automáticamente.)
- [x] 5.3 Verificar que las acciones redirigen de vuelta a la página de origen (Diario, o Historial con el día/mes seleccionado) tras completarse. (Los controladores redirigen a `Referer`, que el navegador envía automáticamente en el POST del formulario.)

## 6. Verificación manual y housekeeping

- [x] 6.1 `make test` y `make cs-check` en verde.
- [x] 6.2 Verificación manual en navegador (`make up`): editar una transcripción, eliminar un audio transcrito, eliminar un audio en error, y reintentar un audio en error, en Diario e Historial, en escritorio y móvil.
- [x] 6.3 Actualizar `Especificaciones.md` si algún detalle de 3.4/3.4-bis cambió durante la implementación. (Revisado: la implementación coincide con lo documentado, sin cambios necesarios.)
