## Context

`src/Schedule.php` ya programa con Symfony Scheduler el resumen de las 21:00, el aviso de recordatorios de las 08:00 (`app:notify-reminders`) y los rechequeos del resumen, todos con `RecurringMessage::cron(..., timezone: LocalTimezone::NAME)` y `RunCommandMessage`. `TelegramClient::sendMessage()` y el parámetro ligado `$authorizedChatId` ya existen. `AudioRecordingRepository::countReceivedBetween()` cuenta los audios de cualquier origen y estado en un intervalo.

## Goals / Non-Goals

**Goals:**
- Avisar por Telegram, a una o varias horas, solo si el día va sin audios.
- Que las horas y el texto se cambien por variables de entorno, sin tocar código.

**Non-Goals:**
- Configurarlo desde la web o desde la base de datos.
- Usar minutos que no sean en punto.
- Llevar registro de los avisos enviados: si a las 17:00 sigue sin haber audio, se vuelve a avisar, y eso es lo que se busca.

## Decisions

- **Variables de entorno con valor por defecto en `services.yaml`** (`env(IDLE_DAY_REMINDER_HOURS): '15,17'`, `env(IDLE_DAY_REMINDER_MESSAGE): '...'`), igual que se hizo con `KEENDNS_PROXY_IP`. El usuario eligió esta opción frente a dejarlo en código: permite cambiar horas y texto sin desplegar y no obliga a tocar `.env` en producción. Se ligan con `bind` como `string $idleDayReminderHours` y `string $idleDayReminderMessage`.
- **Un cron por hora en `Schedule`**: `Schedule` recibe `$idleDayReminderHours`, la divide por comas, descarta los vacíos y añade `RecurringMessage::cron("0 H * * *", new RunCommandMessage('app:notify-idle-day'), timezone: LocalTimezone::NAME)` por cada hora. Una hora que no sea un entero entre 0 y 23 lanza `\InvalidArgumentException` al construir el horario, para que una configuración errónea falle al arrancar el worker y no pase desapercibida. El parseo vive en un método estático pequeño de `Schedule`, que se puede probar sin levantar el kernel.
- **Criterio: cualquier audio recibido hoy** (Telegram o API, en cualquier estado, también `ERROR`), con `countReceivedBetween()` sobre el día local de `DateRange`. Elección del usuario: si se mandó algo, ya se «hizo algo».
- **Comando de consola** `app:notify-idle-day`, como `app:notify-reminders`, para que se pueda lanzar a mano y probar con `CommandTester`.
- **Logs**: `info` con `event` `idle_day.notified` o `idle_day.skipped` y `date`, más `idle_day_audio_count` en el caso de que se omita. Campos planos con prefijo, siguiendo la convención de Kibana.

## Risks / Trade-offs

- [Cambiar las horas no surte efecto hasta reiniciar el worker] → Se documenta en `Especificaciones.md` y en el CHANGELOG.
- [Scheduler `stateful` con `processOnlyLastMissedRun`: si el worker estuvo caído a las 15:00 y arranca a las 15:40, se lanza el aviso que se perdió] → Es aceptable, porque el comando vuelve a comprobar que no haya audios en el momento de ejecutarse.
