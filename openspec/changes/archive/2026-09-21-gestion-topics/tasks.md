## 1. Repositorio y lógica de dominio

- [x] 1.1 Añadir `TopicRepository::findAllWithUsageCount()` (o similar) que devuelva todos los `Topic` con su número de `DailySummary` asociados, ordenados por frecuencia descendente.
- [x] 1.2 Añadir `TopicRepository::findOneByNameCaseInsensitive(string $name, ?int $excludeId = null)` para la validación de duplicados al renombrar.
- [x] 1.3 Implementar la lógica de fusión (en `TopicRepository` o en un nuevo servicio `TopicMerger`): reasignar los `DailySummary` de los `Topic` origen al `Topic` destino (sin duplicar, usando `addTopic`/`Collection::contains()`) y eliminar los `Topic` origen.
- [x] 1.4 Tests unitarios/funcionales para el conteo de uso, la comprobación case-insensitive y la fusión (incluyendo el caso de `DailySummary` compartido entre origen y destino).

## 2. Formularios

- [x] 2.1 Crear `TopicRenameType` (`FormType` con campo `name`).
- [x] 2.2 Crear `TopicMergeType` (`FormType` con `EntityType` multiple para los `Topic` origen + `EntityType` single para el `Topic` destino).

## 3. Controlador y rutas

- [x] 3.1 Crear `TopicController` con `GET /topics` (listado con frecuencia de uso).
- [x] 3.2 Añadir acción `POST /topics/{id}/renombrar` que valida duplicados y persiste el renombrado, con mensaje flash de éxito/error.
- [x] 3.3 Añadir acción `POST /topics/fusionar` que ejecuta la fusión tras confirmación, con mensaje flash de éxito.
- [x] 3.4 Tests funcionales del controlador (listado, renombrado válido/duplicado, fusión con y sin solapamiento).

## 4. Vistas

- [x] 4.1 Plantilla `templates/topics/index.html.twig`: listado de temas con frecuencia, acción de renombrar inline y selección para fusionar.
- [x] 4.2 Paso de confirmación de fusión (modal o página intermedia) mostrando los `Topic` origen, el destino y el número de `DailySummary` afectados.
- [x] 4.3 Añadir enlace "Temas" a `templates/base.html.twig` (sidebar y bottom-nav), junto a los enlaces existentes.

## 5. Verificación final

- [x] 5.1 `make cs-check` y `make test` en verde.
- [x] 5.2 Probar manualmente: renombrar un tema, fusionar dos temas con solapamiento de `DailySummary`, y confirmar que Estadísticas/Resúmenes reflejan el resultado. (Verificado vía tests funcionales con BD real — `TopicControllerTest`/`TopicMergerTest` — más smoke test de la ruta `/topics` protegida por auth; no se ha probado a ojo en el navegador.)
