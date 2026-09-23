## MODIFIED Requirements

### Requirement: Vista Diario con mini-dashboard
El sistema SHALL mostrar, como página principal tras el login, el día actual con: un mini-dashboard (racha de días consecutivos con audio, total de audios de la semana en curso con tendencia respecto a la anterior, tema más mencionado del mes en curso), el log cronológico de `AudioRecording`/`Transcription` del día (con su estado y, si es `ERROR`, el `error_message` descriptivo), y el panel de `DailySummary` cuando existe para ese día. Cada entrada `TRANSCRIBED` del log SHALL mostrar un pie de métricas con el tiempo de proceso, la velocidad (duración del audio dividida entre el tiempo de proceso, con un decimal y sufijo `×`) y el modelo de la transcripción. El panel de `DailySummary` SHALL mostrar un pie de métricas con tokens de entrada, salida y total, el tiempo de generación y el modelo. El log SHALL poder filtrarse por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro); el mini-dashboard no se ve afectado por este filtro.

#### Scenario: Día sin resumen todavía
- **WHEN** se visita el Diario antes de que se haya generado el `DailySummary` del día (antes de las 21:00 o si falló)
- **THEN** se muestra el log de audios del día sin el panel de resumen

#### Scenario: Racha con corte
- **WHEN** el último día con audio registrado es anterior a ayer
- **THEN** la racha actual mostrada es 0

#### Scenario: Filtrar el log por estado
- **WHEN** el usuario selecciona el filtro "Error" en Diario
- **THEN** el log muestra solo los `AudioRecording` del día en estado `ERROR`, y el mini-dashboard sigue mostrando los mismos valores que sin filtro

#### Scenario: Métricas de una transcripción en el log
- **WHEN** el log muestra un audio de 102 segundos cuya transcripción tiene `processing_ms = 14000` y `model = "whisper-1"`
- **THEN** su pie de métricas muestra `Procesado en 14 s`, `7,3× tiempo real` y `whisper-1`

#### Scenario: Entradas sin transcripción no muestran métricas
- **WHEN** el log muestra un audio en estado `PENDING` o `ERROR`
- **THEN** esa entrada no muestra pie de métricas
