## Why

La vista de Búsqueda (`/busqueda`) solo consulta transcripciones y resúmenes diarios por similitud semántica; los recordatorios (`Reminder`) quedan fuera, así que no hay forma de encontrar un recordatorio por las palabras de su texto ("dentista", "ITV") sin recorrer el calendario mes a mes.

## What Changes

- La Búsqueda también busca en `Reminder.text` por palabras: coincidencia de subcadena, sin distinguir mayúsculas/minúsculas ni tildes (`dentista` encuentra "Cita con el Dentista", `medico` encuentra "Médico").
- Los recordatorios que coinciden se muestran en una sección propia **"Recordatorios"** encima de los resultados semánticos actuales (transcripciones + resúmenes), con fecha, hora (si tiene) y texto, y un enlace al día en `/recordatorios`.
- La búsqueda de recordatorios no depende de Ollama: si falla la generación del embedding de la consulta, los recordatorios se siguen mostrando (hoy la vista queda vacía).
- Migración que activa la extensión PostgreSQL `unaccent`.
- Sin embeddings para `Reminder` y sin cambios en el ranking semántico actual.

## Capabilities

### New Capabilities
<!-- ninguna -->

### Modified Capabilities
- `semantic-search`: la vista de Búsqueda incorpora una búsqueda por palabras sobre recordatorios, mostrada en sección aparte e independiente del embedding de la consulta.

## Impact

- `src/Repository/ReminderRepository.php`: nuevo método de búsqueda por texto.
- `src/Controller/SearchController.php`: consulta los recordatorios además de los resultados semánticos.
- `templates/busqueda/index.html.twig`: nueva sección "Recordatorios" y textos de estado vacío.
- `migrations/`: `CREATE EXTENSION IF NOT EXISTS unaccent` (disponible en la imagen `pgvector/pgvector`, en producción y en CI).
- Tests funcionales de `/busqueda` y del repositorio.
- `Especificaciones.md`: la sección de Búsqueda menciona los recordatorios.
