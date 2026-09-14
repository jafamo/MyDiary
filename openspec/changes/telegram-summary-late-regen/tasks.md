## 1. Repositorio

- [ ] 1.1 Añadir `AudioRecordingRepository::existsTranscribedReceivedAfter(\DateTimeImmutable $date, \DateTimeImmutable $after): bool` (usa `DateRange::dayBoundaries` para los límites del día, filtra `status = TRANSCRIBED` y `receivedAt > :after`)
- [ ] 1.2 Añadir `AudioRecordingRepository::existsTranscribedReceivedOn(\DateTimeImmutable $date): bool` (mismo filtro que `findTranscribedReceivedOn` pero como `EXISTS`, para el caso sin `DailySummary` previo) — o reutilizar `findTranscribedReceivedOn` si el coste de cargar entidades es aceptable

## 2. Servicio

- [ ] 2.1 Añadir `DailySummaryService::hasNewTranscriptionsSince(\DateTimeImmutable $date): bool`: si existe `DailySummary` para `$date`, delega en `existsTranscribedReceivedAfter($date, $dailySummary->getGeneratedAt())`; si no existe, delega en `existsTranscribedReceivedOn($date)`
- [ ] 2.2 Test unitario de `hasNewTranscriptionsSince` cubriendo: hay `DailySummary` y audio posterior (true), hay `DailySummary` y audio solo anterior (false), no hay `DailySummary` y hay audio transcrito (true), no hay `DailySummary` ni audio (false)

## 3. Comando

- [ ] 3.1 Crear `src/Command/RecheckDailySummaryCommand.php` (`app:recheck-daily-summary`): para la fecha de hoy (`Europe/Madrid`, vía `DateRange::nowInMadrid()`), si `hasNewTranscriptionsSince` es true, llama a `DailySummaryService::generateForDate($date, waitForPending: false)`; si es false, no hace nada (loggear a nivel debug/info que no había novedades)
- [ ] 3.2 Test de comando `tests/Command/RecheckDailySummaryCommandTest.php`: caso con novedades (se invoca generación y se envía Telegram), caso sin novedades (no se invoca generación ni se envía nada)

## 4. Scheduler

- [ ] 4.1 Añadir a `src/Schedule.php` las dos entradas `RecurringMessage::cron` para `app:recheck-daily-summary`: `'*/15 21,22,23 * * *'` y `'0,15,30 0 * * *'`, timezone `Europe/Madrid`
- [ ] 4.2 Verificar con `bin/console debug:scheduler` (o equivalente) que las nuevas ejecuciones aparecen en la ventana esperada

## 5. Specs y documentación

- [ ] 5.1 `make cs-fix` y `make test` en verde
- [ ] 5.2 Confirmar que `Especificaciones.md` no necesita cambios adicionales más allá de lo ya cubierto por la spec delta (revisar sección 3.3 y añadir una línea si procede)
