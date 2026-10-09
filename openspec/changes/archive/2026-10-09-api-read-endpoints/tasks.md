## 1. Base común

- [x] 1.1 Presenters en `src/Controller/Api/Presenter/`: `AudioPresenter` (`brief` / `detail`), `SummaryPresenter`, `ReminderPresenter` y `TopicPresenter`, con tests unitarios (incluido que no salen `file_path`, `content_hash` ni identificadores de Telegram)
- [x] 1.2 `AudioController` usa `AudioPresenter::brief()` sin cambiar su respuesta
- [x] 1.3 `ApiQuery`: `status`, `day`, `month`, `page`, `perPage` y `choice`, con `422` en valores inválidos; tests unitarios
- [x] 1.4 Esquemas compartidos (`Audio`, `Transcription`, `Summary`, `Reminder`, `Topic`, `Statistics`) y parámetros `page` / `per_page` en `nelmio_api_doc.yaml` (`ApiException::notFound()` no hizo falta: ningún endpoint de lectura responde `404` propio)
- [x] 1.5 Ayudas de test en `ApiTestCase` para crear audios, transcripciones, resúmenes y recordatorios y limpiarlos

## 2. Diario e historial

- [x] 2.1 `Api\DiarioController` (`GET /api/v1/diario`) con atributos OpenAPI
- [x] 2.2 `HistorialService::monthDays()` (días del mes con audios o resumen), cubierto por los tests funcionales de 2.4
- [x] 2.3 `Api\HistorialController`: `GET /api/v1/historial` y `GET /api/v1/historial/{fecha}` con atributos OpenAPI
- [x] 2.4 Tests funcionales: sin token, día vacío, audios con y sin transcripción, filtro `status`, estado inválido, mes con actividad, meses contiguos, mes inválido, día concreto, día sin nada y fecha imposible

## 3. Resúmenes

- [x] 3.1 Listado sin rango con `count([])` y `findBy()` del repositorio (no hicieron falta métodos nuevos)
- [x] 3.2 `Api\ResumenesController` (`GET /api/v1/resumenes`) con `audio_count` y atributos OpenAPI
- [x] 3.3 Tests funcionales: paginación, página más allá de la última, `per_page` fuera de rango, rango de fechas, rango incompleto e invertido

## 4. Búsqueda

- [x] 4.1 `Api\BusquedaController` (`GET /api/v1/busqueda`) con atributos OpenAPI
- [x] 4.2 Tests funcionales: resultados de los dos tipos ordenados por distancia, recordatorios por texto, consulta vacía y fallo del embedding

## 5. Estadísticas

- [x] 5.1 `Api\EstadisticasController` (`GET /api/v1/estadisticas`): validación de `range`, métricas y `ai_usage` en `snake_case`, con atributos OpenAPI
- [x] 5.2 Tests funcionales: rango por defecto, preset, rango personalizado, `custom` incompleto, `range` desconocido, filtro `status` y claves de `ai_usage`

## 6. Recordatorios

- [x] 6.1 `ReminderRepository::findPageInRange()` (el total sale de `countByDateInRange()`), cubierto por los tests funcionales de 6.4
- [x] 6.2 `RecordatoriosService::page()` (scope, mes o día, con tamaño de página) y `upcomingAlert()`; `ReminderRuntime` delega en el servicio; los tests de la campana web solo cambian la construcción del runtime
- [x] 6.3 `Api\RecordatoriosController`: `GET /api/v1/recordatorios` y `GET /api/v1/recordatorios/proximos` con atributos OpenAPI
- [x] 6.4 Tests funcionales: próximos, históricos, mes, día, filtros combinados, `scope` desconocido, aviso urgente, aviso no urgente y sin recordatorios cercanos

## 7. Temas

- [x] 7.1 `Api\TopicsController` (`GET /api/v1/topics`) con atributos OpenAPI
- [x] 7.2 Tests funcionales: orden por uso, tema sin uso y `total`

## 8. Documentación de la API

- [x] 8.1 `make openapi` y `doc/openapi.json` actualizado
- [x] 8.2 Las nueve peticiones en `doc/MyDiary.postman_collection.json` y en `doc/api.http`
- [x] 8.3 `tests/Doc/ApiDocumentationTest.php` en verde; ahora compara ignorando la query y los parámetros de ruta (`:fecha`, `{{fecha}}`)

## 9. Documentación del proyecto y cierre

- [x] 9.1 `Especificaciones.md` 3.7: endpoints de lectura, formas de respuesta, validación de parámetros y presenters
- [x] 9.2 `ROADMAP.md`: fase 3 hecha con el nombre del change y bloqueo 1 actualizado
- [x] 9.3 `CHANGELOG.md` bajo `## [Sin publicar]`
- [x] 9.4 `make cs-check`, `make phpstan` y `make test` en verde
