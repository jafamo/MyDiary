## 1. Configuración

- [ ] 1.1 Añadir `env(IDLE_DAY_REMINDER_HOURS): '15,17'` y `env(IDLE_DAY_REMINDER_MESSAGE)` con el texto por defecto en `config/services.yaml`, y los `bind` `string $idleDayReminderHours` / `string $idleDayReminderMessage`
- [ ] 1.2 Documentar ambas variables en `.env.example` y fijarlas en `.env.test`

## 2. Comando

- [ ] 2.1 Crear `src/Command/NotifyIdleDayCommand.php` (`app:notify-idle-day`): cuenta los audios de hoy con `countReceivedBetween()` y, si no hay ninguno, envía el mensaje configurado al chat autorizado; log `idle_day.notified` / `idle_day.skipped`
- [ ] 2.2 Tests en `tests/Command/NotifyIdleDayCommandTest.php`: envía si no hay audios, no envía si hay alguno, y usa el texto configurado

## 3. Scheduler

- [ ] 3.1 `src/Schedule.php`: parsear `$idleDayReminderHours` (vacía = sin avisos; un valor fuera de 0-23 lanza `\InvalidArgumentException`) y añadir un `RecurringMessage::cron("0 H * * *", ...)` por hora
- [ ] 3.2 Tests del parseo de horas y del horario generado (horas por defecto, vacía y hora inválida)

## 4. Documentación y verificación

- [ ] 4.1 Actualizar `Especificaciones.md` (flujo programado y variables de entorno)
- [ ] 4.2 `make cs-check`, `make phpstan` y `make test` en verde
