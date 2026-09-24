## 1. Datos

- [x] 1.1 Ampliar `TopicRepository::findAllWithUsageCount()` con `MAX(ds.date)` y devolver `lastUsed: ?\DateTimeImmutable` en cada fila (actualizar el `@return`).
- [x] 1.2 Test en `TopicRepositoryTest`: último uso correcto con varios `DailySummary` y `null` para un tema sin resúmenes.

## 2. Plantilla

- [x] 2.1 Reescribir `templates/topics/index.html.twig` como tabla (`.topics-table`) dentro del `<form>` de fusión: columnas selección, nombre, resúmenes, último uso, acciones; atributos `data-name`, `data-count`, `data-last-used` por fila; cabeceras ordenables como `<button>` con `aria-sort`.
- [x] 2.2 Añadir campo de búsqueda (`type="search"`) encima de la tabla y mensaje "Ningún tema coincide" para filtro sin resultados.
- [x] 2.3 Mover los `<form>` de renombrado fuera de la tabla (`id="topic-rename-{id}"`) y enlazar sus campos con el atributo `form=`; mantener el `<details>` inline y el bloque `rename_error`.
- [x] 2.4 Contenedor de paginación bajo la tabla (vacío en el HTML, lo rellena el JS) y `<select>` de filas por página (25/50/100).
- [x] 2.5 Barra de fusión (sticky) con contador de seleccionados, `<select>` de destino completo y botón "Fusionar"; cargar `js/topics.js` en `{% block javascripts %}` con `defer`.

## 3. JavaScript y estilos

- [x] 3.1 Crear `public/js/topics.js` (IIFE vanilla, estilo de `estadisticas.js`): filtro por nombre insensible a mayúsculas y acentos, ocultando filas con `hidden`.
- [x] 3.2 Ordenación por columna (nombre, resúmenes, último uso) reordenando `<tr>`, alternando sentido y actualizando `aria-sort`.
- [x] 3.3 Paginación: pipeline filtrar → ordenar → paginar, controles anterior/siguiente + números con elipsis + texto "X–Y de N", cambio de tamaño de página, vuelta a página 1 al filtrar/ordenar/cambiar tamaño, controles ocultos con ≤ 25 resultados filtrados.
- [x] 3.4 Contador de seleccionados, restricción del `<select>` de destino a los marcados (autoseleccionando el de más resúmenes) botón desactivado con menos de 2, aviso "(N fuera de la vista)", botón "Quitar selección" y resaltado de filas marcadas.
- [x] 3.5 Estilos `.topics-table`, buscador, paginación y barra de fusión en `public/css/app.css` (tokens existentes, modo oscuro, `overflow-x: auto`, ocultar "Último uso" en móvil).

## 4. Tests y verificación

- [x] 4.1 Actualizar `TopicControllerTest`: la vista contiene la tabla con las filas en el orden por defecto, el último uso y los `form=` de renombrado; el renombrado y la fusión siguen funcionando con los mismos nombres de campo.
- [x] 4.2 `make cs-check`, `make phpstan` y `make test` en verde.
- [ ] 4.3 Probar en navegador: buscar, ordenar, paginar (incluido cambiar tamaño), marcar temas en páginas distintas y ocultos por el filtro y fusionarlos, renombrar desde la tabla, y comprobar la vista en móvil y sin JS.
