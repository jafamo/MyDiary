## Why

Es la fase 1 de `ROADMAP.md` (API para la app de iPhone). Hoy los cálculos de Estadísticas, Historial, Recordatorios y Búsqueda viven en los controladores web (métodos privados y bucles dentro de la acción), así que una API JSON tendría que duplicarlos. Antes de abrir la API (fase 2) hay que dejar esa lógica en servicios reutilizables.

## What Changes

Refactor **sin cambio funcional**: las cuatro vistas devuelven exactamente el mismo HTML que hoy.

- `EstadisticasService` (nuevo): resolución del rango (presets 15/30/90/365 y `custom`), series diarias de audios y recordatorios, totales y medias, racha actual y mejor racha del rango, día récord, comparativa con el periodo anterior y datos de «Consumo IA» (tiles + días).
- `TokensChartBuilder` (nuevo, `src/Twig/`): geometría SVG del gráfico de tokens (`tokensChart` / `niceMax`). Es presentación de la web; la usa solo `EstadisticasController`.
- `MonthGrid` (nuevo, `src/Service/`): rejilla del mes (semanas lunes-domingo con días de relleno, mes anterior y siguiente), hoy duplicada en `HistorialController` y `RecordatoriosController`.
- `HistorialService` (nuevo): rejilla del mes con marcas de audios y resumen, y entradas del día seleccionado.
- `RecordatoriosService` (nuevo): rejilla del mes con marcas de recordatorios, recordatorios del día seleccionado, paginación de próximos e históricos, total del mes y próximo recordatorio.
- `SearchService` (nuevo): embedding de la consulta, fusión de transcripciones y resúmenes por distancia y búsqueda textual de recordatorios. El aviso de log `search.embedding_generation_failed` se emite desde el servicio, con los mismos campos.
- Los cuatro controladores quedan en «leer petición → llamar servicio → renderizar». `series_json` y `reminders_series_json` se siguen codificando en el controlador web.
- Los servicios devuelven arrays tipados (array-shape en PHPDoc), como `DiarioDashboardService`; «hoy» lo pasa el controlador como parámetro.
- Tests unitarios de los servicios y helpers extraídos. Los tests funcionales existentes no se modifican.

Sin migraciones, sin cambios de configuración y sin pasos de despliegue.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `automated-testing`: se añade el requisito de tests unitarios para los servicios de cálculo de las vistas (Estadísticas, Historial, Recordatorios, Búsqueda) y sus helpers.

Las capabilities `web-views`, `reminders`, `semantic-search` y `ai-usage-metrics` no cambian: su comportamiento observable es el mismo.

## Impact

- Código: `src/Controller/{Estadisticas,Historial,Recordatorios,Search}Controller.php` (adelgazan); nuevos `src/Service/{EstadisticasService,HistorialService,RecordatoriosService,SearchService,MonthGrid}.php` y `src/Twig/TokensChartBuilder.php`.
- Tests: nuevos en `tests/Service/` y `tests/Twig/`; los de `tests/Controller/` quedan intactos y son la red de seguridad del refactor.
- Plantillas Twig: sin cambios (mismas variables y claves).
- Documentación: `ROADMAP.md` (marcar la fase 1 con el nombre del change) y `CHANGELOG.md`. `Especificaciones.md` no cambia de contenido funcional.
