## ADDED Requirements

### Requirement: Aviso por Telegram si el día no tiene audios
El sistema SHALL ejecutar, vía `Symfony Scheduler`, el comando `app:notify-idle-day` en cada una de las horas en punto (Europe/Madrid) configuradas en `IDLE_DAY_REMINDER_HOURS` (lista separada por comas, por defecto `15,17`). El comando SHALL enviar al chat autorizado de Telegram el texto de `IDLE_DAY_REMINDER_MESSAGE` (por defecto `No has hecho nada hoy? venga no seas así`) solo si en el día actual (Europe/Madrid) no se ha recibido ningún audio, de cualquier origen (Telegram o API) y en cualquier estado. Si `IDLE_DAY_REMINDER_HOURS` está vacía, no se programa ningún aviso.

#### Scenario: Aviso enviado cuando el día no tiene audios
- **WHEN** `app:notify-idle-day` se ejecuta y no existe ningún audio recibido hoy
- **THEN** se envía un único mensaje de Telegram al chat autorizado con el texto configurado

#### Scenario: Sin aviso cuando ya hay algún audio hoy
- **WHEN** `app:notify-idle-day` se ejecuta y existe al menos un audio recibido hoy, aunque su transcripción esté pendiente o haya fallado
- **THEN** no se envía ningún mensaje de Telegram

#### Scenario: Varios avisos en el mismo día sin audios
- **WHEN** a las 15:00 se envía el aviso y a las 17:00 sigue sin haber ningún audio recibido hoy
- **THEN** a las 17:00 se envía de nuevo el aviso

#### Scenario: Horas por defecto
- **WHEN** `IDLE_DAY_REMINDER_HOURS` no está definida
- **THEN** el Scheduler programa `app:notify-idle-day` a las 15:00 y a las 17:00 (Europe/Madrid)

#### Scenario: Aviso desactivado
- **WHEN** `IDLE_DAY_REMINDER_HOURS` está vacía
- **THEN** el Scheduler no programa `app:notify-idle-day`

#### Scenario: Hora inválida
- **WHEN** `IDLE_DAY_REMINDER_HOURS` contiene un valor que no es un entero entre 0 y 23
- **THEN** la construcción del horario falla con un error que indica el valor inválido
