## 1. Preparación

- [x] 1.1 Confirmar/instalar `symfony/twig-bundle`
- [x] 1.2 Configurar `form_login` (`login_path`/`check_path: app_login`) y `logout` (`path: app_logout`) en `config/packages/security.yaml`; `access_control` para `^/login` y `^/telegram/webhook` públicos, resto `ROLE_USER`
- [x] 1.3 Crear `public/css/app.css` con los tokens de diseño (color claro/oscuro, tipografía, iconos) trasladados del boceto aprobado

## 2. Layout base y navegación

- [x] 2.1 `templates/_partials/svg_sprite.html.twig` (iconos del boceto)
- [x] 2.2 `templates/base.html.twig`: shell con barra lateral (≥760px) y barra superior + navegación inferior (<760px), bloques `title`/`body`
- [x] 2.3 Navegación activa resaltada inline en `base.html.twig` vía `app.request.attributes.get('_route')` (sin partial separado — suficientemente simple)

## 3. Login / logout

- [x] 3.1 `src/Controller/SecurityController.php` (`app_login`, renderiza último error de autenticación)
- [x] 3.2 `templates/security/login.html.twig` (layout mínimo, según boceto)
- [x] 3.3 Test funcional: redirección a login sin sesión, login correcto, login incorrecto, logout — 4 tests, `symfony/asset` no estaba instalado (necesario para la función `asset()` de Twig), añadido

## 4. Diario

- [x] 4.1 `AudioRecordingRepository::findDistinctDatesWithAudio(): list<string>` (+ `findAllReceivedOn`, `countReceivedBetween`, `countByDateInRange`, `countByStatusInRange` adelantados para las secciones 5-6)
- [x] 4.2 `TopicRepository::findTopTopicForMonth(\DateTimeImmutable $month): ?array{name, count}` (+ `findTopicFrequencyInRange` adelantado para la sección 6)
- [x] 4.3 `src/Service/DiarioDashboardService.php`: racha actual, mejor racha, total/tendencia semanal, tema del mes
- [x] 4.4 `src/Controller/DiarioController.php` (ruta `/`, `app_diario`)
- [x] 4.5 `templates/_partials/entry_log.html.twig` (reutilizable Diario/Historial)
- [x] 4.6 `templates/diario/index.html.twig` (mini-dashboard + log + panel de resumen). *(Añadido)* `src/Twig/SpanishDateExtension.php` para formatear fechas en castellano (`es_weekday_date`, `es_month_year`, `es_day_month`), reutilizado también por Historial
- [x] 4.7 Tests: `DiarioDashboardService` (racha con corte, racha continua, mejor racha, tendencia semanal) y del controlador (renderiza entradas del día con transcripción, estado vacío sin audios) — mismo hallazgo del lado inverso 1:1 que en changes anteriores, resuelto con `entityManager->clear()`

## 5. Historial

- [x] 5.1 `AudioRecordingRepository::countByDateInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): array<string,int>` (añadido en 4.1)
- [x] 5.2 `DailySummaryRepository::findDatesWithSummaryInRange(\DateTimeImmutable $from, \DateTimeImmutable $to): list<string>` (+ `countInRange` adelantado para la sección 6)
- [x] 5.3 `src/Controller/HistorialController.php` (`/historial`, `?year=&month=&date=`, construcción de la rejilla de semanas)
- [x] 5.4 `templates/historial/index.html.twig` (calendario + vista previa del día vía `entry_log.html.twig`)
- [x] 5.5 Tests: rejilla de calendario (días de meses adyacentes, marcado correcto de días con/sin resumen), selección de día

## 6. Estadísticas

- [x] 6.1 `AudioRecordingRepository`: serie diaria de audios en rango (`countByDateInRange`), desglose de estado en rango (`countByStatusInRange`), + `averageDurationInRange`
- [x] 6.2 `DailySummaryRepository`/`TopicRepository`: ranking de temas en rango (`findTopicFrequencyInRange`), `countInRange`
- [x] 6.3 `src/Controller/EstadisticasController.php` (`/estadisticas`, `?range=&from=&to=`, resuelve rango y calcula todo lo anterior)
- [x] 6.4 `templates/estadisticas/index.html.twig` (filtros como enlaces `<a href="?range=...">`, tiles, gráfico, barra de estado, ranking de temas)
- [x] 6.5 `public/js/estadisticas.js`: dibuja el gráfico SVG a partir del JSON embebido (grid, área, línea, endpoint destacado, crosshair/tooltip al hover, alternancia tabla/gráfico) — adaptado del boceto con datos reales
- [x] 6.6 Tests: cálculo de rango (presets y personalizado), serie diaria correcta, desglose de estado, ranking de temas

## 7. Verificación

- [x] 7.1 `make test` pasa en verde con todos los tests nuevos — 64 tests, 156 assertions
- [x] 7.2 `make cs-check` pasa sin errores sobre el código nuevo — 0/64 ficheros con violaciones
- [x] 7.3 Verificación manual (vía `curl` con sesión real, no se dispone de navegador gráfico en este entorno): login exitoso, `/`, `/historial`, `/estadisticas` responden 200 con contenido correcto (fecha en castellano "Martes, 4 de agosto" verificada contra el día de la semana real, mini-dashboard presente, JSON del gráfico presente, filtro de rango refleja la selección), CSS/JS sirven 200. Usuario de verificación desechable creado y eliminado tras la prueba
- [x] 7.4 Confirmar que el filtro de rango de Estadísticas funciona sin JavaScript — confirmado: los pills son `<a href="?range=...">` reales (probado con `curl`, sin ejecutar JS, `range=90` cambia correctamente el pill activo y los datos)
