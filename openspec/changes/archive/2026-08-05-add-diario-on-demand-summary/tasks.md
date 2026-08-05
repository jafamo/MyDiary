## 1. Servicio

- [x] 1.1 Añadir el parámetro `waitForPending: bool = true` a `DailySummaryService::generateForDate()`, saltando `waitForPendingTranscriptions()` cuando sea `false`.
- [x] 1.2 Ejecutar `tests/Service/DailySummaryServiceTest.php` para confirmar que el comportamiento por defecto (Scheduler/consola) no cambia.
- [x] 1.3 Añadir test cubriendo `generateForDate($date, waitForPending: false)`: no debe invocar la espera aunque existan `AudioRecording` `PENDING`.

## 2. Controlador web

- [x] 2.1 Crear `src/Controller/DailySummaryController.php` con `generate()`: ruta `POST /diario/resumen`, CSRF manual (`token_id` `daily_summary_generate`), llama a `DailySummaryService::generateForDate(DateRange::nowInMadrid(), waitForPending: false)`, redirige a `Referer`.
- [x] 2.2 Añadir test `tests/Controller/DailySummaryControllerTest.php` cubriendo: generación exitosa (crea `DailySummary` si no existía), regeneración (actualiza uno existente sin duplicar), y token CSRF inválido (rechazo).

## 3. Plantilla

- [x] 3.1 Añadir botón "Generar resumen" en `templates/diario/index.html.twig`, visible siempre (sirve tanto para generar por primera vez como para regenerar).
- [x] 3.2 Verificar que el botón redirige de vuelta a Diario tras completarse. (Cubierto por `DailySummaryControllerTest` — redirige a `Referer`, mismo patrón que retry/eliminar.)

## 4. Verificación y housekeeping

- [x] 4.1 `make test` y `make cs-check` en verde.
- [x] 4.2 Verificación manual en navegador (`make up`): generar el resumen antes de las 21:00, y regenerarlo tras haberlo generado ya una vez.
- [x] 4.3 Actualizar `Especificaciones.md` (sección 3.3) si algún detalle del disparo bajo demanda cambió durante la implementación.
