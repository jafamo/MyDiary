## Purpose

Generación programada (y bajo demanda) del resumen diario y sus temas a partir de las transcripciones del día, vía Ollama.

## Requirements

### Requirement: Disparo diario a las 21:00 Europe/Madrid
El sistema SHALL ejecutar automáticamente `app:generate-daily-summary` cada día a las 21:00 en la zona horaria `Europe/Madrid`, vía Symfony Scheduler.

#### Scenario: Disparo automático
- **WHEN** el reloj del sistema alcanza las 21:00 `Europe/Madrid`
- **THEN** el Scheduler dispara la ejecución de `app:generate-daily-summary` sin intervención manual

### Requirement: Espera corta por transcripciones pendientes
El sistema SHALL comprobar si existen `AudioRecording` del día en curso en estado `PENDING`, y SHALL esperar con reintentos periódicos durante una ventana corta antes de continuar, para no excluir transcripciones casi listas — **únicamente cuando la generación se dispara por el Scheduler o por consola**. El disparo manual desde la web SHALL omitir esta espera (ver "Generación bajo demanda desde la web").

#### Scenario: Continúa tras agotar la ventana de espera
- **WHEN** siguen existiendo `AudioRecording` `PENDING` del día tras agotarse la ventana de espera
- **THEN** el comando continúa igualmente, generando el resumen solo con las transcripciones ya disponibles en estado `TRANSCRIBED`

### Requirement: Generación bajo demanda desde la web
El sistema SHALL permitir disparar la generación (o regeneración) del `DailySummary` del día actual desde la vista Diario, sin esperar al disparo programado de las 21:00. Esta vía SHALL reutilizar la misma lógica de generación, reintentos, guardado y notificación de fallo que el disparo programado, y SHALL omitir la espera por `AudioRecording` `PENDING` (a diferencia del disparo programado), generando inmediatamente con las transcripciones `TRANSCRIBED` disponibles en ese momento.

#### Scenario: Generación manual exitosa desde Diario
- **WHEN** el usuario pulsa "Generar resumen" en Diario y hay transcripciones `TRANSCRIBED` para el día actual
- **THEN** el sistema genera (o regenera) el `DailySummary` de hoy de inmediato, sin esperar por audios `PENDING`

#### Scenario: Generación manual sin esperar pendientes
- **WHEN** el usuario pulsa "Generar resumen" y existen `AudioRecording` `PENDING` del día
- **THEN** el sistema genera el resumen igualmente con las transcripciones ya disponibles, sin bloquear la petición esperando a que terminen los pendientes

### Requirement: Generación de resumen y temas vía Ollama
El sistema SHALL recoger todas las transcripciones en estado `TRANSCRIBED` del día en curso, SHALL llamar al servicio de generación configurado (`SummaryGeneratorInterface`) para obtener un resumen y una lista de temas, y SHALL guardar o actualizar el `DailySummary` de esa fecha con sus `Topic` asociados.

#### Scenario: Generación exitosa, primera vez del día
- **WHEN** el comando se ejecuta para una fecha sin `DailySummary` previo y hay transcripciones `TRANSCRIBED` ese día
- **THEN** se crea un `DailySummary` con `summary_text`, `generated_at`, y sus `Topic` asociados (creando los que no existan ya por `name`)

#### Scenario: Re-ejecución actualiza en vez de duplicar
- **WHEN** el comando se ejecuta para una fecha que ya tiene `DailySummary`
- **THEN** el `DailySummary` existente se actualiza (no se crea un segundo registro para la misma fecha) y sus asociaciones de `Topic` se reemplazan por las del nuevo resultado

### Requirement: Manejo de errores en la generación
El sistema SHALL reintentar la llamada al generador de resumen un número corto de veces con espera breve si falla. Si todos los intentos fallan, SHALL registrar el error en logs estructurados, SHALL no crear ni modificar el `DailySummary` de esa fecha, y SHALL notificar al usuario *"No se pudo generar el resumen de hoy ⚠️"* por Telegram, sin reintento automático al día siguiente.

#### Scenario: Fallo tras agotar los reintentos
- **WHEN** todas las tentativas de generación fallan para una fecha dada
- **THEN** no se persiste ningún `DailySummary` para esa fecha, se registra un log de error con contexto estructurado, y se envía la notificación de fallo por Telegram

### Requirement: Comando ejecutable manualmente
El sistema SHALL permitir ejecutar `app:generate-daily-summary` manualmente (no solo por el Scheduler), opcionalmente para una fecha distinta a hoy.

#### Scenario: Ejecución manual para una fecha concreta
- **WHEN** se ejecuta `bin/console app:generate-daily-summary --date=2026-08-01`
- **THEN** el comando genera (o regenera) el `DailySummary` correspondiente al 1 de agosto de 2026, no al día actual
