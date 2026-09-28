## 1. Base de datos y Doctrine

- [x] 1.1 Migración que ejecuta `CREATE EXTENSION IF NOT EXISTS unaccent` (`down()`: `DROP EXTENSION IF EXISTS unaccent`)
- [x] 1.2 Función DQL `unaccent` (`src/Doctrine/Unaccent.php`) registrada en `config/packages/doctrine.yaml`

## 2. Repositorio

- [x] 2.1 `ReminderRepository::searchByText(string $query, int $limit)`: subcadena sin distinguir mayúsculas/minúsculas ni tildes, con `%`, `_` y `\` escapados, ordenado por fecha descendente
- [x] 2.2 Tests del repositorio: coincidencia simple, mayúsculas/tildes, comodines literales, límite y orden

## 3. Controlador y vista

- [x] 3.1 `SearchController`: inyectar `ReminderRepository`, buscar recordatorios con `MAX_REMINDER_RESULTS = 20` aunque falle el embedding y pasar `reminders` a la plantilla
- [x] 3.2 `templates/busqueda/index.html.twig`: sección "Recordatorios (N)" encima de los resultados semánticos, con fecha, hora opcional, texto y enlace a `app_recordatorios` (date/year/month); "Sin resultados" solo si ambas listas están vacías; texto de ayuda actualizado
- [x] 3.3 Tests funcionales de `/busqueda`: sección con coincidencias, sin sección si no hay coincidencias, recordatorios mostrados con el embedding fallando

## 4. Documentación y cierre

- [x] 4.1 Actualizar `Especificaciones.md` §3.6 con la búsqueda de recordatorios
- [x] 4.2 Entrada en `CHANGELOG.md` bajo `## [Sin publicar]` (Añadido + Migraciones)
- [x] 4.3 `make test`, `make cs-check` y `make phpstan` en verde
