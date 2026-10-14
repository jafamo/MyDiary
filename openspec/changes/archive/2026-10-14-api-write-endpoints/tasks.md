## 1. Piezas compartidas

- [x] 1.1 `ApiException::conflict()` y `ApiForm` en `src/Controller/Api/` (cuerpo JSON → formulario sin CSRF: claves desconocidas, obligatorios, `maxlength`, escalares a cadena, `submit` parcial en `PATCH`, campos no sincronizados → `422`)
- [x] 1.2 Tests de `ApiForm` (cuerpo no JSON, campo desconocido, obligatorio ausente/en blanco/`null`, `PATCH` parcial, valor no transformable, `maxlength`)
- [x] 1.3 `AudioRecordingDeleter` en `src/Service/` (`canDelete`, `delete`) y `TranscriptionController::delete` (web) pasa a usarlo; tests unitarios y los funcionales de la web siguen en verde
- [x] 1.4 `TopicRenameType`: `attr.maxlength` 255

## 2. Audios

- [x] 2.1 `PATCH /api/v1/audios/{id}/transcripcion` (`TranscriptionEditor`, `409` si no está `TRANSCRIBED`) con atributos OpenAPI
- [x] 2.2 `DELETE /api/v1/audios/{id}` (`AudioRecordingDeleter`, `409` si está `PENDING`, `204`) con atributos OpenAPI
- [x] 2.3 `POST /api/v1/audios/{id}/reintentar` (`retryAfterError`, `409` si no está en `ERROR`) con atributos OpenAPI
- [x] 2.4 Tests funcionales de los tres endpoints: caso correcto, `409`, `422`, `404` y sin token

## 3. Recordatorios

- [x] 3.1 `POST /api/v1/recordatorios`, `PATCH` y `DELETE /api/v1/recordatorios/{id}` con `ReminderType` y atributos OpenAPI
- [x] 3.2 Tests funcionales: crear (`201`), sin texto y fecha imposible (`422`), edición parcial, quitar la hora, eliminar (`204`), `404` y sin token

## 4. Temas

- [x] 4.1 `PATCH /api/v1/topics/{id}` con `TopicRenameType` (duplicado → `409`) y `POST /api/v1/topics/fusionar` con `TopicMergeType` y `TopicMerger`, con atributos OpenAPI
- [x] 4.2 Tests funcionales: renombrado, duplicado sin distinguir mayúsculas, nombre en blanco y demasiado largo, fusión correcta, origen igual al destino e id inexistente

## 5. Documentación y verificación

- [x] 5.1 Regenerar `doc/openapi.json` (`make openapi`) y añadir las peticiones a `doc/MyDiary.postman_collection.json` y `doc/api.http`
- [x] 5.2 Actualizar `Especificaciones.md` (3.7) y `ROADMAP.md` (fase 4 hecha, bloqueo 1 resuelto)
- [x] 5.3 `make cs-check`, `make phpstan` y `make test` en verde
