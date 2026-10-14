## Why

La API ya permite leer, subir y escuchar audios, pero no modificar nada: corregir una transcripción, borrar un audio, reintentar uno fallido o gestionar recordatorios y temas solo se puede hacer desde la web. Es la fase 4 del `ROADMAP.md` y la siguiente en el orden recomendado (bloqueo 1: falta la escritura).

## What Changes

- `PATCH /api/v1/audios/{id}/transcripcion`: editar el texto de la transcripción (solo audios `TRANSCRIBED`; `409 conflict` en otro caso). Responde el audio con su transcripción.
- `DELETE /api/v1/audios/{id}`: eliminar el audio, su transcripción y sus ficheros (solo `TRANSCRIBED` o `ERROR`; `409` en otro caso). Responde `204`.
- `POST /api/v1/audios/{id}/reintentar`: relanzar la transcripción de un audio en `ERROR` (`409` en otro caso). Responde el audio en `PENDING`.
- `POST /api/v1/recordatorios` (`201`), `PATCH /api/v1/recordatorios/{id}` (parcial) y `DELETE /api/v1/recordatorios/{id}` (`204`), con las mismas reglas que la web: fecha y texto obligatorios, hora opcional.
- `PATCH /api/v1/topics/{id}`: renombrar un tema; nombre vacío = `422`, nombre ya usado por otro tema (sin distinguir mayúsculas) = `409`.
- `POST /api/v1/topics/fusionar`: fusionar uno o varios temas en otro, sin el paso de confirmación de la web. Responde el tema destino.
- La validación de los cuerpos JSON reutiliza los `FormType` de la web (sin CSRF en la API): campos obligatorios, longitud máxima, campos desconocidos y valores que el formulario no puede transformar se convierten al formato único `code` / `message` (`422 validation_failed`).
- El borrado de un audio sale del controlador web a un servicio compartido con la API, sin cambio de comportamiento en la web. La comprobación de tema duplicado ya vive en `TopicRepository` y la fusión en `TopicMerger`, así que la API los usa directamente.
- `TopicRenameType` declara `maxlength` 255 (la longitud de la columna): el navegador lo respeta en la web y la API lo valida; hoy un nombre más largo provoca un error de base de datos.
- Documentación de los endpoints: atributos OpenAPI, `doc/openapi.json`, colección Postman y `doc/api.http`.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `api`: nuevos requisitos de escritura sobre audios (editar transcripción, eliminar, reintentar), recordatorios (crear, editar, eliminar) y temas (renombrar, fusionar), y validación de cuerpos JSON.

## Impact

- **Código nuevo:** acciones en `src/Controller/Api/AudioController.php`, `RecordatoriosController.php` y `TopicsController.php`; helper de formularios para la API en `src/Controller/Api/`; servicio de borrado de audios en `src/Service/`.
- **Código modificado:** `TranscriptionController` (web) pasa a usar el servicio de borrado; `TopicRenameType` (`maxlength`).
- **Documentación:** `doc/openapi.json`, `doc/MyDiary.postman_collection.json`, `doc/api.http`, `Especificaciones.md` (3.7) y `ROADMAP.md` (fase 4).
- **Sin cambios:** base de datos, dependencias, infraestructura y campos de log. Sin migraciones ni pasos de despliegue especiales.
