# Changelog

Formato inspirado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/). Versionado según [SemVer](https://semver.org/lang/es/). Flujo de ramas: [Git Flow](CLAUDE.md).

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
