## ADDED Requirements

### Requirement: Generación del embedding al generar el resumen diario
El sistema SHALL generar y guardar el embedding vectorial del `summaryText` de un `DailySummary` inmediatamente después de guardarlo (flujo 3.5, `DailySummaryService::saveDailySummary`). Un fallo al generar el embedding SHALL registrarse en logs estructurados y NO SHALL impedir que el `DailySummary` se guarde ni que se notifique por Telegram.

#### Scenario: Embedding generado junto con el resumen diario
- **WHEN** un resumen diario se genera con éxito
- **THEN** el sistema guarda también su embedding vectorial correspondiente al `summaryText`

#### Scenario: Fallo al generar el embedding no bloquea el guardado del resumen
- **WHEN** el resumen diario se genera con éxito pero la generación del embedding falla (p. ej. error de red con Ollama)
- **THEN** el `DailySummary` queda guardado igualmente, sin embedding, y el fallo se registra en logs estructurados

### Requirement: Regeneración del embedding al regenerar el resumen diario
El sistema SHALL regenerar el embedding de un `DailySummary` cada vez que su `summaryText` se sobrescribe (p. ej. al volver a generar el resumen del mismo día), para que el embedding no quede desincronizado con el texto vigente.

#### Scenario: Embedding actualizado tras regenerar el resumen
- **WHEN** el resumen diario de una fecha ya existente se vuelve a generar con un `summaryText` distinto
- **THEN** el sistema regenera su embedding a partir del nuevo `summaryText`
