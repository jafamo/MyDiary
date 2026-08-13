## 1. Repositorio

- [x] 1.1 Añadir `DailySummaryRepository::findLatest(int $limit = 5): list<DailySummary>` (orden por `date` DESC, `topics` cargados vía la relación existente).
- [x] 1.2 Añadir `DailySummaryRepository::findPageInRange(\DateTimeImmutable $from, \DateTimeImmutable $to, int $page, int $pageSize): list<DailySummary>` (orden por `date` DESC, `LIMIT`/`OFFSET`).
- [x] 1.3 Reutilizar `DailySummaryRepository::countInRange()` (ya existe) para calcular el número total de páginas del rango filtrado.
- [x] 1.4 Tests de repositorio: últimos 5 con más/menos de 5 registros, página dentro de rango, rango sin resultados, orden descendente.

## 2. Controlador

- [x] 2.1 Crear `SummariesController` (`GET /resumenes`, ruta `app_resumenes`) siguiendo el patrón `__invoke` de `EstadisticasController`/`HistorialController`.
- [x] 2.2 Parsear `from`/`to` de la querystring; si ambos son fechas válidas y `from <= to`, activar modo "rango filtrado"; si no, modo por defecto (últimos 5), ignorando un rango inválido sin error.
- [x] 2.3 En modo rango, parsear `page` (entero ≥1, por defecto 1), calcular `pageSize` fijo (20), acotar `page` al total de páginas disponible si lo excede, y consultar `findPageInRange`.
- [x] 2.4 En modo por defecto, consultar `findLatest(5)` y no mostrar controles de paginación.
- [x] 2.5 Pasar a la plantilla: lista de resúmenes, modo actual, `from`/`to` aplicados, página actual y total de páginas (si aplica).
- [x] 2.6 Tests de controlador: sin filtro (últimos 5), con rango válido paginado, rango inválido (recae en últimos 5), página fuera de rango, resumen sin `Topic`.

## 3. Plantilla y navegación

- [x] 3.1 Crear `templates/resumenes/index.html.twig`: por cada `DailySummary`, fecha, `summary_text`, chips de `Topic` (reutilizando las clases CSS ya usadas para temas en `estadisticas/index.html.twig`), y el número de audios del día como enlace a `app_historial` con `date` igual a la fecha del resumen.
- [x] 3.6 Añadir el número total de `AudioRecording` del día a cada resumen (`AudioRecordingRepository::countByDateInRange` sobre el rango de fechas de los resúmenes mostrados, una sola consulta por página).
- [x] 3.2 En la misma plantilla: formulario de filtro por rango de fechas (`from`/`to`) que funcione sin JavaScript (submit GET), siguiendo el patrón de filtros de `estadisticas/index.html.twig`.
- [x] 3.3 Controles de paginación (anterior/siguiente, conservando `from`/`to` en la URL) visibles solo en modo rango filtrado.
- [x] 3.4 Mensaje de "sin resultados" cuando el rango filtrado no tiene resúmenes.
- [x] 3.5 Añadir el ítem "Resúmenes" (icono + enlace a `app_resumenes`, con `aria-current="page"` cuando corresponda) en `templates/base.html.twig`: barra lateral (`sidebar__nav`) y barra inferior móvil (`bottom-nav`).

## 4. Especificaciones y cierre

- [x] 4.1 Ejecutar `make cs-check` y `make test` y corregir lo que falle.
- [x] 4.2 Verificar manualmente en navegador: listado por defecto, filtro de rango, paginación, chips de temas, y el número de audios como enlace a Historial con el día correcto ya seleccionado.
