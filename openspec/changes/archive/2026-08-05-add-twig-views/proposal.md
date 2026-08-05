## Why

Es el paso 5 del orden de construcción de `Especificaciones.md` (sección 9): la primera vez que hay algo visible en el navegador. Sin esto, todo lo implementado hasta ahora (webhook, transcripción, resumen diario) solo se puede verificar por consola o directamente en la base de datos.

## What Changes

- Añadir `SecurityController` con formulario de login (Symfony Security `form_login`, CSRF) y logout, usando el provider Doctrine/`User` ya configurado (sin registro ni recuperación de contraseña, según restricción de `CLAUDE.md`).
- Añadir un layout base (`base.html.twig`) con la navegación aprobada en los bocetos de diseño: barra lateral vertical en escritorio (≥760px), barra superior + navegación inferior fija en móvil (por debajo de 760px) — mobile-first, ya que el uso principal previsto es desde el móvil.
- Añadir `DiarioController` (ruta raíz tras login): vista del día actual con:
  - Mini-dashboard: racha de días consecutivos con audio, total y tendencia de la semana en curso, tema más frecuente del mes.
  - Log cronológico de `AudioRecording`/`Transcription` del día, con estados `PENDING`/`TRANSCRIBED`/`ERROR` (incluyendo `error_message` descriptivo, sin botón de acción todavía — el botón "Reintentar" es funcionalidad de escritura, se implementa en el siguiente change).
  - Panel de resumen diario (`DailySummary` + `Topic`) cuando existe para el día.
- Añadir `HistorialController`: calendario mensual navegable (mes anterior/siguiente) marcando días con `DailySummary` vs. días con audios sin resumen, y vista previa del día seleccionado (mismo log de entradas que Diario, en modo solo lectura).
- Añadir `EstadisticasController`: tiles de métricas, gráfico de audios por día (SVG + hover), barra de estado de transcripciones, ranking de temas — todo filtrable por rango de fechas (15 días / 1 mes / 3 meses / 1 año / personalizado), calculado sobre datos reales.
- Extraer los tokens de diseño (color, tipografía, iconos SVG) validados en los bocetos a un asset CSS/JS del proyecto (`public/`), reutilizado por las 3 vistas + login.

## Capabilities

### New Capabilities
- `web-views`: las 4 pantallas (login/logout, Diario, Historial, Estadísticas) con navegación responsive, y el layout base común.

### Modified Capabilities
(ninguna — no cambia el comportamiento de `data-model`, `telegram-audio-pipeline`, `daily-summary-generation`, `docker-infrastructure`, `symfony-application-bootstrap`, `code-style-enforcement` ni `automated-testing`, solo se construye sobre ellas)

## Impact

- Ficheros nuevos: `src/Controller/SecurityController.php`, `src/Controller/DiarioController.php`, `src/Controller/HistorialController.php`, `src/Controller/EstadisticasController.php`, `templates/base.html.twig`, `templates/security/login.html.twig`, `templates/diario/index.html.twig`, `templates/historial/index.html.twig`, `templates/estadisticas/index.html.twig`, `templates/_partials/entry_log.html.twig`, `public/css/app.css`, `public/js/estadisticas.js`, tests correspondientes.
- Ficheros modificados: `config/packages/security.yaml` (rutas de login/logout, `access_control`), `config/routes/security.yaml` (si aplica).
- No incluye: edición/eliminación de transcripciones, botón "Reintentar" funcional (paso 6, siguiente change). Los bocetos ya aprobados por el usuario son la referencia visual directa de este change.
- Instalar `symfony/twig-bundle` si no está ya presente (confirmar durante la implementación — el skeleton mínimo no lo incluye por defecto).
