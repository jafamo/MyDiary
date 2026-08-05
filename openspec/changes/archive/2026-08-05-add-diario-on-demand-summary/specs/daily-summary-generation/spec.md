## ADDED Requirements

### Requirement: Generación bajo demanda desde la web
El sistema SHALL permitir disparar la generación (o regeneración) del `DailySummary` del día actual desde la vista Diario, sin esperar al disparo programado de las 21:00. Esta vía SHALL reutilizar la misma lógica de generación, reintentos, guardado y notificación de fallo que el disparo programado, y SHALL omitir la espera por `AudioRecording` `PENDING` (a diferencia del disparo programado), generando inmediatamente con las transcripciones `TRANSCRIBED` disponibles en ese momento.

#### Scenario: Generación manual exitosa desde Diario
- **WHEN** el usuario pulsa "Generar resumen" en Diario y hay transcripciones `TRANSCRIBED` para el día actual
- **THEN** el sistema genera (o regenera) el `DailySummary` de hoy de inmediato, sin esperar por audios `PENDING`

#### Scenario: Generación manual sin esperar pendientes
- **WHEN** el usuario pulsa "Generar resumen" y existen `AudioRecording` `PENDING` del día
- **THEN** el sistema genera el resumen igualmente con las transcripciones ya disponibles, sin bloquear la petición esperando a que terminen los pendientes

## MODIFIED Requirements

### Requirement: Espera corta por transcripciones pendientes
El sistema SHALL comprobar si existen `AudioRecording` del día en curso en estado `PENDING`, y SHALL esperar con reintentos periódicos durante una ventana corta antes de continuar, para no excluir transcripciones casi listas — **únicamente cuando la generación se dispara por el Scheduler o por consola**. El disparo manual desde la web SHALL omitir esta espera (ver "Generación bajo demanda desde la web").

#### Scenario: Continúa tras agotar la ventana de espera
- **WHEN** siguen existiendo `AudioRecording` `PENDING` del día tras agotarse la ventana de espera
- **THEN** el comando continúa igualmente, generando el resumen solo con las transcripciones ya disponibles en estado `TRANSCRIBED`
