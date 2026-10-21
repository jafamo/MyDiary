## Why

La interfaz web usa «Tema» para dos cosas distintas: el destino del menú «Temas» (los `Topic` que salen de los resúmenes) y la sección «Tema» del menú de usuario (el tema visual Moderno/Cuaderno, capability `ui-themes`). Llamar «Categorías» a los `Topic` en la web quita esa ambigüedad y describe mejor lo que son.

## What Changes

- El destino de navegación «Temas» pasa a llamarse «Categorías», tanto en la barra lateral como en el panel «Más».
- Los textos visibles de la gestión (`/topics`) dicen «categoría(s)» en lugar de «tema(s)»: título, estado vacío, buscador, contador, mensaje de que el filtro no encuentra nada, error de nombre duplicado al renombrar y página de confirmación de la fusión.
- En Diario, «Tema del mes» pasa a «Categoría del mes». En Estadísticas, «Temas más frecuentes» pasa a «Categorías más frecuentes», y su estado vacío también cambia.
- **No cambia**: la ruta `/topics` ni los nombres de ruta, la API `/api/v1/topics` (rutas, campos como `top_topic`, mensajes de error y el tag OpenAPI), las clases PHP, las tablas `topic` / `daily_summary_topic`, el prompt de Ollama y el mensaje de Telegram. No hay migración.

## Capabilities

### New Capabilities

_(ninguna)_

### Modified Capabilities

- `topic-management`: la vista de gestión y el error de renombrado usan el término «Categorías» / «categoría»; se añade un requisito que fija ese término en la interfaz web.
- `web-views`: el destino de navegación «Temas» pasa a llamarse «Categorías».

## Impact

- Plantillas Twig: `base.html.twig`, `topics/index.html.twig`, `topics/confirmar_fusion.html.twig`, `_partials/activity_card.html.twig`, `estadisticas/index.html.twig`.
- `public/js/topics.js`: el contador «N temas» / «N de M temas».
- `src/Controller/TopicController.php`: mensaje de nombre duplicado (solo la web; la API conserva el suyo).
- Tests: `tests/Controller/NavigationTest.php` y los tests de la vista de temas que comprueban textos.
- `Especificaciones.md`: navegación y vistas, con una nota de que en la web los `Topic` se llaman «Categorías».
- Base de datos: sin cambios. Se ha comprobado el esquema local: `topic` y `daily_summary_topic` se quedan como están. El desfase que señala `doctrine:schema:validate` (índices HNSW de los embeddings) ya existía y no tiene que ver con este cambio.
