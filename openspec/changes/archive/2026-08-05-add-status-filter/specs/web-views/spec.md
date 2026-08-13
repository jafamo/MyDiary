## MODIFIED Requirements

### Requirement: Vista Diario con mini-dashboard
El sistema SHALL mostrar, como página principal tras el login, el día actual con: un mini-dashboard (racha de días consecutivos con audio, total de audios de la semana en curso con tendencia respecto a la anterior, tema más mencionado del mes en curso), el log cronológico de `AudioRecording`/`Transcription` del día (con su estado y, si es `ERROR`, el `error_message` descriptivo), y el panel de `DailySummary` cuando existe para ese día. El log SHALL poder filtrarse por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro); el mini-dashboard no se ve afectado por este filtro.

#### Scenario: Día sin resumen todavía
- **WHEN** se visita el Diario antes de que se haya generado el `DailySummary` del día (antes de las 21:00 o si falló)
- **THEN** se muestra el log de audios del día sin el panel de resumen

#### Scenario: Racha con corte
- **WHEN** el último día con audio registrado es anterior a ayer
- **THEN** la racha actual mostrada es 0

#### Scenario: Filtrar el log por estado
- **WHEN** el usuario selecciona el filtro "Error" en Diario
- **THEN** el log muestra solo los `AudioRecording` del día en estado `ERROR`, y el mini-dashboard sigue mostrando los mismos valores que sin filtro

### Requirement: Vista Historial con calendario
El sistema SHALL mostrar un calendario navegable por mes (mes anterior/siguiente), marcando visualmente los días con `DailySummary` generado frente a los días con audios pero sin resumen, y SHALL mostrar el log de entradas del día seleccionado con las mismas acciones disponibles en el Diario (editar, eliminar, reintentar según el estado de cada entrada). El log del día seleccionado SHALL poder filtrarse por estado igual que en Diario; el calendario en sí (marcado de días) no se ve afectado por este filtro.

#### Scenario: Seleccionar un día con entradas
- **WHEN** el usuario selecciona un día del calendario que tiene `AudioRecording`
- **THEN** se muestra debajo el log de esas entradas, con las acciones de editar/eliminar/reintentar disponibles según el estado de cada una, igual que en el Diario

#### Scenario: Filtrar el log del día seleccionado por estado
- **WHEN** el usuario selecciona el filtro "Pendiente" tras elegir un día del calendario
- **THEN** el log de ese día muestra solo los `AudioRecording` en estado `PENDING`, conservando el día/mes seleccionado

### Requirement: Vista Estadísticas con filtro de rango y gráfico
El sistema SHALL permitir filtrar las estadísticas por un rango de fechas (15 días, 1 mes, 3 meses, 1 año, o un rango personalizado) y, combinable con lo anterior, por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro). Al aplicar un filtro de estado, el sistema SHALL recalcular sobre ese estado: la serie de audios por día, la media de audios/día, y la duración media. El desglose de estados de transcripción y el ranking de temas más frecuentes SHALL seguir mostrando siempre todos los estados, sin verse afectados por el filtro de estado.

#### Scenario: Cambiar el rango de fechas
- **WHEN** el usuario selecciona el filtro "3 meses"
- **THEN** el gráfico, los tiles y el desglose de estados se recalculan sobre los últimos 3 meses, y la URL refleja el rango seleccionado

#### Scenario: Filtro funciona sin JavaScript
- **WHEN** JavaScript está deshabilitado en el navegador
- **THEN** seleccionar un filtro de rango sigue funcionando como navegación normal (recarga de página con el rango aplicado)

#### Scenario: Ver los datos del gráfico como tabla
- **WHEN** el usuario activa "Ver como tabla" en el gráfico de audios por día
- **THEN** se muestra una tabla accesible con fecha y valor de cada punto, con los mismos datos que el gráfico

#### Scenario: Filtrar por estado combinado con el rango
- **WHEN** el usuario tiene seleccionado el rango "3 meses" y aplica el filtro de estado "Error"
- **THEN** el gráfico de audios por día, la media de audios/día y la duración media se recalculan usando solo audios `ERROR` de esos 3 meses, mientras que el desglose de estados y el ranking de temas siguen mostrando todos los estados del mismo rango

#### Scenario: Filtro de estado inválido recae en "sin filtro"
- **WHEN** la URL contiene un valor de `status` que no corresponde a ningún estado válido
- **THEN** el sistema ignora el filtro y muestra las estadísticas sin filtrar por estado, sin error
