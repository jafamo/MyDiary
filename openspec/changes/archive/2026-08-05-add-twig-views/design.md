## Context

Los bocetos de diseño (Login, Diario con mini-dashboard, Historial, Estadísticas) ya están aprobados por el usuario — son la referencia visual directa de este change, no hay que volver a diseñar, solo traducir a Twig + CSS/JS reales con datos reales. `symfony/security-bundle` ya está configurado con provider Doctrine (`App\Entity\User`) desde el change de bootstrap; falta el `form_login` y las plantillas. `symfony/twig-bundle` probablemente no está instalado (el skeleton mínimo no lo trae) — se confirma en la primera tarea.

## Goals / Non-Goals

**Goals:**
- Las 4 pantallas de los bocetos, con datos reales de BD, mobile-first (navegación inferior + desplegable de usuario en <760px, barra lateral en ≥760px).
- Mini-dashboard del Diario con datos reales: racha, actividad semanal + tendencia, tema del mes.
- Historial navegable por mes con calendario real.
- Estadísticas con filtro de rango de fechas real (15d/1m/3m/1a/personalizado) y el gráfico SVG interactivo con datos reales.
- Un único asset CSS y un único JS para Estadísticas, sin build step (sin Webpack Encore ni AssetMapper) — coherente con la filosofía de `CLAUDE.md` de no añadir infraestructura sin necesidad real; un solo fichero CSS y un JS pequeño no la justifican.

**Non-Goals:**
- No se implementa edición ni eliminación de transcripciones, ni el botón "Reintentar" funcional — son el siguiente paso (`Especificaciones.md` 3.4/3.4-bis). El estado `ERROR` se muestra en el Diario/Historial solo como información, sin acción.
- No se pagina el log de entradas ni el histórico — con el volumen esperado (uso personal) no hace falta todavía.
- No se optimiza el cálculo de estadísticas para grandes volúmenes (consultas simples, no vistas materializadas ni caché) — prematuro para un solo usuario.

## Decisions

### 1. `symfony/twig-bundle` + `symfony/asset`, sin AssetMapper ni Encore
Se confirma/instala `symfony/twig-bundle`. Los assets (`public/css/app.css`, `public/js/estadisticas.js`) se sirven directamente desde `public/`, referenciados con la función `asset()` de Twig (componente `symfony/asset`, ya trivial de tener disponible) para el versionado por defecto de Symfony — sin pipeline de build. Un solo fichero CSS con los tokens ya validados en los bocetos (custom properties, ambos temas) y un JS pequeño para el gráfico/interactividad de Estadísticas.

### 2. `form_login` con plantilla propia, sin registro
`config/packages/security.yaml`: firewall `main` añade `form_login` (`login_path: app_login`, `check_path: app_login`, csrf activado) y `logout` (`path: app_logout`). `access_control`: `^/login` y `^/telegram/webhook` públicos; el resto requiere `ROLE_USER`. `SecurityController::login()` solo renderiza el formulario y expone el último error (Symfony gestiona el POST internamente vía el firewall, no hace falta lógica propia de autenticación).

### 3. Layout base común con navegación responsive
`templates/base.html.twig` define el shell (sprite SVG incluido una vez, `<nav>` lateral para escritorio + barra superior/inferior para móvil, replicando exactamente la estructura de los bocetos) con bloques `title` y `content`. Las 3 vistas autenticadas extienden este layout; `security/login.html.twig` usa un layout mínimo aparte (sin navegación, como en el boceto).

### 4. Cálculo del mini-dashboard del Diario
Nuevo `AudioRecordingRepository::findDistinctDatesWithAudio(): list<string>` (fechas `Y-m-d` en `Europe/Madrid`, usando el mismo patrón de conversión a UTC de `App\Service\DateRange` ya existente, ordenadas desc). Un nuevo servicio `DiarioDashboardService` calcula en PHP (dataset pequeño, no hace falta SQL con funciones de ventana):
- **Racha actual**: recorre las fechas distintas desde la más reciente; si la más reciente es anterior a ayer, racha = 0; si no, cuenta días consecutivos hacia atrás.
- **Mejor racha**: recorre todas las fechas distintas (ascendente) buscando la racha consecutiva más larga.
- **Semana actual vs. anterior**: cuenta `AudioRecording` recibidos en la semana en curso (lunes-domingo, `Europe/Madrid`) y en la anterior; delta = actual − anterior.
- **Tema del mes**: `TopicRepository::findTopTopicForMonth(\DateTimeImmutable $month): ?array{name, count}` — join con `daily_summary_topic`/`daily_summary` filtrando por mes, `GROUP BY`/`ORDER BY COUNT DESC LIMIT 1` (sí tiene sentido en SQL, es agregación simple).

