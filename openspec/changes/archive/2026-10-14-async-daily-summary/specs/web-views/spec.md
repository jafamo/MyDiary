## MODIFIED Requirements

### Requirement: Botón de generación de resumen bajo demanda en Diario
El sistema SHALL mostrar en la vista Diario un botón "Generar resumen" que pide la generación (o regeneración) del `DailySummary` del día actual sin esperar al disparo programado de las 21:00. La petición SHALL volver de inmediato a Diario. Mientras la generación de hoy esté en `QUEUED` o `RUNNING`, Diario SHALL mostrar el aviso «Generando el resumen…» con la hora de la petición y el botón desactivado, y SHALL consultar el estado en `GET /diario/resumen/estado` cada pocos segundos para recargar la vista al terminar. Si la última generación de hoy falló, SHALL mostrar «No se pudo generar el resumen» con su hora y el botón con el texto «Reintentar». Si hoy no hay transcripciones, SHALL avisar de ello en lugar de no hacer nada.

#### Scenario: Pedir el resumen antes de las 21:00
- **WHEN** el usuario pulsa "Generar resumen" en Diario y hay transcripciones de hoy
- **THEN** la respuesta redirige a Diario sin esperar a la generación, y Diario muestra «Generando el resumen…» con el botón desactivado

#### Scenario: El resumen termina mientras la página está abierta
- **WHEN** Diario muestra una generación en curso y esta pasa a `DONE`
- **THEN** la página se recarga sola y muestra el panel del resumen del día

#### Scenario: La generación falla
- **WHEN** la última generación de hoy está en `FAILED`
- **THEN** Diario muestra «No se pudo generar el resumen» con la hora y el botón «Reintentar»

#### Scenario: Sin transcripciones hoy
- **WHEN** el usuario pulsa "Generar resumen" y hoy no hay transcripciones
- **THEN** Diario muestra un aviso indicando que no hay transcripciones de hoy y no se encola nada

#### Scenario: Estado sin sesión
- **WHEN** un visitante sin sesión pide `GET /diario/resumen/estado`
- **THEN** el sistema le redirige a `/login`
