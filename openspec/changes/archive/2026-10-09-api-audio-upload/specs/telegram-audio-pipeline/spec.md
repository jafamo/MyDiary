## MODIFIED Requirements

### Requirement: Transcripción asíncrona
El sistema SHALL, al procesar `TranscribeAudioMessage`, llamar al servicio de transcripción configurado (`TranscriberInterface`), guardar el resultado en `Transcription` (contenido en BD y export a fichero, junto con sus métricas de consumo), marcar el `AudioRecording` como `TRANSCRIBED`, y notificar al usuario con un resumen corto por Telegram. El mensaje de Telegram SHALL terminar, tras una línea en blanco, con una línea de métricas con el formato `🎙️ <m:ss> de audio · ⏱️ transcrito en <tiempo>`, donde `<tiempo>` se expresa en segundos (`14 s`) si es menor de un minuto y en minutos y segundos (`1 min 05 s`) en caso contrario. El comportamiento SHALL ser el mismo sea cual sea el `source` del audio (`telegram` o `app`). El fichero exportado SHALL nombrarse con `telegram_file_unique_id` y, si el audio no lo tiene, con su `content_hash`.

#### Scenario: Transcripción exitosa
- **WHEN** el handler procesa un `TranscribeAudioMessage` y la llamada al transcriptor tiene éxito
- **THEN** se crea una `Transcription` asociada al `AudioRecording`, el `AudioRecording` pasa a `TRANSCRIBED`, y se envía un mensaje de Telegram con el resultado

#### Scenario: Mensaje con métricas de la transcripción
- **WHEN** se transcribe con éxito un audio de 102 segundos y la llamada al transcriptor tarda 14.200 ms
- **THEN** el mensaje de Telegram termina con la línea `🎙️ 1:42 de audio · ⏱️ transcrito en 14 s`

#### Scenario: Audio subido desde la app
- **WHEN** el handler transcribe con éxito un `AudioRecording` con `source` `app` y sin identificadores de Telegram
- **THEN** la `Transcription` se exporta a `<content_hash>.txt`, el audio pasa a `TRANSCRIBED` y se envía el mismo mensaje de Telegram que para un audio de origen `telegram`
