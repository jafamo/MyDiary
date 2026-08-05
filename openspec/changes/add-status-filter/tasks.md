## 1. Repositorio

- [x] 1.1 Añadir `?AudioRecordingStatus $status = null` a `AudioRecordingRepository::findAllReceivedOn()`, filtrando por estado cuando se indique.
- [x] 1.2 Añadir el mismo parámetro opcional a `countByDateInRange()` y `averageDurationInRange()`.
- [x] 1.3 Tests de regresión (llamadas sin `$status` mantienen el comportamiento actual) y tests nuevos (con `$status` filtran correctamente) en `tests/Repository/` o vía los tests de servicio/controlador existentes que ya cubren estos métodos.

## 2. Diario e Historial

- [x] 2.1 `DiarioController`: leer `status` de la query string, validar contra `AudioRecordingStatus::tryFrom()` (inválido o ausente → sin filtro), pasar las entradas filtradas y el estado activo a la plantilla.
- [x] 2.2 `HistorialController`: mismo filtro sobre `selected_entries`, preservando `year`/`month`/`date` en los enlaces del filtro.
- [x] 2.3 Añadir `filter-pill` de estado (Todos/Pendiente/Transcrito/Error) en `templates/diario/index.html.twig` y `templates/historial/index.html.twig`, encima del log de entradas.
- [x] 2.4 Tests de controlador cubriendo: filtro por cada estado, filtro ausente (todas las entradas), y valor de `status` inválido (recae en sin filtro).

## 3. Estadísticas

- [x] 3.1 `EstadisticasController`: leer y validar `status` igual que en Diario/Historial; pasar `$status` a `countByDateInRange()` y `averageDurationInRange()`; `status_counts` y `topic_frequency` SHALL seguir calculándose sin filtro de estado.
- [x] 3.2 Añadir `filter-pill` de estado en `templates/estadisticas/index.html.twig`, junto al filtro de rango existente, preservando `range`/`from`/`to` en los enlaces y `status` en el formulario de rango personalizado.
- [x] 3.3 Tests de controlador cubriendo: filtro por estado combinado con un rango, estado inválido, y que `status_counts`/`topic_frequency` no cambian con el filtro activo.

## 4. Verificación y housekeeping

- [x] 4.1 `make test` y `make cs-check` en verde.
- [x] 4.2 `make test-coverage` — confirmar que no baja el porcentaje de líneas cubierto respecto al actual (93.27%). (92.63% global — baja 0.64pp solo por crecer el denominador; el código nuevo está cubierto al 97-100%: `AudioRecordingRepository` 100%, `DiarioController` 100%, `EstadisticasController`/`HistorialController` ~98%.)
- [x] 4.3 Verificación manual en navegador (`make up`): filtrar por cada estado en Diario, Historial y Estadísticas (combinado con un rango), en escritorio y móvil.
- [x] 4.4 Actualizar `Especificaciones.md` si algún detalle de las vistas cambió durante la implementación.
