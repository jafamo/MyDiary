## 1. Modelo de datos

- [x] 1.1 Crear entidad `Reminder` (`date`, `text`, `createdAt`, `updatedAt`)
- [x] 1.2 Generar y revisar la migración (`make migration-diff`)
- [x] 1.3 Crear `ReminderRepository` con `findAllOn(\DateTimeImmutable $date)`, `countByDateInRange(\DateTimeImmutable $from, \DateTimeImmutable $to)` (mismo vocabulario que `AudioRecordingRepository`) y `findUpcoming(\DateTimeImmutable $today, int $days = 5)` (recordatorios entre hoy y hoy+N días, ordenados por fecha)
- [x] 1.4 Añadir a `ReminderRepository` `countFromDate(\DateTimeImmutable $from)` y `findPageFromDate(\DateTimeImmutable $from, int $page, int $pageSize)` (recordatorios con fecha ≥ `$from`, orden ascendente, paginados) — mismo patrón que `DailySummaryRepository::findPageInRange`
- [x] 1.5 Añadir a `ReminderRepository` `countBeforeDate(\DateTimeImmutable $before)` y `findPageBeforeDate(\DateTimeImmutable $before, int $page, int $pageSize)` (recordatorios con fecha < `$before`, orden descendente, paginados) para el historial

## 2. Formulario y controlador web

- [x] 2.1 Crear `ReminderType` (FormType con campos `date` y `text`)
- [x] 2.2 Crear `RecordatoriosController` con acción de calendario mensual (`/recordatorios`), replicando la construcción de la rejilla de `HistorialController`
- [x] 2.3 Añadir acción de creación (`POST /recordatorios`)
- [x] 2.4 Añadir acción de edición (`POST /recordatorios/{reminder}/editar`), siguiendo el patrón de `TranscriptionController::edit`
- [x] 2.5 Añadir acción de borrado (`POST /recordatorios/{reminder}/eliminar`), con verificación CSRF como `TranscriptionController::delete`
- [x] 2.6 En `RecordatoriosController::index()`, calcular la página del listado de próximos recordatorios (parámetro `page`, independiente de `year`/`month` del calendario) y pasarla a la vista
- [x] 2.7 En `RecordatoriosController::index()`, calcular la página del historial (parámetro `history_page`, independiente de `page` y de `year`/`month`) y pasarla a la vista

## 3. Vista

- [x] 3.1 Crear `templates/recordatorios/index.html.twig`: calendario mensual, formulario de creación, listado del día seleccionado con edición/borrado inline
- [x] 3.2 Añadir "Recordatorios" a la navegación en `templates/base.html.twig` (barra lateral y barra inferior)
- [x] 3.3 Estilos en `public/css/app.css` reutilizando las clases `.calendar*` ya existentes de Historial
- [x] 3.4 Añadir icono `#i-bell` a `_partials/svg_sprite.html.twig`
- [x] 3.5 Crear `ReminderTwigExtension`/`RuntimeExtension` con la función Twig que expone recordatorios próximos (conteo + nivel de urgencia) a partir de `ReminderRepository::findUpcoming()`
- [x] 3.6 Añadir la campana (icono + badge) a `templates/base.html.twig`, junto a `.topbar__menu` (móvil) y `.sidebar__brand`/`.sidebar__nav` (escritorio), enlazando a `/recordatorios`
- [x] 3.7 Estilos de la campana en `app.css`: badge numérico y variante "urgente" (hoy/mañana) vs. "próximo" (2-5 días)
- [x] 3.8 Añadir sección "Próximos recordatorios" en `recordatorios/index.html.twig`: listado paginado (todas las fechas futuras, no solo el mes visible), reutilizando `.pagination`/`.entry-log` ya existentes
- [x] 3.9 Añadir sección "Historial de recordatorios" en `recordatorios/index.html.twig`: listado paginado de recordatorios pasados, orden descendente, con su propia paginación (`history_page`)
- [x] 3.10 Convertir "Próximos recordatorios" e "Historial" en `<details>`/`<summary>` (acordeón nativo, sin JS nuevo): "Próximos" abierto por defecto, "Historial" cerrado
- [x] 3.11 Rediseñar el listado de recordatorios como cards (`.reminder-grid`/`.reminder-card`) en un partial reutilizable (`_partials/reminder_cards.html.twig`), usado en día seleccionado, próximos e historial
- [x] 3.12 Mostrar el contador `(N)` junto a los títulos "Próximos recordatorios" e "Historial de recordatorios"
- [x] 3.13 Añadir stat-grid en Recordatorios: total de recordatorios, recordatorios este mes, próximo recordatorio (cuenta atrás en días)
- [x] 3.14 Convertir la campana global en un desplegable (`.reminder-menu`/`.reminder-dropdown`, reutilizando y generalizando el JS de toggle del menú de usuario) que muestra el/los recordatorio(s) más próximo(s) (agrupados por fecha) y enlaza a "Ver todos"
- [x] 3.15 Estadísticas: segunda línea (naranja, discontinua) de recordatorios por día en el gráfico existente, con leyenda y tooltip combinado; nuevo stat-tile "Recordatorios en el rango"
- [x] 3.16 Estadísticas: la tabla del gráfico ("Ver como tabla") se ordena de más reciente a más antigua (el gráfico en sí mantiene el orden cronológico)

## 4. Notificación por Telegram

- [x] 4.1 Crear `NotifyRemindersCommand` (`app:notify-reminders`): consulta recordatorios de hoy, si hay alguno construye y envía un mensaje vía `TelegramClient`
- [x] 4.2 Añadir entrada en `src/Schedule.php` (cron `0 8 * * *`, timezone `Europe/Madrid`)

## 5. Tests

- [x] 5.1 Test de entidad/repositorio: varios recordatorios el mismo día, `findAllOn`, `countByDateInRange`
- [x] 5.2 Test de controlador: crear, editar, eliminar recordatorio (funcional, como `TranscriptionControllerTest`)
- [x] 5.3 Test de controlador: calendario marca correctamente los días con recordatorios y navega entre meses
- [x] 5.4 Test de validación: formulario sin fecha o sin texto no crea/modifica nada
- [x] 5.5 Test de `NotifyRemindersCommand`: envía mensaje cuando hay recordatorios hoy, no envía nada cuando no hay
- [x] 5.6 Test de `ReminderRepository::findUpcoming()`: incluye recordatorios entre hoy y hoy+5, excluye los de más de 5 días y los ya pasados
- [x] 5.7 Test de la función Twig/badge: cuenta correcta, sin badge cuando no hay recordatorios próximos, nivel "urgente" cuando el más cercano es hoy o mañana
- [x] 5.8 Test de `findPageFromDate`/`countFromDate`: excluye pasados, ordena ascendente, pagina correctamente
- [x] 5.9 Test de controlador: el listado paginado muestra recordatorios de meses distintos al calendario visible, y pagina
- [x] 5.10 Test de `findPageBeforeDate`/`countBeforeDate`: excluye futuros, ordena descendente, pagina correctamente
- [x] 5.11 Test de controlador: un recordatorio con fecha pasada aparece en el historial y no en "próximos"; el historial pagina de forma independiente

## 6. Cierre

- [x] 6.1 `make cs-fix` y `make test` en verde
- [x] 6.2 Revisar visualmente la vista Recordatorios (crear, editar, eliminar, navegación de meses, responsive móvil/escritorio)
- [ ] 6.3 Verificar en producción que el aviso de las 08:00 llega correctamente tras el siguiente despliegue (`make deploy`)
