## Why

La API ya permite autenticarse y subir audios, pero no consultar nada: la app de iPhone no puede ver el diario, el historial ni los resúmenes. Es la fase 3 del `ROADMAP.md` (bloqueo 1) y, tras la subida de audio, lo que hace la app utilizable para leer lo que se graba.

## What Changes

Nueve endpoints `GET` bajo `/api/v1`, equivalentes a las vistas web y apoyados en los servicios extraídos en la fase 1. La web no cambia.

- `GET /api/v1/diario` — audios de hoy con su transcripción, resumen del día, rachas, total de la semana y tema del mes. Filtro `status`.
- `GET /api/v1/historial?month=AAAA-MM` — días del mes que tienen audios o resumen (la app pinta su propio calendario).
- `GET /api/v1/historial/{fecha}` — audios y resumen de un día. Filtro `status`.
- `GET /api/v1/resumenes` — resúmenes paginados, del más reciente al más antiguo, con el número de audios de cada día. Filtros `from` / `to`.
- `GET /api/v1/busqueda?q=` — búsqueda semántica en transcripciones y resúmenes más búsqueda textual en recordatorios.
- `GET /api/v1/estadisticas` — métricas del rango y consumo de IA, con las series en crudo. Filtros `range`, `from`, `to` y `status`.
- `GET /api/v1/recordatorios` — recordatorios paginados. Filtros `scope` (`upcoming` / `history`), `month` o `date`.
- `GET /api/v1/recordatorios/proximos` — el aviso de la campana: cuántos hay en los próximos 5 días, urgencia y los del día más cercano.
- `GET /api/v1/topics` — todos los temas con su número de usos y fecha del último.

Además:

- Formas de respuesta únicas para audio (con su transcripción), resumen, recordatorio y tema, compartidas por todos los endpoints mediante presenters en `src/Controller/Api/Presenter/`.
- Parámetros de consulta inválidos (`status`, `month`, fechas, `page`, `per_page`, `range`, `scope`) responden `422 validation_failed` en lugar de ignorarse en silencio como hace la web.
- El cálculo del aviso de la campana pasa de `ReminderRuntime` (Twig) a `RecordatoriosService`, para que web y API usen el mismo; la web muestra lo mismo que hoy.
- Documentación de cada endpoint en el mismo cambio: atributos OpenAPI, `doc/openapi.json`, colección Postman y `doc/api.http`.

## Capabilities

### New Capabilities

Ninguna: son endpoints de la capacidad `api`.

### Modified Capabilities

- `api`: nuevos requisitos de lectura (diario, historial, resúmenes, búsqueda, estadísticas, recordatorios y temas), formas de respuesta compartidas y validación de parámetros de consulta.

## Impact

- **Código nuevo:** controladores en `src/Controller/Api/` (`DiarioController`, `HistorialController`, `ResumenesController`, `BusquedaController`, `EstadisticasController`, `RecordatoriosController`, `TopicsController`), presenters en `src/Controller/Api/Presenter/` y `ApiQuery` (lectura y validación de parámetros).
- **Código modificado:** `AudioController` (usa el presenter, misma respuesta), `ApiFormatter`, `HistorialService`, `RecordatoriosService`, `ReminderRepository` y `ReminderRuntime`.
- **Sin cambios:** base de datos, dependencias, infraestructura, vistas web y webhook de Telegram. Sin migraciones ni pasos de despliegue especiales.
- **Documentación:** `config/packages/nelmio_api_doc.yaml` (esquemas compartidos), `doc/openapi.json`, `doc/MyDiary.postman_collection.json`, `doc/api.http`, `Especificaciones.md` (3.7), `ROADMAP.md` y `CHANGELOG.md`.
