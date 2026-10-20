## Why

La interfaz web tiene una sola apariencia (papel cálido + teal, títulos monospace). En móvil la barra inferior muestra 7 destinos en 390 px y las etiquetas no caben. En Diario, lo que más interesa (el resumen del día y la actividad) queda al final, por debajo del log. Con los mockups v2 (lienzo «MyDiary — propuestas visuales», artboards `DiarioTemas` y `DiarioTemasEscritorio`) el usuario ha elegido una nueva disposición para Diario. También quiere dos temas visuales que pueda cambiar desde la propia aplicación: **Moderno** y **Cuaderno**.

## What Changes

- **Dos temas seleccionables**:
  - **Moderno**: tarjetas, letra Plus Jakarta Sans con JetBrains Mono para cifras y paleta cian.
  - **Cuaderno**: línea de tiempo, letra Newsreader + Roboto y paleta papel/marrón tinta con violeta.
  - Tipografías y colores salen de la librería ui-ux-pro-max (perfiles «Friendly SaaS», «News Editorial», «Healthcare/Calm» y «Diary & Journal»).
- **Preferencia de tema persistida por usuario.** Se aplica en todas las páginas desde el primer render, sin parpadeo. Por defecto: Moderno.
- **Selector de tema**:
  - En escritorio, dentro del menú desplegable del nombre de usuario (sección «Tema» con «Moderno» / «Cuaderno», icono y check en el activo).
  - En móvil, un botón en la barra superior con el nombre del otro tema (como en el mockup). El usuario y «Cerrar sesión» pasan al panel «Más».
  - Cambiarlo no recarga datos ni pierde la página actual.
- **Vista Diario reordenada**:
  - Arriba, la actividad: racha (con la mejor racha), barras de audios por día de los últimos 14 días, audios de la semana con tendencia y tema del mes.
  - Después, el resumen del día con el botón «Regenerar resumen» al pie de su tarjeta.
  - Después, el filtro por estado y el log de audios.
  - En escritorio, el log y el resumen van en dos columnas, y la columna derecha añade los próximos recordatorios.
- **Tema Cuaderno**:
  - Tira con los 7 días de la semana en curso; cada día enlaza a Historial (`/historial?date=…`).
  - El log se muestra como línea de tiempo, con el estado codificado en el punto de cada entrada.
- **Reproductor de audio propio**: botón play/pausa con progreso, sobre el `<audio preload="none">` existente.
- **Navegación móvil con 5 destinos**: Diario, Historial, Buscar, Avisos (Recordatorios, con su badge) y «Más». «Más» abre un panel con Resúmenes, Estadísticas y Temas. **BREAKING (visual)**: la barra inferior deja de listar todos los destinos.
- **BREAKING (visual)**: se elimina el modo oscuro automático por `prefers-color-scheme`. La interfaz usa siempre el tema elegido, y los dos son claros.
- Las demás vistas (Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas, Temas, Login) heredan colores y tipografías del tema activo, pero **no** se rediseñan en este cambio.

## Capabilities

### New Capabilities
- `ui-themes`: catálogo de temas (Moderno, Cuaderno), preferencia por usuario, selector en el menú de usuario y aplicación del tema a todas las vistas.

### Modified Capabilities
- `web-views`: «Navegación responsive» (5 destinos + «Más» en móvil; menú de usuario con selector de tema) y «Vista Diario con mini-dashboard» (orden actividad → resumen → log, barras de 14 días, mejor racha, columnas en escritorio, línea de tiempo y tira semanal en Cuaderno). «Botón de generación de resumen bajo demanda» cambia de posición (pie de la tarjeta del resumen) sin cambiar su comportamiento. «Reproductor de audio en el log» pasa a tener controles propios.
- `data-model`: nueva columna de preferencia de tema en `app_user` (según la opción elegida en design.md, D1).

## Impact

- **Plantillas**: `templates/base.html.twig` (atributo `data-theme`, menú de usuario, barra inferior, panel «Más», carga de fuentes), `templates/diario/index.html.twig`, `templates/_partials/entry_log.html.twig` y nuevos parciales (`activity_card`, `theme_menu`, `week_strip`, `audio_player`).
- **CSS**: `public/css/app.css` se reorganiza en tokens por tema (`:root[data-theme="moderno"]`, `:root[data-theme="cuaderno"]`) más componentes. Nuevos ficheros de fuentes en `public/fonts/` (si se elige autoalojarlas, D2).
- **JS**: `public/js/diario.js` (reproductor) y JS de menú en `base.html.twig` (selector de tema).
- **Backend**:
  - Campo de preferencia en `User` + migración.
  - Ruta POST `/preferencias/tema` con CSRF.
  - El servicio de Diario devuelve la serie de 14 días y la mejor racha (la mejor racha ya se calcula).
- **Sin cambios en la API `/api/v1`**: el tema es solo de la web.
- **Docs**: `Especificaciones.md` (3.5 Vistas y modelo `app_user`), CHANGELOG.
