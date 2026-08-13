## Context

El proyecto ya tiene todas las piezas que necesita este cambio: `Symfony Scheduler` con caché stateful para tareas diarias (`src/Schedule.php`), `TelegramClient::sendMessage()` para notificar, y un patrón de calendario mensual navegable ya implementado en `HistorialController`/`historial/index.html.twig` que se puede replicar. No hace falta ninguna pieza de infraestructura nueva.

## Goals / Non-Goals

**Goals:**
- CRUD completo de recordatorios (crear/editar/eliminar) desde la web, con validación mínima (fecha y texto obligatorios).
- Calendario mensual propio en `/recordatorios`, coherente visualmente con Historial.
- Aviso por Telegram una vez al día con los recordatorios de esa fecha, si existen.
- Icono de campana con badge, visible en todas las páginas (no solo Recordatorios), que muestra cuántos recordatorios están "próximos" y enlaza a `/recordatorios`.

**Non-Goals:**
- Recurrencia (recordatorios que se repiten) — cada recordatorio es una fila con una fecha concreta; si algo se repite, se crea uno nuevo cada vez.
- Creación de recordatorios desde Telegram (por texto o por voz) — el webhook de Telegram sigue interpretando únicamente audio.
- Sugerencia/creación automática de recordatorios a partir de transcripciones vía Ollama — propuesta futura independiente.
- Múltiples avisos por Telegram por recordatorio (recordatorio con antelación de X días) — un único aviso por Telegram, el mismo día. La campana web sí anticipa (ver más abajo), pero eso no dispara mensajes adicionales de Telegram.
- Antelación configurable por recordatorio — la ventana de 5 días es fija para todos, no un campo del formulario.
- Marcar recordatorios como "vistos"/descartar la campana — el badge siempre refleja el estado real de los recordatorios próximos; no hay estado de lectura por ahora.

## Decisions

- **Entidad `Reminder`**: `id`, `date` (tipo `date`, **sin** constraint unique — puede haber varios recordatorios el mismo día, a diferencia de `DailySummary`), `text` (string, contenido libre), `createdAt`, `updatedAt`. Sin campo de recurrencia ni de "notificado" — la notificación se calcula en el momento por fecha, igual que `DailySummaryService::generateForDate()` calcula por fecha sin marcar banderas de estado.
- **Hora del aviso diario**: 08:00 Europe/Madrid, vía una entrada nueva en `RecurringMessage::cron()` en `src/Schedule.php`, apuntando a un nuevo comando `app:notify-reminders`. Se separa deliberadamente de las 21:00 del resumen diario (ese es un cierre del día; esto es un aviso de mañana). Alternativa descartada: reutilizar el mismo comando/horario que `app:generate-daily-summary` — mezclaría dos responsabilidades distintas (cierre vs. aviso) en un mismo punto de fallo.
- **Sin aviso si no hay recordatorios ese día**: igual que el resumen diario no genera `DailySummary` si no hay transcripciones, `app:notify-reminders` no envía mensaje a Telegram si `ReminderRepository::findAllOn($today)` devuelve vacío — evita ruido diario.
- **Reutilización del patrón de calendario de Historial**: `RecordatoriosController` replica la construcción de la rejilla mensual (`$gridStart`/`$gridEnd`, semanas de 7 días) ya usada en `HistorialController`, marcando los días con `ReminderRepository::countByDateInRange()` (mismo nombre/forma que el método ya existente en `AudioRecordingRepository`, para mantener el vocabulario del repo consistente). No se extrae un trait/servicio compartido para esta rejilla — solo hay dos usos hasta ahora, no justifica la abstracción todavía (regla del CLAUDE.md de no anticipar patrones).
- **Edición inline, sin página de detalle separada**: igual que Diario/Historial resuelven la edición de transcripciones con un formulario inline y redirección al `Referer`, la edición/borrado de un recordatorio sigue el mismo patrón (`TranscriptionController::edit`/`delete`) en vez de crear una vista de "detalle de recordatorio" nueva.
- **Ruta de creación**: formulario siempre visible en `/recordatorios` (fecha + texto), en vez de un modal o página aparte — coherente con "no EasyAdmin, controladores + FormType simples".
- **Ventana de visibilidad de la campana**: fija, `hoy ≤ date ≤ hoy + 5 días`. Un recordatorio "entra" en la campana 5 días antes de su fecha y sale de ella al día siguiente de la fecha (deja de estar "próximo"). No se contempla aquí el caso de un recordatorio con fecha pasada que nunca se borró — se queda simplemente fuera de la ventana, sin aviso ni distinción especial; si en la práctica resulta molesto tener recordatorios "fantasma" sin gestionar, se revisita en una iteración futura.
- **Cálculo sin nuevo estado en BD**: la ventana y la urgencia se calculan al vuelo con una query (`date BETWEEN hoy AND hoy+5`), igual que el resto de agregados de la app (Estadísticas). No se añade un campo "visto"/"notificado" a `Reminder` — evita gestionar estado de lectura, que no se ha pedido.
- **Niveles de urgencia visual**: dos niveles, no una escala continua, para no complicar el CSS/lógica: `urgent` (fecha = hoy o mañana) y `upcoming` (2 a 5 días vista). El badge cambia de color entre ambos; sin recordatorios en la ventana, la campana no muestra badge.
- **Cómo llega el dato a `base.html.twig`**: se expone como función Twig (p. ej. `upcoming_reminders()`) respaldada por un `Twig\Extension\RuntimeExtensionInterface` que inyecta `ReminderRepository`, en vez de pasar la variable desde cada controlador (`DiarioController`, `HistorialController`, `SummariesController`, `EstadisticasController`, `RecordatoriosController`...). Alternativa descartada: añadir el dato al contexto de cada controlador — significaría tocar 5 controladores existentes por una necesidad puramente de layout, y arrastrar el riesgo de que un controlador nuevo futuro se olvide de añadirlo.
- **Ubicación en el layout**: en vez de un botón de campana separado, se usa el icono `#i-bell` (nuevo en el sprite SVG) como icono del propio ítem "Recordatorios" ya añadido a la navegación (`.sidebar__nav` en escritorio, `.bottom-nav` en móvil, ambas siempre visibles), con el badge numérico colgado del mismo enlace. Evita duplicar un segundo punto de entrada a `/recordatorios` en la misma pantalla.

## Risks / Trade-offs

- [Varios recordatorios el mismo día sin límite] → Aceptable para un único usuario; si en la práctica se vuelve ruidoso, se puede ajustar la UI de listado sin cambios de modelo.
- [El aviso de las 08:00 depende de que el worker/scheduler esté corriendo esa hora exacta] → Ya cubierto por `stateful($this->cache)` + `processOnlyLastMissedRun(true)` en `Schedule.php`, mismo mecanismo que ya protege el resumen de las 21:00 ante caídas cortas del contenedor.
- [Fecha del recordatorio en el pasado] → No se bloquea explícitamente (puede ser útil como registro retroactivo); no se envía notificación para fechas pasadas porque el comando solo consulta "hoy".
