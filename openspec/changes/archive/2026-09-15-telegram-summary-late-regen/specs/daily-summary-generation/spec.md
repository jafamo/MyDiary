## ADDED Requirements

### Requirement: Recheck tardío entre las 21:00 y las 00:30 Europe/Madrid
El sistema SHALL ejecutar automáticamente `app:recheck-daily-summary` cada 15 minutos entre las 21:00 y las 00:30 en la zona horaria `Europe/Madrid`, vía Symfony Scheduler. Para cada ejecución, el comando SHALL determinar si existen `AudioRecording` en estado `TRANSCRIBED` recibidas ese día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente de ese día (o, si no existe `DailySummary` para ese día, si existe cualquier `AudioRecording` `TRANSCRIBED` de ese día). Cuando existan tales transcripciones nuevas, el sistema SHALL regenerar el `DailySummary` del día reutilizando `DailySummaryService::generateForDate` sin esperar por `AudioRecording` `PENDING` (igual que la generación bajo demanda desde la web), y SHALL reenviar la notificación por Telegram con el resumen actualizado. Cuando no existan transcripciones nuevas, el sistema SHALL no regenerar ni reenviar nada.

#### Scenario: Audio nuevo tras el resumen de las 21:00 dispara regeneración
- **WHEN** el recheck se ejecuta y existe al menos una `AudioRecording` `TRANSCRIBED` del día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente
- **THEN** el sistema regenera el `DailySummary` del día con todas las transcripciones disponibles y reenvía por Telegram la cabecera con la fecha seguida del texto del resumen actualizado

#### Scenario: Sin audios nuevos, no se reenvía nada
- **WHEN** el recheck se ejecuta y no existe ninguna `AudioRecording` `TRANSCRIBED` del día con `receivedAt` posterior al `generatedAt` del `DailySummary` vigente
- **THEN** el sistema no regenera el `DailySummary` ni envía ningún mensaje por Telegram

#### Scenario: El resumen de las 21:00 falló y llegan audios después
- **WHEN** el recheck se ejecuta, no existe `DailySummary` para el día en curso, y existe al menos una `AudioRecording` `TRANSCRIBED` de ese día
- **THEN** el sistema genera el `DailySummary` del día (como si fuera la primera generación) y lo envía por Telegram

#### Scenario: Ejecuciones repetidas sin novedades no duplican envíos
- **WHEN** el recheck se ejecuta varias veces seguidas dentro de la ventana 21:00–00:30 sin que lleguen audios nuevos entre ejecuciones
- **THEN** solo la primera ejecución que detectó novedades (si la hubo) regenera y reenvía; las siguientes no vuelven a enviar el mismo resumen

### Requirement: Comando `app:recheck-daily-summary` ejecutable manualmente
El sistema SHALL permitir ejecutar `app:recheck-daily-summary` manualmente (no solo por el Scheduler), aplicando la misma lógica de detección de novedades y regeneración condicional para el día actual.

#### Scenario: Ejecución manual detecta y regenera
- **WHEN** se ejecuta `bin/console app:recheck-daily-summary` y hay transcripciones nuevas desde el último `DailySummary` de hoy
- **THEN** el comando regenera el `DailySummary` de hoy y lo reenvía por Telegram, igual que si lo hubiera disparado el Scheduler
