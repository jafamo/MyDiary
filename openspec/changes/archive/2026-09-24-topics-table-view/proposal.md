## Why

La vista `/topics` muestra cada `Topic` como una tarjeta `entry` apilada una detrás de otra. Con decenas de temas generados por el LLM resulta difícil localizar uno concreto, comparar frecuencias y, sobre todo, seleccionar varios duplicados para fusionarlos (el selector de destino lista además todos los temas, no solo los elegidos). Hace falta una presentación tabular, buscable y ordenable, para que la gestión de temas siga siendo práctica a medida que crecen.

## What Changes

- La vista `/topics` pasa a ser una **tabla** con columnas: selección (checkbox), nombre, nº de resúmenes, último uso (fecha del `DailySummary` más reciente asociado) y acciones (renombrar).
- **Búsqueda instantánea** por nombre (filtro en cliente, sin recargar), que conserva los temas ya marcados aunque queden ocultos por el filtro o estén en otra página.
- **Paginación** en cliente (25 temas por página por defecto, selector 25/50/100) aplicada sobre el resultado filtrado y ordenado; la selección para fusión se conserva al cambiar de página.
- **Ordenación** por columna (nombre, nº de resúmenes, último uso) al pulsar la cabecera; por defecto, frecuencia descendente como hoy.
- **Barra de fusión** con contador de seleccionados (avisando de cuántos están fuera de la vista), botón "Quitar selección" y selector de destino restringido a los temas seleccionados (con JS); sin JS sigue funcionando con el selector completo actual.
- Se corrige el HTML inválido actual (formulario de renombrado anidado dentro del formulario de fusión) asociando los campos de renombrado a su propio `<form>` mediante el atributo `form=`.
- Sin cambios en el flujo de confirmación de fusión, en la validación del renombrado ni en el modelo de datos.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `topic-management`: el requirement "Vista de gestión de temas" pasa a exigir presentación tabular con último uso, búsqueda por nombre, ordenación por columnas, paginación y selección para fusión que sobrevive al filtrado y al cambio de página, con destino restringido a los seleccionados.

## Impact

- **`templates/topics/index.html.twig`**: reescrita como tabla + campo de búsqueda + barra de fusión.
- **Nuevo `public/js/topics.js`** (JS vanilla sin dependencias, mismo estilo que `public/js/estadisticas.js`, cargado vía `{% block javascripts %}`): filtro, ordenación, paginación, contador y restricción del selector de destino.
- **`public/css/app.css`**: estilos de la tabla de temas (reutilizando tokens y el estilo de `.chart-table` / `.usage-table`), incluida la adaptación a móvil.
- **`TopicRepository::findAllWithUsageCount()`**: añade `MAX(ds.date)` como último uso.
- **Tests**: `TopicControllerTest` / test de repositorio actualizados para la nueva estructura y el último uso.
- Sin migraciones, sin nuevas dependencias (se descarta DataTables, ver `design.md`), sin cambios de rutas.
