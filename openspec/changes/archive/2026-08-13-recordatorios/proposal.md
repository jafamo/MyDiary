## Why

Hoy la aplicación solo registra lo que ya ha pasado (audios, transcripciones, resúmenes). No hay forma de anotar algo futuro — una cita médica, un compromiso, una tarea con fecha — y tenerlo presente en un calendario, con aviso el día que toca. Encaja con la infraestructura ya existente (Symfony Scheduler, bot de Telegram, patrón de calendario de Historial) sin necesitar piezas nuevas de arquitectura.

Este cambio cubre la base editable manual. La creación automática de recordatorios a partir de transcripciones de audio (detectando frases como "recuérdame que...") queda deliberadamente fuera: es un cambio más complejo (requiere clasificación + extracción de fecha relativa vía Ollama) que se abordará en una propuesta futura independiente, una vez esta base esté en producción.

## What Changes

- Nueva entidad `Reminder`: fecha (no única — puede haber varios recordatorios el mismo día), texto, timestamps.
- Nuevo menú **Recordatorios** (`/recordatorios`): calendario mensual navegable (mismo patrón que Historial) marcando los días con recordatorios; al seleccionar un día se listan sus recordatorios con edición y borrado inline; formulario para crear uno nuevo.
- **Listado paginado de próximos recordatorios**: todos los recordatorios futuros (no solo los del mes visible en el calendario), ordenados por fecha ascendente, paginados — para poder ver de un vistazo qué viene después sin navegar mes a mes.
- **Historial paginado de recordatorios pasados**: los recordatorios cuya fecha ya pasó no se borran ni cambian de estado — simplemente dejan de aparecer como "próximos" y pasan a un listado de historial (orden descendente, más reciente primero), paginado, para poder consultarlos más adelante.
- Nueva notificación por Telegram: cada mañana (08:00 Europe/Madrid) el sistema envía un mensaje con los recordatorios del día, si los hay. Reutiliza `Symfony Scheduler` + `TelegramClient` ya existentes, igual que el resumen diario de las 21:00.
- Navegación: se añade "Recordatorios" a la barra lateral/inferior, junto a Diario, Historial, Resúmenes, Estadísticas.
- **Icono de campana global** (todas las páginas, no solo Recordatorios): muestra el número de recordatorios "próximos" (dentro de una ventana fija de 5 días antes de su fecha, incluyendo el mismo día) con un badge, y al pulsarla despliega un panel con el/los recordatorio(s) más cercanos (agrupados por fecha) y un enlace "Ver todos" a `/recordatorios`. Se vuelve visualmente más urgente (color) cuando el recordatorio más cercano es para hoy o mañana. Discreto por defecto — no es un elemento intrusivo, solo un indicador junto a la navegación.
- **Diseño visual**: los listados de recordatorios (día seleccionado, próximos, historial) se presentan como cards en rejilla, no como filas de texto plano; "Próximos" e "Historial" son acordeones nativos (`<details>`/`<summary>`, "Próximos" abierto por defecto); un stat-grid en la parte superior de Recordatorios resume el total, los del mes en curso y el próximo con cuenta atrás en días.
- **Estadísticas**: el gráfico de audios por día incorpora una segunda línea (color distinto) con los recordatorios por día del mismo rango, con su leyenda; nuevo tile "Recordatorios en el rango"; la tabla accesible del gráfico se lista de más reciente a más antigua.

## Capabilities

### New Capabilities
- `reminders`: creación, edición y borrado de recordatorios desde la web, vista de calendario mensual, y notificación diaria por Telegram de los recordatorios del día.

### Modified Capabilities
- `web-views`: el requirement "Navegación responsive" se amplía para incluir el nuevo destino "Recordatorios" en ambas barras (escritorio y móvil), y el icono de campana global presente en todas las páginas.
- `data-model`: se añade la entidad Doctrine `Reminder` y su migración versionada.

## Impact

- `src/Entity/Reminder.php`, `src/Repository/ReminderRepository.php`, migración nueva.
- `src/Form/ReminderType.php` (crear/editar).
- `src/Controller/RecordatoriosController.php` (calendario + listado del día seleccionado, siguiendo el patrón de `HistorialController`) y un controlador o acciones para crear/editar/eliminar (siguiendo el patrón de `TranscriptionController`).
- `src/Command/NotifyRemindersCommand.php` (`app:notify-reminders`) + entrada nueva en `src/Schedule.php`.
- `templates/recordatorios/index.html.twig`, ajustes en `templates/base.html.twig` (nav + campana global) y `public/css/app.css`.
- Nueva función Twig (extensión) para exponer el contador/urgencia de recordatorios próximos en `base.html.twig` sin tener que inyectarlo desde cada controlador existente.
- Tests: entidad, repositorio, controlador (CRUD + calendario), comando de notificación.
- **Fuera de alcance de este cambio**: sugerencia automática de recordatorios a partir de transcripciones vía Ollama (propuesta futura independiente, una vez esta base esté en producción).
