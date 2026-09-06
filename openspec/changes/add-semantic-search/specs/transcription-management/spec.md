## ADDED Requirements

### Requirement: Generación del embedding al transcribir
El sistema SHALL generar y guardar el embedding vectorial del `content` de una `Transcription` inmediatamente después de crearla en el flujo de transcripción (3.2). Un fallo al generar el embedding SHALL registrarse en logs estructurados y NO SHALL impedir que la `Transcription` se guarde ni que el `AudioRecording` pase a `TRANSCRIBED`.

#### Scenario: Embedding generado junto con la transcripción
- **WHEN** una transcripción se genera con éxito
- **THEN** el sistema guarda también su embedding vectorial correspondiente al texto transcrito

#### Scenario: Fallo al generar el embedding no bloquea la transcripción
- **WHEN** la transcripción se genera con éxito pero la generación del embedding falla (p. ej. error de red con Ollama)
- **THEN** la `Transcription` y el `AudioRecording` `TRANSCRIBED` quedan guardados igualmente, sin embedding, y el fallo se registra en logs estructurados

### Requirement: Regeneración del embedding al editar manualmente
El sistema SHALL regenerar el embedding de una `Transcription` cada vez que su `content` se edita manualmente (ver "Edición manual del texto de una transcripción"), para que el embedding no quede desincronizado con el texto vigente.

#### Scenario: Embedding actualizado tras editar
- **WHEN** el usuario edita el texto de una `Transcription` y guarda el cambio
- **THEN** el sistema regenera su embedding a partir del nuevo `content`
