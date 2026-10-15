## Why

Los días en que no se graba ningún audio el diario se queda vacío y el resumen de las 21:00 no tiene nada que resumir. Un aviso a media tarde por Telegram, solo esos días, anima a grabar algo antes de que acabe el día.

## What Changes

- Comando nuevo `app:notify-idle-day`: si hoy (Europe/Madrid) no se ha recibido ningún audio, de cualquier origen (Telegram o API) y en cualquier estado, envía un mensaje de Telegram al chat autorizado. Si hay al menos uno, no envía nada.
- Symfony Scheduler lanza el comando a las horas en punto configuradas (por defecto 15:00 y 17:00, Europe/Madrid).
- Variables de entorno nuevas, con valor por defecto en `config/services.yaml` para que el despliegue no exija nada:
  - `IDLE_DAY_REMINDER_HOURS`: horas separadas por comas (por defecto `15,17`). Si está vacía, el aviso se desactiva.
  - `IDLE_DAY_REMINDER_MESSAGE`: texto del aviso (por defecto `No has hecho nada hoy? venga no seas así`).
- Logs del comando con `event` `idle_day.*` y campos planos con prefijo `idle_day_*`.

## Capabilities

### New Capabilities
<!-- ninguna -->

### Modified Capabilities
- `reminders`: se añade el requisito «Aviso por Telegram si el día no tiene audios».

## Impact

- Código: `src/Command/NotifyIdleDayCommand.php` (nuevo), `src/Schedule.php`, `config/services.yaml`, `.env.example`, `.env.test`.
- Tests: `tests/Command/NotifyIdleDayCommandTest.php` y un test del horario generado por `Schedule`.
- Despliegue: ninguno obligatorio. El horario del Scheduler se construye al arrancar el worker, así que cambiar las horas exige reiniciar `diary-messenger-worker`.
- Docs: `Especificaciones.md` (flujos programados y variables de entorno).
