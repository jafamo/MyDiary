# Changelog

Formato inspirado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado según [SemVer](https://semver.org/lang/es/). Flujo de ramas y política de versiones/tags: [AGENTS.md](AGENTS.md).

## [Sin publicar]

### Añadido
- Campo `messenger_status` en los logs JSON del canal `messenger` (`received`, `sent`, `handled`, `no_handler`, `acknowledged`, `retry`, `failed`, `rejected`), para filtrar en Kibana por el estado de cada mensaje Messenger (`MessengerStatusProcessor`).
- Log por petición HTTP en el canal `http` con `status` (código HTTP numérico), `method`, `route`, `path` y `duration_ms`; `warning` para 4xx y `error` para 5xx (`HttpRequestLogListener`).
- Logs `audio_recording.received` (webhook de Telegram, con el resultado y el `status` devuelto) y `transcription.created` (worker).

### Cambiado
- En `prod`, los logs INFO de los canales `http`, `app` y `messenger` se escriben siempre (handler `structured` sin buffer), en vez de solo cuando hay un error en la misma petición o mensaje.

## [0.13.0] - 2026-09-24

### Ramas integradas en `develop`
- `feature/ci-quality-checks`
- `bugfix/sonar-timezone-constant`
- Commits directos sobre `develop`: corrección del mensaje de tag en la política de releases de `AGENTS.md`.

### Añadido
- **CI de calidad** (`.github/workflows/ci.yml`): en cada push a `main`/`develop` y en cada PR se ejecutan php-cs-fixer y PHPStan, después PHPUnit con cobertura contra un Postgres (pgvector) efímero, y por último SonarQube, que ahora recibe la cobertura (`sonar.php.coverage.reportPaths=coverage.xml`).
- **PHPStan** (nivel 5, extensiones Symfony y Doctrine, baseline con los errores previos) y target `make phpstan`.
- **GitHub Release automática** (`.github/workflows/release.yml`): al empujar una tag `X.Y.Z` se crea la release con la sección correspondiente del CHANGELOG; no hace nada si ya existe y falla si falta la sección.

### Cambiado
- `.github/workflows/sonarqube.yml` sustituido por el job `sonar` de `ci.yml`, que solo se ejecuta si pasan estilo, PHPStan y tests; `sonarqube-scan-action` sube de `v4` a `v8`.
- Zona horaria local centralizada en la constante `App\LocalTimezone::NAME` (`Europe/Madrid`), usada por `DateRange`, `AudioRecordingRepository`, `Schedule` y, vía el global de Twig `local_timezone`, por las plantillas; desaparecen los literales duplicados que marcaba Sonar. `Especificaciones.md` deja de listar `APP_TIMEZONE` como variable de entorno.

### Corregido
- Los tests ya no dependen de los valores reales de Telegram del `.env`: `.env.test` define `TELEGRAM_*` ficticios (el test del webhook fallaba con los valores de `.env.example`).
- Línea `.PHONY` del `Makefile`, que tenía texto basura al inicio y no declaraba los targets como phony.
- Política de tags en `AGENTS.md`: el git-flow instalado añade la versión al mensaje de `-m`, así que se usa `-m "Release"` para obtener `Release X.Y.Z`.

## [0.12.2] - 2026-09-24

### Ramas integradas en `develop`
- `feature/tag-policy`
- Commits directos sobre `develop`: instrucciones compartidas en `AGENTS.md`.

### Cambiado
- Las instrucciones del repositorio pasan de `CLAUDE.md` a `AGENTS.md`, compartidas con otros agentes; `CLAUDE.md` solo lo referencia.
- Política de versiones, tags y GitHub Releases documentada en `AGENTS.md`.
- Recuperadas en este CHANGELOG las entradas de `0.9.0` y `0.10.0`, que faltaban.

## [0.12.1] - 2026-09-24

### Ramas integradas en `develop`
- `feature/make-sonar`

### Añadido
- **`make sonar`**: análisis de SonarQube en local con la imagen Docker `sonarsource/sonar-scanner-cli`, sin instalar el scanner en el host. `SONAR_HOST_URL` por defecto `https://sonarqube.jfarinos.keenetic.pro` (sobrescribible); `SONAR_TOKEN` obligatorio desde el entorno, con error claro si falta.

## [0.12.0] - 2026-09-23

### Ramas integradas en `develop`
- `feature/ai-usage-metrics`

### Añadido
- **Consumo de IA por transcripción**: se guardan el tiempo de proceso de la llamada a Whisper que tuvo éxito (`transcription.processing_ms`) y el modelo (`transcription.model`). El mensaje de Telegram termina con `🎙️ 1:42 de audio · ⏱️ transcrito en 14 s`, y cada entrada del Diario/Historial muestra tiempo de proceso, velocidad (× tiempo real) y modelo. Open WebUI no devuelve tokens de Whisper.
- **Consumo de IA por resumen diario**: se guardan los tokens de entrada y salida que devuelve Ollama, el tiempo de generación y el modelo (`daily_summary.prompt_tokens`, `completion_tokens`, `generation_ms`, `model`). El mensaje de Telegram termina con `🧮 5 audios · 3.412 tokens (2.980 entrada + 432 salida) · ⏱️ 38 s · 🤖 <modelo>`, y el resumen en Diario y Resúmenes muestra las mismas métricas.
- **Estadísticas → Consumo IA**: tokens del rango (entrada/salida), media por resumen, audio transcrito, tiempo de proceso Whisper/Ollama, gráfico de barras apiladas de tokens por día y tabla por día ("Ver como tabla"). No le afecta el filtro de estado.
- Nueva variable `WHISPER_MODEL` (por defecto `whisper-1`): modelo que se envía a Open WebUI y que se registra en cada transcripción.

### Cambiado
- Los registros anteriores a esta versión no tienen métricas: se muestran como "—" y no cuentan en las medias.

### Migraciones
- `Version20260923161809`: añade las columnas de métricas (todas nullable) a `transcription` y `daily_summary`. **Requiere `make migrate` tras el despliegue** (`make deploy` no ejecuta migraciones).

### Despliegue
- Añadir `WHISPER_MODEL=whisper-1` al `.env` del servidor y reiniciar el worker (`docker compose restart diary-messenger-worker`) para que las transcripciones nuevas guarden métricas.

## [0.11.0] - 2026-09-23

### Ramas integradas en `develop`
- `feature/summary-prompt-file`
- Commits directos sobre `develop`: análisis estático con SonarQube en CI.

### Añadido
- **Resumen diario más descriptivo**: nuevo prompt de estilo (segunda persona, detalles concretos, orden cronológico, sin frases genéricas, temas concretos) en el fichero versionado `config/prompts/daily_summary.md`, leído en cada generación: editarlo cambia el siguiente resumen sin tocar PHP. El formato de salida lo fija el código y se impone con `response_format` `json_schema`. Si el fichero falta o está vacío, la generación falla con `PROMPT_NOT_FOUND`.
- **Emojis y leyenda**: cada párrafo del resumen empieza con un emoji elegido por el modelo, que devuelve también su leyenda (emoji → categoría). Se guarda en el nuevo campo `daily_summary.emoji_legend` y se muestra al final del mensaje de Telegram y bajo el resumen en Diario, Resúmenes y Búsqueda.
- El mensaje de Telegram del resumen incluye al final la línea de temas (`🏷️ Tema1 · Tema2`).
- Log `daily_summary.prompt_tokens` con los tokens de entrada de cada generación, para vigilar la ventana de contexto de Ollama (4096 tokens por defecto en el servidor).

### Cambiado
- `TelegramClient::sendMessage` divide los mensajes de más de 4000 caracteres en varios, cortando preferentemente entre párrafos.

### Migraciones
- `Version20260923154314`: añade `daily_summary.emoji_legend` (JSON, nullable). **Requiere `make migrate` tras el despliegue** (`make deploy` no ejecuta migraciones).

## [0.10.0] - 2026-09-21

### Ramas integradas en `develop`
- Commits directos sobre `develop`: gestión manual de temas (change OpenSpec `gestion-topics`).

### Añadido
- **Gestión de temas** (`/topics`, enlace en el menú): listado de temas ordenado por frecuencia de uso, **renombrado** con validación de duplicados sin distinguir mayúsculas y **fusión** de un tema en otro con pantalla de confirmación previa (`TopicMerger`). Así se pueden corregir duplicados creados automáticamente ("trabajo" vs "curro") que ensuciaban el ranking de Estadísticas.

## [0.9.0] - 2026-09-15

### Ramas integradas en `develop`
- `feature/resumen-telegram-cabecera-fecha`
- `feature/telegram-summary-late-regen`

### Añadido
- **Cabecera con fecha en el resumen de Telegram**: el mensaje antepone el día al que corresponde el resumen (p. ej. `📔 Resumen día: 22 de septiembre de 2026`).
- **Regeneración tardía del resumen diario**: nuevo comando `app:recheck-daily-summary`, lanzado por Symfony Scheduler cada 15 minutos entre las 21:00 y las 00:30. Si hay audios transcritos después de la última generación del resumen del día, lo regenera y lo vuelve a enviar por Telegram; si no, no hace nada.

## [0.8.0] - 2026-09-06

### Ramas integradas en `develop`
- `feature/add-semantic-search`
- `feature/audio-retry-transcription-command`
- Commits directos sobre `develop`: notificación del resumen por Telegram, aplanado de logs JSON, timeout de nginx, política de reinicio de contenedores y varios fixes de Filebeat/ELK.

### Añadido
- **Búsqueda semántica** (`/busqueda`): nueva vista para buscar en lenguaje natural sobre transcripciones y resúmenes diarios, con resultados combinados y ordenados por similitud coseno (`pgvector`) sobre embeddings generados con Ollama (`nomic-embed-text`). Los embeddings se generan/regeneran automáticamente al transcribir un audio, editar manualmente una transcripción, y generar/regenerar el resumen diario (sin bloquear esos flujos si Ollama falla). Comandos `bin/console app:transcription:backfill-embeddings` / `app:daily-summary:backfill-embeddings` (`make embeddings-backfill`) para regenerar embeddings faltantes del histórico.
  - **Cambio de infraestructura**: la imagen de `diary-postgres` pasa de `postgres:${POSTGRES_VERSION}` a `pgvector/pgvector:pg${POSTGRES_VERSION}` (incluye la extensión `pgvector`); nueva migración Doctrine para activarla y añadir las columnas `embedding`. Requiere tener el modelo `nomic-embed-text` descargado en el servidor de Ollama.
- Notificación del resumen diario por Telegram al generarse con éxito (antes solo se notificaba el fallo de generación).
- Comando `bin/console app:audio:retry-transcription [ids...]` para reencolar transcripciones atascadas en `PENDING` (por defecto, más de 15 minutos) o marcadas como `ERROR`, sin tener que hacer `UPDATE` manuales en la base de datos (`make audio-retry`).
- Logs de acceso de nginx en JSON estructurado (`status`, `request_uri`, `remote_addr`...) para poder filtrar por código HTTP en Kibana sin grok/dissect.
- `restart: unless-stopped` en todos los servicios `diary-*` de `docker-compose.yml`, para que un crash (p. ej. `diary-messenger-worker` perdiendo la conexión con Redis) no deje audios atascados indefinidamente hasta un reinicio manual.

### Corregido
- Monolog anidaba `context`/`extra` bajo esas claves en el JSON de log, por lo que Filebeat/Kibana no podían filtrar por campos como `error_code` como si fueran de primer nivel; ahora se aplanan a la raíz del log (`FlattenedContextJsonFormatter`).
- El timeout por defecto de nginx (60s) cortaba la generación bajo demanda del resumen diario antes de que Ollama respondiera (hasta 120s), devolviendo un 499 sin ningún log de aplicación asociado.
- Varios problemas de arranque/indexado de Filebeat detectados tras el despliegue de ELK en 0.7.0: conflicto de mapeo ECS con el campo `service` (renombrado a `log_service`), logging silencioso a fichero en vez de a stdout/stderr, rechazo por permisos estrictos del fichero de configuración montado, y versión de imagen desalineada con el stack ELK ya desplegado.

## [0.7.1] - 2026-08-17

### Corregido
- El webhook de Telegram dejaba de enviar el mensaje de confirmación ("Audio recibido ✅") sin avisar cuando fallaba la llamada a la API de Telegram, ya que ese envío ocurría sin try/catch después de haber persistido el audio y encolado la transcripción: el fallo tumbaba el webhook con un 500 aunque el audio se procesara igualmente. Ahora el fallo se loguea (`telegram.ack_send_failed`) y no interrumpe la respuesta del webhook.

## [0.7.0] - 2026-08-14

### Ramas integradas en `develop`
- `feature/observability-elk-logging`

### Añadido
- Envío de logs a Elasticsearch/Kibana (ya desplegados en el servidor): nuevo servicio `diary-filebeat` en `docker-compose.yml` que lee los logs de app, nginx, postgres y redis desde `${LOGS_PATH}` y los indexa en Elasticsearch.
- Retención de logs de la app PHP: Monolog rota diariamente y borra automáticamente pasados 60 días (`rotating_file`, `config/packages/monolog.yaml`); en producción ahora escribe a fichero en vez de a `stderr`.
- Límite de tamaño en los logs de todos los contenedores Docker (`json-file`, 50MB por servicio) para evitar crecimiento sin control en disco.
- Documentación del setup de logging/ELK en `Especificaciones.md` (sección 7.1).

## [0.6.0] - 2026-08-13

### Ramas integradas en `develop`
- `feature/reminder-time`

### Añadido
- **Recordatorios**: campo de hora opcional al crear/editar, visible en las cards, en el panel de la campana y en el aviso diario de Telegram (ordenado por hora, con los recordatorios sin hora al final).
- Versionado automático de assets estáticos (`app.css?v=<fecha de modificación>`, `App\Asset\FileModificationVersionStrategy`): cada despliegue fuerza a los navegadores a pedir la versión nueva de CSS/JS en vez de servir una copia cacheada.

### Corregido
- En móvil, tras un despliegue el navegador podía seguir usando una versión antigua de `app.css` cacheada (mismo nombre de fichero en cada release), haciendo que vistas nuevas como las cards de Recordatorios o el desplegable de la campana se vieran sin estilo, como bloques de texto apilados. Resuelto con el versionado automático de assets.

## [0.5.0] - 2026-08-13

### Ramas integradas en `develop`
- `feature/deploy-tooling`
- `feature/recordatorios`

### Añadido
- **Recordatorios**: nuevo menú (`/recordatorios`) para anotar eventos futuros (citas, compromisos, tareas con fecha), con calendario mensual navegable (mismo patrón que Historial), creación/edición/borrado desde la web, y varios recordatorios por día.
  - Listado paginado de **próximos recordatorios** (todas las fechas futuras, no solo el mes visible) y un **historial paginado** de recordatorios pasados (se derivan solo de la fecha, sin campo de estado nuevo), ambos presentados como cards y plegables en acordeón.
  - Stat-grid con total de recordatorios, recordatorios del mes en curso y cuenta atrás hasta el próximo.
  - **Icono de campana global**, visible en todas las páginas (barra lateral en escritorio, barra superior en móvil), con badge de recordatorios "próximos" (ventana de 5 días, con nivel de urgencia visual) y un panel desplegable con el/los recordatorio(s) más cercano(s) sin salir de la página.
  - **Aviso diario por Telegram** a las 08:00 (Europe/Madrid) con los recordatorios del día, si existen (`app:notify-reminders`, vía Symfony Scheduler).
  - **Estadísticas**: nuevo tile "Recordatorios en el rango" y una segunda línea (naranja) en el gráfico de audios por día con los recordatorios de cada día; la tabla accesible del gráfico ahora incluye esa columna y se ordena de más reciente a más antigua.
- `make deploy`: nuevo target que encadena `git pull origin main`, `cache:clear` y el reinicio de `diary-php`/`diary-messenger-worker` — evita el error de caché obsoleta en el worker tras un despliegue sin reiniciar los procesos de larga duración.

### Corregido
- El worker de Messenger podía fallar con `Failed to open stream` tras un `cache:clear` en producción si no se reiniciaba junto con `diary-php`; documentado y resuelto vía `make deploy`.

## [0.4.0] - 2026-08-13

### Ramas integradas en `develop`
- `feature/chores`
- `feature/estadisticas-insights`

### Añadido
- **Estadísticas**: total de audios del rango, racha de días consecutivos con audio (actual y mejor racha), día récord (fecha con más audios) y comparación porcentual de audios/día frente al periodo anterior equivalente; los cuatro indicadores respetan los filtros de rango y estado ya existentes.
- **Historial**: el calendario muestra ahora el número de audios de cada día junto al indicador de estado.
- `make cache-clear`: nuevo target para limpiar la caché de Symfony en el contenedor.

### Cambiado
- Ajustes de espaciado y jerarquía visual en las tarjetas de la vista Resúmenes.

## [0.3.0] - 2026-08-13

### Ramas integradas en `develop`
- `feature/add-summaries-menu` (PR [#1](https://github.com/jafamo/MyDiary/pull/1))
- Commits directos sobre `develop` (filtro de estado, tooling de desarrollo, fix de login)

### Añadido
- **Menú "Resúmenes"** (`/resumenes`): últimos 5 `DailySummary` por defecto, filtro por rango de fechas con paginación, etiquetas de tema por resumen, y el número de audios del día como enlace a Historial.
- **Filtro por estado** (`PENDING`/`TRANSCRIBED`/`ERROR`) en Diario, Historial y Estadísticas, combinable con el filtro de rango de fechas en Estadísticas.
- **Tooling de desarrollo**: comandos `make composer-install`, `make migrate`, `make migration-diff`, `make console`, `make test-coverage`; cobertura de tests con PCOV; diagramas Mermaid y contenido actualizado en el README.

### Corregido
- La redirección tras el login siempre lleva a Diario, en vez de a la URL objetivo almacenada en sesión (podía apuntar a una IP LAN inalcanzable si el usuario había accedido antes por IP en lugar del dominio).

## [0.2.0] - 2026-08-05

### Ramas integradas
- `release/0.2.0`
- `bugfix/telegram-audio-format-and-error-column` (mergeada a `develop` antes de la release)

### Añadido
- **Edición, borrado en cascada y reintento manual** de transcripciones desde Diario/Historial, con acciones inline compartidas entre ambas vistas.
- **Generación bajo demanda del resumen diario** desde un botón en Diario, sin esperar al disparo programado de las 21:00.

### Corregido
- Pipeline de transcripción de audios de Telegram: normalización de extensión `.oga` a `.ogg`, `Content-Type` explícito en la subida multipart a Whisper (vía Open WebUI), y ampliación de la columna `error_message` a `TEXT` para evitar caídas del worker con mensajes de error largos.

## [0.1.0] - 2026-08-05

### Ramas integradas
- `release/0.1.0`

### Añadido
- Infraestructura Docker Compose (servicios `diary-*`) y decisiones de arquitectura documentadas en `Especificaciones.md`.
- Bootstrap de Symfony + Doctrine: entidades `AudioRecording`, `Transcription`, `Topic`, `DailySummary`, `User` con migraciones; seguridad basada en `User` de base de datos gestionado solo por consola; PSR-12 vía PHP-CS-Fixer con hook de pre-commit.
- Suite de tests PHPUnit para entidades y comandos de usuario, con base de datos de test separada.
- Webhook de Telegram + pipeline asíncrono de transcripción (Symfony Messenger sobre Redis, reintentos con backoff, logging estructurado en JSON).
- Generación programada del resumen diario a las 21:00 (Europe/Madrid) vía Ollama.
- Primeras vistas web: login/logout, Diario (mini-dashboard con racha, tendencia semanal y tema del mes, log del día), Historial (calendario mensual), Estadísticas (filtro de rango sin JS, gráfico SVG interactivo).

## [0.0.1] - 2026-08-04

### Ramas integradas
- Trabajo inicial directo sobre `main`/`develop`, previo a la adopción formal de Git Flow (documentada en este mismo release).

### Añadido
- Especificación inicial del proyecto, `CLAUDE.md` y scaffolding de OpenSpec.
- Documentación de Git Flow como flujo de ramas fijo del repositorio.
- Cierre de huecos de especificación: whitelist de `chat_id` de Telegram en el webhook, políticas de error y reintento para transcripciones y resúmenes diarios.
- Flujo de reintento para audios con fallo técnico de procesamiento (`telegram_file_unique_id`, `error_code`/`error_message`, reintento automático al reenviar el mismo audio).
- README con visión general del stack (inglés y traducción al español).
