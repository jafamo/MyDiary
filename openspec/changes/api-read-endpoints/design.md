## Context

La fase 1 dejó los cálculos de Estadísticas, Historial, Recordatorios y Búsqueda en servicios que devuelven datos, no HTML. La fase 2 fijó las convenciones de la API (token, `snake_case`, fechas, paginación `page` / `per_page`, error único) y la regla de documentar cada endpoint. La fase 5 añadió `POST /api/v1/audios`, cuyo controlador serializa el audio con un método privado.

Los servicios actuales están hechos a la medida de las vistas Twig: devuelven rejillas por semanas (`MonthGrid`), tamaños de página fijos y claves pensadas para la plantilla. El aviso de la campana se calcula en un runtime de Twig (`ReminderRuntime`).

## Goals / Non-Goals

**Goals:**

- Que la app pueda leer todo lo que muestra la web, con los mismos filtros.
- Una sola forma JSON por recurso, igual en todos los endpoints.
- No duplicar cálculos: la API llama a los mismos servicios y repositorios que la web.

**Non-Goals:**

- Escritura (fase 4), descarga del audio (fase 6) y resumen bajo demanda (fase 7).
- Cambiar el comportamiento o el HTML de la web.
- Caché, ETag o campos seleccionables: con un solo usuario no hacen falta.

## Decisions

### 1. Presenters compartidos

Clases pequeñas en `src/Controller/Api/Presenter/`, servicios sin estado que devuelven arrays:

- `AudioPresenter`: `brief()` (`id`, `status`, `source`, `duration_seconds`, `received_at`; lo que ya devuelve la subida) y `detail()` (añade `error_code`, `error_message` y `transcription`: `id`, `content`, `edited_manually`, `model`, `processing_ms`, `created_at`, `updated_at`, o `null`).
- `SummaryPresenter`: `id`, `date`, `summary_text`, `generated_at`, `emoji_legend`, `topics` (`id`, `name`), `model`, `prompt_tokens`, `completion_tokens`, `generation_ms`.
- `ReminderPresenter`: `id`, `date`, `time` (`HH:MM` o `null`), `text`, `created_at`, `updated_at`.
- `TopicPresenter`: `id`, `name`.

Se descartó repetir métodos privados en cada controlador (la forma de un audio saldría copiada en diario, historial y búsqueda) y Symfony Serializer con grupos (reparte el contrato por las entidades, y casi todas las respuestas son agregados). `AudioController` pasa a usar `AudioPresenter::brief()` sin cambiar su respuesta.

Nunca se exponen `file_path`, `embedding`, `content_hash` ni los identificadores de Telegram.

### 2. Parámetros de consulta: `ApiQuery`

Clase `App\Controller\Api\ApiQuery` con métodos estáticos que leen y validan un parámetro y lanzan `ApiException::validationFailed` si es inválido: `status`, `day` (`AAAA-MM-DD`, fecha real), `month` (`AAAA-MM`), `page` y `perPage` (por defecto 20, máximo 100), y `choice` para enumerados. Un parámetro ausente toma su valor por defecto; uno presente e inválido es `422`.

La web ignora en silencio los filtros inválidos; la API no, porque un cliente programático debe enterarse de que envía algo mal. Es la única diferencia de comportamiento respecto a los filtros de la web.

Una página más allá de la última devuelve `items` vacío con el `total` real (la web la ajusta a la más cercana).

### 3. Calendarios como lista de días

`GET /historial?month=` devuelve `month`, `previous_month`, `next_month` y `days`: solo los días del mes con audios o resumen (`date`, `audio_count`, `has_summary`). `HistorialService::monthDays()` lo calcula con los mismos repositorios que `month()`, sin `MonthGrid`. La rejilla por semanas se queda en la web.

Para recordatorios no hay endpoint de calendario: `GET /recordatorios?month=` devuelve los recordatorios del mes y la app marca los días.

### 4. Recordatorios: un listado con filtros

`GET /recordatorios` responde siempre con la forma paginada (`items`, `page`, `per_page`, `total`). Filtros, excluyentes entre sí (`422` si se combinan):

- `scope=upcoming` (por defecto): de hoy en adelante, ascendente.
- `scope=history`: anteriores a hoy, descendente.
- `month=AAAA-MM`: los de ese mes, ascendente.
- `date=AAAA-MM-DD`: los de ese día.

`RecordatoriosService::page()` centraliza los cuatro casos. `ReminderRepository` gana `findPageInRange` (el total sale de `countByDateInRange`, que ya existía); los de `upcoming` e `history` ya existen. Los métodos que usa la web no cambian.

`GET /recordatorios/proximos` devuelve `count`, `level` (`urgent`, `upcoming` o `null`), `nearest_date` y `reminders` (los del día más cercano). El cálculo sale de `ReminderRuntime` a `RecordatoriosService::upcomingAlert()`; el runtime de Twig delega en él.

### 5. Resto de endpoints

- **Diario:** `date`, `entries` (audios `detail`), `summary` o `null`, `streak` (`current`, `best`), `week` (`total`, `delta`) y `top_topic` (`name`, `count`) o `null`.
- **Historial de un día:** `date`, `entries` y `summary` o `null`. La fecha va en la ruta con requisito `\d{4}-\d{2}-\d{2}`; si no es una fecha real, `422`. Un día sin nada responde `200` con listas vacías, no `404`.
- **Resúmenes:** paginado, descendente por fecha, cada elemento es el resumen más `audio_count`. `from` y `to` son opcionales pero van juntos. El listado sin rango usa `count([])` y `findBy()` del repositorio, sin métodos nuevos.
- **Búsqueda:** `query`, `results` (`type`, `distance`, `date`, `audio` o `null`, `summary` o `null`) y `reminders`. `q` vacío o ausente es `422`. Si falla el embedding, `results` va vacío y los recordatorios se buscan igual, como en la web.
- **Estadísticas:** `range`, `from`, `to`, las métricas de `overview()` tal cual (ya en `snake_case`, con `series` y `reminders_series`) y `ai_usage` con `totals` y `days`, convertidos a `snake_case` en el controlador. Sin `TokensChartBuilder`: la geometría del gráfico es de la web. `range` admite `15`, `30` (por defecto), `90`, `365` y `custom` (los presets del servicio); `custom` exige `from` y `to` válidos.
- **Temas:** `items` con `id`, `name`, `usage_count` y `last_used` (día o `null`), más `total`. Sin paginar: es un catálogo pequeño que la app necesita entero para renombrar y fusionar (fase 4).

### 6. Controladores

Uno por vista en `src/Controller/Api/`, planos, con el nombre de la ruta en castellano como el roadmap. Cada acción: leer parámetros con `ApiQuery`, llamar al servicio o repositorio, presentar. Los esquemas compartidos (`Audio`, `Transcription`, `Summary`, `Reminder`, `Topic`) se declaran una vez en `nelmio_api_doc.yaml` y las acciones los referencian.

## Risks / Trade-offs

- [Cambio grande: nueve endpoints en un solo change] → un commit por vista y tests funcionales por endpoint; los presenters tienen tests propios.
- [La forma JSON queda como contrato con la app] → se fija en la spec y en OpenAPI; el test de documentación falla si el esquema versionado no coincide con el código.
- [`GET /diario` hace varias consultas (rachas, semana, tema)] → las mismas que la vista web; aceptable con un usuario.
- [Diferencia con la web en filtros inválidos (`422` frente a ignorar)] → documentada en la spec y en `Especificaciones.md`.

## Migration Plan

Sin migraciones ni cambios de infraestructura: despliegue normal (`make deploy`).
