## Why

El Diario/Historial ya muestran el estado de cada `AudioRecording` (incluido `ERROR` con su `error_message`), pero son de solo lectura: no hay forma de corregir el texto de una transcripción, de eliminar un audio que no sirve, ni de reintentar uno que falló técnicamente sin tener que reenviarlo por Telegram. Esto era un non-goal explícito del change anterior (`2026-08-05-add-twig-views`) y corresponde a las secciones 3.4 y 3.4-bis de `Especificaciones.md`, que es el siguiente paso pendiente del orden de construcción (sección 9, punto 6).

## What Changes

- Nuevo `TranscriptionController` con acciones para editar el `content` de una `Transcription` existente (regenerando su export a fichero tras guardar) y para eliminar un `AudioRecording` ya transcrito junto con su `Transcription` y los ficheros asociados (audio + export de texto) en cascada.
- Nuevo `TranscriptionEditType` (`FormType`) para el formulario de edición.
- Nuevo `AudioRecordingController` con una acción de reintento inline para `AudioRecording` en estado `ERROR`: resetea el registro a `PENDING`, limpia `error_code`/`error_message`, y despacha un nuevo `TranscribeAudioMessage` para ese mismo registro (mismo efecto que el reenvío por Telegram, pero disparado desde la web).
- Actualización de las plantillas de Diario e Historial (`_partials/entry_log.html.twig`) para mostrar, por cada entrada: acción de editar (si `TRANSCRIBED`), acción de eliminar (si `TRANSCRIBED` o `ERROR`), y botón "Reintentar" (si `ERROR`). Esto convierte la vista de Historial de solo lectura a interactiva, alineándola con Diario.
- Manejo de errores de filesystem al eliminar (fichero ya ausente no debe romper el borrado del registro en BD).

## Capabilities

### New Capabilities
- `transcription-management`: edición del texto de una transcripción, eliminación en cascada de `AudioRecording`+`Transcription`+ficheros, y reintento manual desde la web de un `AudioRecording` en `ERROR`.

### Modified Capabilities
- `web-views`: el requisito "Vista Historial con calendario" cambia — el log de entradas del día seleccionado deja de ser exclusivamente de solo lectura; pasa a ofrecer las mismas acciones (editar/eliminar/reintentar) que el Diario, según el estado de cada entrada.

## Impact

- Código: `src/Controller/TranscriptionController.php`, `src/Controller/AudioRecordingController.php`, `src/Form/TranscriptionEditType.php`, `src/Service/AudioRecordingService.php` (o servicio nuevo si procede), `templates/_partials/entry_log.html.twig`, `templates/historial/index.html.twig`.
- BD: sin cambios de esquema — reutiliza campos existentes (`status`, `error_code`, `error_message`, `content`, `edited_manually`).
- Mensajería: reutiliza `TranscribeAudioMessage`/`TranscribeAudioMessageHandler` ya existentes (mismo mensaje que despacha el flujo 3.2).
- Filesystem: borrado de ficheros de audio y de export de transcripción; regeneración del export tras edición manual.
