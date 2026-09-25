## Context

`templates/topics/index.html.twig` renderiza cada `Topic` como un `<article class="entry">` dentro de un único `<form>` de fusión; el renombrado es un `<details>` con **otro `<form>` anidado**, lo cual es HTML inválido (el navegador descarta el `<form>` interior y el botón "Guardar" acaba enviando el formulario de fusión). El selector de destino lista todos los temas. `TopicController::index()` obtiene los datos de `TopicRepository::findAllWithUsageCount()` (tema + nº de `DailySummary`). El proyecto ya usa JS vanilla servido desde `public/js/` (`estadisticas.js`, cargado en `{% block javascripts %}` con `defer`) y tiene estilos de tabla (`.chart-table`, `.usage-table`) en `public/css/app.css`.

Volumen esperado: decenas a pocos cientos de temas (un solo usuario). Se pueden renderizar todos en el HTML sin problema, pero hace falta paginarlos para que la lista se pueda leer.

## Goals / Non-Goals

**Goals:**
- Tabla buscable, ordenable y paginada de temas, con último uso como ayuda para decidir fusiones.
- Selección para fusión que no se pierde al filtrar ni al cambiar de página; destino limitado a los seleccionados.
- HTML válido (sin formularios anidados) y degradación correcta sin JS.

**Non-Goals:**
- Paginación ni búsqueda en servidor (`?page=`, `?q=`).
- Recordar página, orden o tamaño de página entre visitas.
- Sugerencias automáticas de duplicados, edición masiva o borrado de temas.
- Cambios en `TopicController::merge*`, en `TopicMerger`, en la página de confirmación o en la validación del renombrado.
- Librerías JS de tablas (DataTables, etc.) o Stimulus/AssetMapper.

## Decisions

- **Filtro y orden en cliente, no en servidor.** Con este volumen el coste es nulo, y un filtro por `GET ?q=` recargaría la página perdiendo los checkboxes marcados, que es justo el caso de uso (buscar "trab", marcar, buscar "curro", marcar, fusionar). Filtrar con `hidden` sobre las filas mantiene los inputs dentro del `<form>`, así que las filas ocultas marcadas se envían igual.
- **Paginación en cliente, no en servidor.** Mismo motivo: con `?page=` cada cambio de página recargaría y perdería los temas marcados, que a menudo están en páginas distintas (p. ej. "trabajo" con muchos resúmenes en la 1 y "curro" con pocos en la 3). Todas las filas se renderizan en el HTML y el JS decide cuáles se ven: pipeline `filtrar → ordenar → cortar [offset, offset+pageSize)`, poniendo `hidden` al resto. Como los `<tr>` nunca salen del DOM, los checkboxes de otras páginas se envían con el `<form>` sin trucos. Controles: anterior/siguiente, números de página (con elipsis si hay muchas), texto "1–25 de 60" y `<select>` de 25/50/100. Filtrar, ordenar o cambiar el tamaño vuelve a la página 1. Los controles se ocultan cuando el resultado filtrado cabe en el tamaño mínimo (≤ 25 filas); con más filas se muestran aunque el tamaño elegido (50/100) deje una sola página, para no perder el `<select>` de tamaño.
- **DataTables descartado.** La v2 sigue necesitando jQuery (~180 KB entre ambos) y el proyecto no carga ninguna librería de terceros hoy: habría que añadirlas en `public/vendor/` o depender de un CDN. Además, DataTables quita del DOM las filas de otras páginas, así que sus checkboxes no se enviarían con el formulario: habría que reinyectarlos como `hidden` al enviar. Su CSS tampoco encaja con el diseño propio y habría que sobrescribirlo. A cambio solo ahorraría unas 100 líneas de JS vanilla, poco para el criterio de AGENTS.md ("introducir un patrón solo cuando el problema ya existe"). Se reconsideraría si hicieran falta filtros por columna, exportación o datos en servidor.
- **Normalización de búsqueda**: `toLowerCase()` + `normalize('NFD')` quitando diacríticos, para que "educacion" encuentre "Educación". Valores de orden en `data-*` (`data-name`, `data-count`, `data-last-used` ISO `Y-m-d`) para no parsear texto formateado.
- **Último uso en la misma consulta**: `findAllWithUsageCount()` añade `MAX(ds.date) AS lastUsed` (ya hace `LEFT JOIN` + `GROUP BY t.id`), devolviendo `lastUsed: ?\DateTimeImmutable`. Alternativa descartada: recorrer `$topic->getDailySummaries()` en Twig (N+1).
- **Formularios sin anidar**: la tabla vive dentro del `<form>` de fusión; cada fila de renombrado tiene su `<form id="topic-rename-{id}">` vacío **fuera** de la tabla (al final de la plantilla), y su `<input>`/`<button>` dentro de la celda usan `form="topic-rename-{id}"`. Alternativa descartada: un `<form>` por fila envolviendo la tabla entera → no es posible con filas de tabla válidas.
- **Renombrado inline**: se mantiene `<details>` en la celda de acciones (patrón ya usado en la app), sin JS obligatorio.
- **Destino restringido**: sin JS el `<select>` lista todos los temas (comportamiento actual). Con JS, al cambiar la selección se deshabilitan/ocultan las `<option>` no seleccionadas, se autoselecciona la de más resúmenes entre las marcadas, y el botón "Fusionar" se desactiva con menos de 2 marcados. Junto al contador se indica "(N fuera de la vista)" cuando hay marcados ocultos por el filtro o en otra página, y un botón "Quitar selección" desmarca todos. Las filas marcadas se resaltan. El servidor ya valida (excluye destino de orígenes y exige al menos un origen), así que el JS es solo ergonomía.
- **Orden por defecto en servidor** (count DESC, name ASC), el JS solo reordena nodos `<tr>` existentes. Sin JS no hay paginación: se ven todas las filas (aceptable para el volumen previsto). La cabecera activa lleva `aria-sort`; las cabeceras son `<button>` dentro de `<th>` para accesibilidad por teclado.
- **Estilos**: nueva clase `.topics-table` reutilizando tokens (`--border`, `--text-muted`) y el look de `.chart-table`; contenedor con `overflow-x: auto` y la columna "Último uso" oculta bajo el breakpoint móvil existente. Barra de fusión `position: sticky; bottom` para que sea accesible con listas largas.

## Risks / Trade-offs

- [Filas marcadas ocultas por el filtro o en otra página se fusionan sin que el usuario las vea] → Mitigación: el contador muestra "N seleccionados" (incluidas las no visibles) con el aviso "(N fuera de la vista)" y la página de confirmación existente lista todos los orígenes antes de ejecutar.
- [Tras un error de renombrado (422) la tabla se re-renderiza y se pierde la selección] → Aceptado: es el comportamiento actual y el caso es raro.
- [Con varios miles de temas renderizar todas las filas pesaría] → Aceptado: muy lejos del volumen real; si ocurriera, pasar a paginación en servidor con selección guardada en sesión.
- [La lógica en JS no está cubierta por PHPUnit] → Mitigación: el JS es progresivo; los tests funcionales cubren el HTML servido (tabla, `data-*`, atributos `form=`, orden por defecto) y la fusión con orígenes enviados; la interacción se verifica a mano en navegador.

## Migration Plan

Sin migraciones ni cambios de rutas. `git flow feature finish topics-table-view` → `develop` → siguiente release. Rollback: revertir el merge.

## Open Questions

(ninguna)
