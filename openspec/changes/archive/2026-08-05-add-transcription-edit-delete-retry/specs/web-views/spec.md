## MODIFIED Requirements

### Requirement: Vista Historial con calendario
El sistema SHALL mostrar un calendario navegable por mes (mes anterior/siguiente), marcando visualmente los días con `DailySummary` generado frente a los días con audios pero sin resumen, y SHALL mostrar el log de entradas del día seleccionado con las mismas acciones disponibles en el Diario (editar, eliminar, reintentar según el estado de cada entrada).

#### Scenario: Seleccionar un día con entradas
- **WHEN** el usuario selecciona un día del calendario que tiene `AudioRecording`
- **THEN** se muestra debajo el log de esas entradas, con las acciones de editar/eliminar/reintentar disponibles según el estado de cada una, igual que en el Diario
