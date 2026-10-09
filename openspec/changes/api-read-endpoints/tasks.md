## 1. Base común

- [ ] 1.1 Presenters en `src/Controller/Api/Presenter/`: `AudioPresenter` (`brief` / `detail`), `SummaryPresenter`, `ReminderPresenter` y `TopicPresenter`, con tests unitarios (incluido que no salen `file_path`, `content_hash` ni identificadores de Telegram)
- [ ] 1.2 `AudioController` usa `AudioPresenter::brief()` sin cambiar su respuesta
- [ ] 1.3 `ApiQuery`: `status`, `day`, `month`, `page`, `perPage` y `choice`, con `422` en valores inválidos; tests unitarios
- [ ] 1.4 `ApiException::notFound()` y esquemas compartidos (`Audio`, `Transcription`, `Summary`, `Reminder`, `Topic`) en `nelmio_api_doc.yaml`
- [ ] 1.5 Ayudas de test en `ApiTestCase` para crear audios, transcripciones, resúmenes y recordatorios y limpiarlos

## 2. Diario e historial

- [ ] 2.1 `Api\DiarioController` (`GET /api/v1/diario`) con atributos OpenAPI
- [ ] 2.2 `HistorialService::monthDays()` (días del mes con audios o resumen) con test
- [ ] 2.3 `Api\HistorialController`: `GET /api/v1/historial` y `GET /api/v1/historial/{fecha}` con atributos OpenAPI
- [ ] 2.4 Tests funcionales: sin token, día vacío, audios con y sin transcripción, filtro `status`, estado inválido, mes con actividad, meses contiguos, mes inválido, día concreto, día sin nada y fecha imposible

## 3. Resúmenes

- [ ] 3.1 `DailySummaryRepository::countAll()` y `findPage()` con tests
- [ ] 3.2 `Api\ResumenesController` (`GET /api/v1/resumenes`) con `audio_count` y atributos OpenAPI
- [ ] 3.3 Tests funcionales: paginación, página más allá de la última, `per_page` fuera de rango, rango de fechas, rango incompleto e invertido

## 4. Búsqueda

- [ ] 4.1 `Api\BusquedaController` (`GET /api/v1/busqueda`) con atributos OpenAPI
- [ ] 4.2 Tests funcionales: resultados de los dos tipos ordenados por distancia, recordatorios por texto, consulta vacía y fallo del embedding

## 5. Estadísticas

- [ ] 5.1 `Api\EstadisticasController` (`GET /api/v1/estadisticas`): validación de `range`, métricas y `ai_usage` en `snake_case`, con atributos OpenAPI
- [ ] 5.2 Tests funcionales: rango por defecto, preset, rango personalizado, `custom` incompleto, `range` desconocido, filtro `status` y claves de `ai_usage`

## 6. Recordatorios

- [ ] 6.1 `ReminderRepository::countInRange()` y `findPageInRange()` con tests
- [ ] 6.2 `RecordatoriosService::page()` (scope, mes o día, con tamaño de página) y `upcomingAlert()`; `ReminderRuntime` delega en el servicio; tests del servicio y los de la campana web sin modificar
- [ ] 6.3 `Api\RecordatoriosController`: `GET /api/v1/recordatorios` y `GET /api/v1/recordatorios/proximos` con atributos OpenAPI
- [ ] 6.4 Tests funcionales: próximos, históricos, mes, día, filtros combinados, `scope` desconocido, aviso urgente, aviso no urgente y sin recordatorios cercanos

## 7. Temas

- [ ] 7.1 `Api\TopicsController` (`GET /api/v1/topics`) con atributos OpenAPI
- [ ] 7.2 Tests funcionales: orden por uso, tema sin uso y `total`

## 8. Documentación de la API

- [ ] 8.1 `make openapi` y `doc/openapi.json` actualizado
- [ ] 8.2 Las nueve peticiones en `doc/MyDiary.postman_collection.json` y en `doc/api.http`
- [ ] 8.3 `tests/Doc/ApiDocumentationTest.php` en verde

## 9. Documentación del proyecto y cierre

- [ ] 9.1 `Especificaciones.md` 3.7: endpoints de lectura, formas de respuesta, validación de parámetros y presenters
- [ ] 9.2 `ROADMAP.md`: fase 3 hecha con el nombre del change y bloqueo 1 actualizado
- [ ] 9.3 `CHANGELOG.md` bajo `## [Sin publicar]`
- [ ] 9.4 `make cs-check`, `make phpstan` y `make test` en verde
