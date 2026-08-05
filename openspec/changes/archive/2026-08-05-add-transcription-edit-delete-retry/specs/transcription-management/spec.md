## ADDED Requirements

### Requirement: Edición manual del texto de una transcripción
El sistema SHALL permitir editar el `content` de una `Transcription` cuyo `AudioRecording` esté en estado `TRANSCRIBED`, marcando `edited_manually = true`, actualizando `updated_at`, y regenerando el fichero de export (`Transcription.file_path`) con el nuevo contenido.

#### Scenario: Edición correcta
- **WHEN** el usuario envía el formulario de edición con un texto distinto para una `Transcription` cuyo `AudioRecording` está `TRANSCRIBED`
- **THEN** el sistema actualiza `content` y `updated_at`, marca `edited_manually = true`, y regenera el fichero de export con el nuevo texto

#### Scenario: Edición sobre un audio no transcrito
- **WHEN** se intenta editar la transcripción de un `AudioRecording` en estado `PENDING` o `ERROR` (no existe `Transcription` todavía)
- **THEN** el sistema responde con un error sin crear ni modificar ningún registro

### Requirement: Eliminación en cascada de un audio y su transcripción
El sistema SHALL permitir eliminar un `AudioRecording` en estado `TRANSCRIBED` o `ERROR`, borrando también su `Transcription` asociada (si existe) y los ficheros en filesystem (audio y export de texto), sin dejar rastro en BD ni en disco. Si un fichero ya no existe en disco, la ausencia SHALL ignorarse y el borrado en BD SHALL continuar; cualquier otro fallo de filesystem SHALL abortar la operación antes de tocar la BD.

#### Scenario: Eliminar un audio transcrito
- **WHEN** el usuario confirma la eliminación de un `AudioRecording` en estado `TRANSCRIBED`
- **THEN** el sistema borra el registro `Transcription`, el registro `AudioRecording`, el fichero de audio y el fichero de export de texto

#### Scenario: Eliminar un audio en error
- **WHEN** el usuario confirma la eliminación de un `AudioRecording` en estado `ERROR` (sin `Transcription` asociada)
- **THEN** el sistema borra el registro `AudioRecording` y el fichero de audio, sin fallar por la ausencia de `Transcription`

#### Scenario: Fichero ya ausente en disco
- **WHEN** se elimina un `AudioRecording` cuyo fichero de audio ya no existe en el filesystem
- **THEN** el sistema completa el borrado en BD igualmente, sin propagar un error por el fichero faltante

### Requirement: Reintento manual desde la web tras fallo técnico
El sistema SHALL ofrecer una acción que, para un `AudioRecording` en estado `ERROR`, lo resetea a `PENDING`, limpia `error_code` y `error_message`, y despacha un nuevo `TranscribeAudioMessage` para ese mismo registro — mismo efecto que el reenvío del audio por Telegram (dedupe por `telegram_file_unique_id`). Si el `AudioRecording` no está en `ERROR`, el sistema SHALL rechazar la acción sin modificar el registro.

#### Scenario: Reintentar un audio en error
- **WHEN** el usuario pulsa "Reintentar" sobre un `AudioRecording` en estado `ERROR`
- **THEN** el sistema resetea el registro a `PENDING`, limpia `error_code` y `error_message`, y despacha un `TranscribeAudioMessage` para ese mismo `AudioRecording`

#### Scenario: Reintentar un audio que no está en error
- **WHEN** se solicita reintentar un `AudioRecording` en estado `PENDING` o `TRANSCRIBED`
- **THEN** el sistema rechaza la acción y no modifica el registro ni despacha ningún mensaje
