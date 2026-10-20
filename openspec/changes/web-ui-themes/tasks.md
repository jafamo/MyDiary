## 0. Antes de empezar

- [x] 0.1 Confirmar con el usuario las decisiones D1, D2, D3 y D5 de `design.md`: columna, fuentes autoalojadas, mismo HTML y sin modo oscuro
- [ ] 0.1b Confirmar en la revisión D4, D6, D7, D8 y las preguntas abiertas
- [x] 0.2 `openspec validate web-ui-themes` en verde y crear la rama `git flow feature start web-ui-themes`

## 1. Modelo y preferencia de tema

- [x] 1.1 Enum `App\Entity\UiTheme` (`moderno`, `cuaderno`) con `default()`, `label()` e `icon()` (id del símbolo del sprite)
- [x] 1.2 Campo `uiTheme` en `User` (`enumType: UiTheme`, por defecto `Moderno`) con getter/setter
- [x] 1.3 Migración: `ALTER TABLE app_user ADD ui_theme VARCHAR(20) DEFAULT 'moderno' NOT NULL` (con `down()`)
- [x] 1.4 Test de la entidad y de la columna (`UserRepositoryTest`): usuario existente queda en `moderno`

## 2. Cambio de tema (backend)

- [x] 2.1 Test funcional `ThemePreferenceControllerTest`:
  - POST válido guarda la preferencia y redirige al `Referer` interno.
  - `Referer` externo redirige a Diario.
  - Tema desconocido no cambia nada.
  - Sin CSRF, error.
  - Sin sesión, redirige a `/login`.
  - Petición con `Accept: application/json` responde `204`.
- [x] 2.2 `ThemePreferenceController` (`POST /preferencias/tema`, CSRF `ui_theme`) con log `ui.theme_changed` y campo `ui_theme` en el canal `app`
- [x] 2.3 Función/variable Twig `ui_theme` (tema del usuario o el por defecto sin sesión) y `data-theme="{{ ui_theme }}"` en `<html>` de `base.html.twig` y `security/login.html.twig`
- [x] 2.4 Test: Diario y otra vista (p. ej. Estadísticas) renderizan `data-theme` según la preferencia

## 3. Fundamentos visuales

- [x] 3.1 Descargar y añadir a `public/fonts/` los woff2 (subconjunto latin, pesos de D2) con sus licencias OFL; `@font-face` en `public/css/fonts.css`
- [x] 3.2 Reorganizar `public/css/app.css`:
  - Tokens semánticos por tema en `:root[data-theme="moderno"]` y `:root[data-theme="cuaderno"]` (color, tipografía, radios, sombras).
  - Eliminar los bloques `prefers-color-scheme: dark` y `data-theme="dark"|"light"` y declarar `color-scheme: light` (D5).
  - Alias de los nombres actuales (`--panel`, `--accent`, …) para no romper las otras vistas.
- [x] 3.3 Añadir al sprite los iconos nuevos: `i-grid` (Moderno), `i-notebook` (Cuaderno), `i-play`, `i-pause`, `i-more`, `i-sparkle`
- [x] 3.4 Comprobar el contraste (≥4.5:1) de cada par texto/fondo de los dos temas y anotar los valores en `design.md`

## 4. Shell y navegación

- [x] 4.1 Barra lateral: siete destinos con icono y botón de usuario al pie con menú (sección «Tema» con `menuitemradio` y check, «Cerrar sesión») en el parcial `_partials/user_menu.html.twig`
- [x] 4.2 Barra superior móvil: marca, botón de tema con el nombre del otro tema (formulario POST) y campana con badge
- [x] 4.3 Barra inferior móvil de 5 ítems (Diario, Historial, Buscar, Avisos, Más) y panel «Más» con Resúmenes, Estadísticas, Temas, usuario y «Cerrar sesión»; «Más» activo cuando la ruta es una de ellas
- [x] 4.4 JS de menús en `base.html.twig`: reutilizar `.menu.is-open` para el menú de usuario y el panel «Más»; envío del tema con `fetch` y cambio de `data-theme` sin recargar
- [x] 4.5 Tests: barra inferior con 5 ítems, «Más» contiene los tres destinos, `aria-current` correcto en `/estadisticas`, menú de usuario con opción activa marcada

## 5. Vista Diario

- [x] 5.1 Test de `DiarioDashboardService::getDailyCounts(14)`: 14 elementos, ceros en días sin audio, último = hoy (Europe/Madrid)
- [x] 5.2 Implementar `getDailyCounts` y pasarlo desde `DiarioController` junto con la semana en curso para la tira de Cuaderno
- [x] 5.3 Parcial `_partials/activity_card.html.twig`: racha, mejor racha, tendencia, barras de 14 días con `role="img"`/`aria-label`, semana y tema del mes
- [x] 5.4 Parcial `_partials/week_strip.html.twig` (visible solo en Cuaderno por CSS): 7 días con enlace a `/historial?date=`, punto si hay audio y `aria-current="date"` en hoy
- [x] 5.5 Reordenar `diario/index.html.twig`: actividad → resumen (con «Regenerar resumen» al pie de la tarjeta) → filtro + log; en ≥760px, dos columnas (log | resumen + próximos recordatorios)
- [x] 5.6 `entry_log.html.twig`: un único marcado que el CSS pinta como tarjetas (Moderno) o línea de tiempo con marcador por estado (Cuaderno); mantener métricas, editar, eliminar y reintentar
- [x] 5.7 Reproductor propio en `_partials/audio_player.html.twig` + `public/js/audio-player.js` (play/pausa, progreso, tiempo) sobre `<audio preload="none">`, con `<audio controls>` nativo si no hay JS; usarlo también en Historial
- [x] 5.8 Actualizar `DiarioControllerTest`: orden de secciones, serie de 14 días, tira semanal y botón al pie del resumen; los escenarios existentes (filtro, métricas, generación) siguen verdes

## 6. Resto de vistas

- [x] 6.1 Revisar en los dos temas (con el sistema en modo claro y en modo oscuro) Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas, Temas y Login a 390 px y a 1280 px; corregir solo lo que se rompa por los tokens nuevos (sin rediseñar)
- [x] 6.2 Gráficos de Estadísticas: usar los tokens del tema para barras y líneas

## 7. Documentación y cierre

- [x] 7.1 `Especificaciones.md`: 3.5 Vistas (orden de Diario, temas, navegación móvil con «Más») y tabla `app_user` (`ui_theme`)
- [x] 7.2 CHANGELOG `## [Sin publicar]`: Añadido (temas Moderno/Cuaderno, selector, reproductor, barras de 14 días), Cambiado (Diario reordenado, barra inferior de 5 ítems) y Migraciones
- [x] 7.3 `make cs-check`, `make phpstan` y `make test` en verde
- [ ] 7.4 Sincronizar specs y archivar el cambio; presentar el resumen de revisión al usuario y esperar su confirmación antes de `git flow feature finish`