### 5. Historial: rejilla de calendario calculada en el controlador
`HistorialController` recibe `year`/`month` opcionales (por defecto el mes actual en `Europe/Madrid`) y `date` opcional (día seleccionado). Construye una rejilla de semanas completas (días de meses adyacentes incluidos pero marcados `muted`, igual que el boceto) en PHP puro (sin librería de calendario — `DateTimeImmutable` y aritmética de días bastan). Para pintar los puntos: `AudioRecordingRepository::countByDateInRange()` (agrupado por fecha) y `DailySummaryRepository::findDatesWithSummaryInRange()` (conjunto de fechas con resumen), ambos acotados al rango visible del calendario (no todo el histórico). El día seleccionado reutiliza el mismo partial `_partials/entry_log.html.twig` que el Diario, en modo solo lectura (sin mini-dashboard, sin panel de resumen si no existe ese día).

### 6. Estadísticas: rango de fechas vía query string, cálculo en el controlador
`EstadisticasController` acepta `?range=15|30|90|365|custom&from=&to=`. Resuelve el rango a `[from, to]` (`Europe/Madrid`), y calcula: serie diaria de audios (para el gráfico), medias/totales para los tiles, desglose de estado (`PENDING`/`TRANSCRIBED`/`ERROR`) y ranking de temas — todo acotado a ese rango vía las mismas convenciones de `DateRange`. Los datos de la serie temporal se serializan a JSON en el controlador con `json_encode(..., JSON_HEX_TAG)` (evita cualquier inyección si un nombre de tema contuviera secuencias tipo `</script>`) y se insertan en un `<script type="application/json">` que el JS del cliente parsea — el propio gráfico SVG interactivo (grid, área, hover con crosshair/tooltip, endpoint destacado, alternancia tabla/gráfico) es una adaptación directa del boceto ya aprobado, sustituyendo los datos sintéticos por los reales.

### 7. Filtros de fecha sin recarga de página, pero con fallback funcional sin JS
Los pills de rango y el selector personalizado actualizan la URL (`?range=...`) vía `history.pushState` + `fetch` del fragmento, **pero** cada pill es un `<a href="?range=...">` real — sin JavaScript, sigue funcionando como navegación normal (recarga completa). Evita depender de JS para una funcionalidad básica, coherente con progresividad razonable sin sobre-ingeniería (no se monta un framework SPA para esto).

## Risks / Trade-offs

- **[Riesgo] Cálculo de racha con huso horario**: mismo tipo de desfase ya encontrado en el change del resumen diario (UTC en runtime vs. `Europe/Madrid` conceptual) — se reutiliza deliberadamente `App\Service\DateRange` para no repetir el error.
- **[Trade-off] Sin caché de estadísticas**: cada carga de Estadísticas recalcula desde `AudioRecording`/`DailySummary` directamente. Aceptable para el volumen de un solo usuario; se revisita si en la práctica resulta lento.
- **[Riesgo] Gráfico SVG sin librería**: mismo enfoque que el boceto (JS vanilla generando `path`/`polyline`), sin Chart.js ni D3 — coherente con la filosofía del proyecto, pero significa mantener el código de dibujo a mano si se necesitan más tipos de gráfico en el futuro. Aceptable ahora (un solo gráfico de líneas/área).

## Migration Plan

No aplica (funcionalidad nueva). Pasos de puesta en marcha:
1. Confirmar/instalar `symfony/twig-bundle`.
2. Configurar `form_login`/`access_control` en `security.yaml`.
3. Construir `base.html.twig` + assets a partir de los bocetos aprobados.
4. Implementar los 3 controladores + servicios de cálculo (dashboard, calendario, estadísticas) con tests.
5. Verificación manual en navegador (`make up`, login real con el usuario `jfarinos` ya existente) en ancho de escritorio y móvil.

## Open Questions

Ninguna bloqueante.
