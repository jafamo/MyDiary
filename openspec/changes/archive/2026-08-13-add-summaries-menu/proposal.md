## Why

Hoy el único sitio donde se puede leer un `DailySummary` es el panel del propio día en Diario (solo hoy) o entrando día a día en el calendario de Historial. No hay ninguna vista que permita repasar los resúmenes generados como una lista propia, filtrarlos por fecha, ni ver sus temas de un vistazo. A medida que se acumulan meses de resúmenes, hace falta una pantalla dedicada a explorarlos.

## What Changes

- Nuevo menú "Resúmenes" (añadido a la barra lateral, la barra inferior móvil y como ruta `/resumenes`), junto a Diario, Historial y Estadísticas.
- Vista por defecto sin filtro: los últimos 5 `DailySummary` generados (más reciente primero), cada uno con fecha, texto del resumen, sus `Topic` (etiquetas) y un botón que enlaza al día correspondiente en Historial (`/historial?date=...`), donde están todos los audios de ese día.
- Filtro por rango de fechas (`from`/`to`) que, al aplicarse, sustituye el listado de "últimos 5" por todos los `DailySummary` dentro de ese rango, paginados (mismo patrón de filtros por querystring que Historial/Estadísticas, funcional sin JavaScript).
- Paginación simple (anterior/siguiente) sobre el resultado filtrado por rango.

## Capabilities

### New Capabilities
- `summaries-view`: pantalla "Resúmenes" con listado de los últimos 5 resúmenes por defecto, filtro por rango de fechas con paginación, etiquetas (`Topic`) por resumen, y enlace a Historial para ver los audios del día.

### Modified Capabilities
- `web-views`: la navegación (sidebar y bottom-nav) SHALL incluir un cuarto destino "Resúmenes" junto a Diario, Historial y Estadísticas.

## Impact

- **Rutas/Controladores**: nuevo `SummariesController` (`GET /resumenes`).
- **Repositorio**: `DailySummaryRepository` gana métodos para los últimos N resúmenes y para listar+contar resúmenes paginados dentro de un rango de fechas (reutilizando `Topic` ya cargado vía la relación N:M existente, sin nuevas entidades ni migraciones).
- **Plantillas**: nueva `templates/resumenes/index.html.twig`; `templates/base.html.twig` gana el enlace "Resúmenes" en sidebar y bottom-nav.
- **Especificaciones (`openspec/specs/`)**: nueva capability `summaries-view`; delta en `web-views` para el ítem de navegación adicional.
- Sin cambios en el modelo de datos, en la generación de resúmenes, ni en Historial/Estadísticas existentes (el enlace desde Resúmenes reutiliza la ruta `app_historial` ya existente con el parámetro `date`).
