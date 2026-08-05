## Context

`Transcription.content` es la fuente de verdad y `Transcription.filePath` un export de backup (sección 6 de `Especificaciones.md`); `AudioRecording` ya tiene `status`/`error_code`/`error_message` y una relación 1:1 `onDelete: CASCADE` hacia `Transcription`. La lógica de "resetear a `PENDING` y redespachar `TranscribeAudioMessage`" ya existe, pero solo inline dentro de `AudioRecordingService::receive()` (rama `ERROR` del dedupe por `telegram_file_unique_id`). Los controladores web actuales (`DiarioController`, `HistorialController`) son de solo lectura; no hay `FormType` de edición ni rutas de escritura fuera del login.

## Goals / Non-Goals

**Goals:**
- Editar el `content` de una `Transcription` vía formulario, marcando `editedManually = true` y regenerando el fichero de export.
- Eliminar un `AudioRecording` (`TRANSCRIBED` o `ERROR`) junto con su `Transcription` (si existe) y los ficheros en filesystem (audio + export), sin dejar rastro.
- Reintentar manualmente un `AudioRecording` en `ERROR` desde la web, reutilizando la misma lógica que ya dispara el reenvío por Telegram (no duplicarla).
- Estas tres acciones disponibles tanto en Diario como en Historial (mismo partial `entry_log.html.twig`).

**Non-Goals:**
- No hay "regenerar transcripción desde el audio original" para audios ya transcritos con mala calidad (ver 3.4: se resuelve reenviando un audio nuevo).
- No se pagina ni se versiona el histórico de ediciones (`edited_manually` es un booleano simple, no un log de cambios).
- No se añade confirmación vía modal JS obligatoria — el borrado usa un POST con confirmación nativa (`onsubmit="return confirm(...)"` o `<button formnovalidate>` + página de confirmación simple), coherente con "funciona sin JS" ya aplicado en Estadísticas.

## Decisions

### 1. Extraer `AudioRecordingService::retryAfterError(AudioRecording $audioRecording): void`
Se extrae a un método público la lógica ya existente en la rama `ERROR` de `receive()` (reset a `PENDING`, limpiar `error_code`/`error_message`, `flush`, despachar `TranscribeAudioMessage`), y `receive()` pasa a llamarlo. Así el nuevo `AudioRecordingController::retry()` reutiliza exactamente el mismo comportamiento que el reenvío por Telegram, sin dos implementaciones del mismo reset.

### 2. `TranscriptionController` con rutas por `AudioRecording`, no por `Transcription`
Rutas: `POST /transcripcion/{audioRecording}/editar` (`TranscriptionController::edit`, requiere `TRANSCRIBED`) y `POST /transcripcion/{audioRecording}/eliminar` (`TranscriptionController::delete`, requiere `TRANSCRIBED` o `ERROR`). Se usa el id de `AudioRecording` como identificador de la URL (no el de `Transcription`) porque es la entidad "raíz" visible en las plantillas y porque el borrado actúa sobre ambas entidades a la vez — evita tener que resolver primero la `Transcription` en la plantilla solo para construir la URL.

### 3. `AudioRecordingController::retry()` solo para estado `ERROR`
`POST /audio/{audioRecording}/reintentar`. Si el `AudioRecording` no está en `ERROR`, responde 409 (no hace nada) — evita reintentos accidentales sobre registros ya `TRANSCRIBED`.

### 4. Formularios reales con CSRF, sin fetch/JS obligatorio
Editar usa `TranscriptionEditType` (`FormType`, campo `content` como `TextareaType`) renderizado inline en `entry_log.html.twig` (o en una vista de edición dedicada — a decidir en tasks según el boceto existente); eliminar y reintentar son formularios `POST` de un solo botón con token CSRF (`csrf_token()`), redirigiendo de vuelta a la página de origen (Diario o Historial con el día seleccionado) tras la acción. Consistente con el patrón "funciona sin JS" ya usado en Estadísticas.

### 5. Borrado de ficheros: tolerante a ausencia, no a otros errores
Antes de borrar los registros de BD, se intenta `unlink()` sobre `AudioRecording.filePath` y `Transcription.filePath`; si el fichero no existe (`file_exists()` ya en `false`), se continúa sin error (no debe bloquear el borrado en BD por un fichero ya perdido). Cualquier otro fallo de filesystem (permisos, disco) sí se propaga y aborta la operación antes de tocar la BD.

### 6. Historial dejará de ser "solo lectura"
El requisito de `web-views` para Historial se actualiza: el log de entradas del día seleccionado ofrece las mismas acciones que Diario. Se reutiliza el mismo partial, así que no hay lógica nueva de plantilla — solo el requisito formal cambia.

## Risks / Trade-offs

- **[Riesgo] Reintentar sin bloqueo de concurrencia**: si el usuario pulsa "Reintentar" justo cuando el reenvío por Telegram ya lo hizo, podría despacharse `TranscribeAudioMessage` dos veces para el mismo `AudioRecording`. Aceptable: el handler es idempotente en la práctica (transcribe y marca `TRANSCRIBED`; una segunda ejecución simplemente sobrescribe con el mismo resultado) y el volumen de un solo usuario hace la ventana de carrera improbable — no se añade locking.
- **[Trade-off] Sin historial de ediciones**: si se necesita auditar cambios de texto en el futuro, habría que añadir una tabla de versiones; no se anticipa ahora (regla general del proyecto).

## Migration Plan

No aplica (funcionalidad nueva, sin cambios de esquema). Pasos:
1. Extraer `AudioRecordingService::retryAfterError()` y actualizar `receive()` para usarlo (con test de regresión sobre el flujo de Telegram).
2. `TranscriptionEditType` + `TranscriptionController` (editar/eliminar) con tests.
3. `AudioRecordingController::retry()` con tests.
4. Actualizar `entry_log.html.twig` con las acciones y quitar el modo solo-lectura de Historial.
5. Verificación manual en navegador: editar, eliminar y reintentar desde Diario e Historial.

## Open Questions

Ninguna bloqueante.
