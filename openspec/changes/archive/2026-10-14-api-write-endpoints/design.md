## Context

La web ya hace todas estas escrituras con controladores Symfony y `FormType` (`TranscriptionEditType`, `ReminderType`, `TopicRenameType`, `TopicMergeType`), protegidas por CSRF. La API (`/api/v1`) tiene un firewall sin estado, errores únicos con `ApiException` y presenters por recurso, pero solo endpoints de lectura, subida y descarga.

El proyecto **no tiene `symfony/validator`**: los formularios no aplican constraints, y `isValid()` no detecta ni campos desconocidos ni valores que no se pueden transformar (eso lo añade la extensión de validación). Las reglas actuales de la web son las de cada `FormType` (campos `required`, transformación de fechas, horas e ids) más comprobaciones a mano en los controladores (`'' === trim(...)`). Además, los setters de las entidades no admiten `null` (`setDate`, `setText`, `setName`, `setContent`): enviar `null` a un campo obligatorio rompe en el mapeo con un error de tipo, antes de cualquier validación.

## Goals / Non-Goals

**Goals:**
- Las escrituras de la web, también desde la API, con las mismas reglas.
- Un único punto que convierte un cuerpo JSON en un formulario enviado y traduce sus fallos a `422 validation_failed`.

**Non-Goals:**
- Añadir `symfony/validator` ni constraints a las entidades.
- Cambiar el comportamiento de la web (salvo `maxlength` en el renombrado de temas).
- Confirmación previa de la fusión en la API: la app pedirá confirmación en su interfaz.
- Edición masiva o borrado de varios recursos en una petición.

## Decisions

- **Opción A elegida por el usuario: reutilizar los `FormType` de la web.** Alternativas descartadas: DTOs con `#[MapRequestPayload]` (duplican las reglas y necesitan el Validator) y validación a mano en cada acción.
- **`App\Controller\Api\ApiForm`** (servicio, junto a `ApiQuery`), con `submit(string $type, ?object $data, Request $request, bool $partial = false): FormInterface`:
  1. Decodifica el cuerpo; si no es un objeto JSON, `400 bad_request`.
  2. Crea el formulario con `csrf_protection: false`.
  3. Rechaza claves que no son campos del formulario (`422`, nombrando el campo).
  4. Campos `required`: ausentes (salvo en `PATCH`), `null`, cadena en blanco o lista vacía → `422` «El campo "x" es obligatorio». Se comprueba **antes** de enviar el formulario, de modo que nunca llega `null` a un setter que no lo admite.
  5. `attr.maxlength` del campo: más caracteres → `422`. Así, la longitud se declara una vez en el `FormType` y vale para el navegador y la API.
  6. Convierte los escalares a cadena (los ids de `EntityType` llegan como enteros en JSON, y los formularios trabajan con cadenas como en un envío HTTP) y llama a `submit($payload, !$partial)`; en `PATCH`, `clearMissing = false` conserva los campos ausentes.
  7. Cualquier campo no sincronizado (fecha imposible, hora inválida, id inexistente, tipo inesperado) → `422` «El campo "x" no es válido».

  Los mensajes se construyen con el nombre del campo y no con los textos por defecto de Symfony, que están en inglés.
- **Borrado de audios en `App\Service\AudioRecordingDeleter`**, con `canDelete(AudioRecording): bool` (`TRANSCRIBED` o `ERROR`) y `delete(AudioRecording)` (ficheros de audio y export si existen, transcripción y audio). Lo usan `TranscriptionController::delete` y la API. Alternativa descartada: método en `AudioRecordingService`, que ya concentra la recepción y el reintento y crecería con una responsabilidad distinta.
- **Temas sin servicio nuevo:** la comprobación de duplicado ya es `TopicRepository::findOneByNameCaseInsensitive()` y la fusión `TopicMerger::merge()`. Un servicio que solo envuelva esas llamadas sería una capa sin motivo. En la API, el duplicado es `409 conflict` (en la web sigue siendo `422` con el mensaje en la página). La fusión quita el destino de los orígenes, como la web.
- **Rutas y códigos de estado:** el audio es el recurso principal (`/audios/{id}/transcripcion`, como la web usa `/transcripcion/{audioRecording}`). `POST` de creación → `201`, `PATCH` y acciones → `200` con el recurso desde su presenter (`AudioPresenter::detail`, `ReminderPresenter`, `TopicPresenter`), `DELETE` → `204`. Estado incompatible → `409` con un nuevo `ApiException::conflict()`.
- **Logs:** sin campos ni eventos nuevos; `HttpRequestLogListener` ya registra ruta y estado de cada petición.

## Risks / Trade-offs

- [Las reglas de `required`/`maxlength` en `ApiForm` imitan lo que haría el Validator] → Si algún día se añade `symfony/validator`, `ApiForm` podrá delegar en él sin cambiar el contrato de la API.
- [Si `ApiForm` rechaza en el paso 3 o 4, el formulario no llega a enviarse; si falla en el paso 7, la entidad gestionada puede quedar modificada en memoria] → No se hace `flush` tras un error y la petición termina, así que nada se guarda.
- [Fusión sin confirmación desde la API] → Es irreversible; la documentación OpenAPI lo indica para que la app confirme antes.

## Migration Plan

Sin migraciones, dependencias ni cambios de infraestructura. Despliegue normal; rollback revirtiendo el merge.
