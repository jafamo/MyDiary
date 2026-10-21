## 1. Rama

- [ ] 1.1 `git flow feature start rename-topics-to-categories` desde `develop`

## 2. Textos de la interfaz web

- [ ] 2.1 `templates/base.html.twig`: ítem de navegación `app_topics` con `label` y `short` «Categorías»
- [ ] 2.2 `templates/topics/index.html.twig`: título, `<h1>`, estado vacío, placeholder y `aria-label` del buscador, contador y mensaje de filtro sin coincidencias
- [ ] 2.3 `public/js/topics.js`: contador «N categorías» / «N de M categorías»
- [ ] 2.4 `templates/topics/confirmar_fusion.html.twig`: título y aviso de que las categorías fusionadas dejan de existir
- [ ] 2.5 `src/Controller/TopicController.php`: mensaje de nombre duplicado con «categoría» (el de `Api/TopicsController` no se toca)
- [ ] 2.6 `templates/_partials/activity_card.html.twig`: «Categoría del mes»
- [ ] 2.7 `templates/estadisticas/index.html.twig`: «Categorías más frecuentes» y su estado vacío
- [ ] 2.8 `grep -niE '\btemas?\b' templates public/js src/Controller/TopicController.php`: cada coincidencia que quede se refiere al tema visual o es un comentario de código

## 3. Tests

- [ ] 3.1 `tests/Controller/NavigationTest.php`: la lista de destinos espera «Categorías»
- [ ] 3.2 Tests de la vista `/topics`: título «Categorías», estado vacío y mensaje de duplicado con «categoría»
- [ ] 3.3 Tests de Diario y Estadísticas: comprueban «Categoría del mes» y «Categorías más frecuentes»
- [ ] 3.4 Los tests de la API (`TopicsWriteControllerTest`) siguen pasando sin cambios
- [ ] 3.5 `make cs-check`, `make phpstan` y `make test` en verde

## 4. Documentación y cierre

- [ ] 4.1 `Especificaciones.md`: navegación y vistas con «Categorías», y nota «`Topic` = Categoría en la web; código, API y BD siguen como `topic`»
- [ ] 4.2 CHANGELOG `## [Sin publicar]` → `Cambiado`
- [ ] 4.3 Sincronizar las specs y archivar el cambio
- [ ] 4.4 Resumen de revisión al usuario y esperar su OK antes del finish
