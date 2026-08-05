## ADDED Requirements

### Requirement: Botón de generación de resumen bajo demanda en Diario
El sistema SHALL mostrar en la vista Diario un botón "Generar resumen" que dispara la generación (o regeneración) del `DailySummary` del día actual sin esperar al disparo programado de las 21:00, y tras completarse SHALL volver a mostrar Diario con el resultado actualizado.

#### Scenario: Generar el resumen antes de las 21:00
- **WHEN** el usuario pulsa "Generar resumen" en Diario antes de que exista un `DailySummary` para hoy
- **THEN** el sistema genera el resumen y, al recargar la vista, se muestra el panel de resumen del día

#### Scenario: Regenerar un resumen existente
- **WHEN** el usuario pulsa "Generar resumen" en Diario y ya existe un `DailySummary` para hoy
- **THEN** el sistema regenera el resumen con las transcripciones actuales, reemplazando el contenido anterior
