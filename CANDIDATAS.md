# Features candidatas (pendientes de proponer vía OpenSpec)

> Lista de ideas para futuras iteraciones. No implica compromiso de implementación — se van moviendo a `openspec change` una a una, según prioridad. Al proponer una, marcarla aquí como `[ ] → openspec change: <nombre>` o eliminarla si se descarta.

## Pendiente de fase 2 (ya decidido, solo falta abordarlo)

- [ ] **Sugerencia automática de recordatorios desde transcripciones** — detectar frases tipo "recuérdame que..." vía Ollama y proponer creación de `Reminder` automáticamente. Diferido explícitamente en el proposal de `recordatorios` hasta que esa base esté en producción.

## Candidatas nuevas

- [ ] **Exportación de datos** — descargar histórico (transcripciones + resúmenes) en Markdown/PDF por rango de fechas, como backup o para releer fuera de la app.
- [x] **Gestión de `topic`** → openspec change: `gestion-topics` — hoy se crean automáticamente sin forma de fusionar duplicados (p. ej. "trabajo" vs "curro") ni renombrarlos; con el tiempo ensucia el ranking de Estadísticas.
- [ ] **Notas de texto directas por Telegram** — permitir que un mensaje de texto (no solo audio) entre al Diario sin pasar por transcripción, para cuando no se puede grabar audio.
- [ ] **Etiquetado / marcar como importante** — contexto manual extra sobre una transcripción (tags o flag "importante") para resaltarla en Diario/Historial y que pese más en la búsqueda semántica.
- [ ] **Purga/retención de audios antiguos** — comando de limpieza para `audio_storage` en filesystem, que hoy crece indefinidamente (ya existe retención de logs a 60 días pero no de audios).
- [ ] **Health-check de dependencias externas** — endpoint o vista simple de estado para Ollama, Open WebUI y Redis, para diagnosticar antes de que falle un resumen diario.
- [ ] **Filtros en búsqueda semántica** — acotar por rango de fechas y/o tipo (solo transcripciones / solo resúmenes).
- [ ] **Recordatorios sincronizados con Google Calendar** — que los `Reminder` se creen en Google Calendar y que los eventos creados allí se recuperen en la app. Análisis previo (pendiente de decidir):
  - **Quién manda:** recomendada sincronización bidireccional manteniendo la tabla `reminder` (vistas, búsqueda `unaccent` y aviso de las 8:00 siguen leyendo de BD). App → Google al crear/editar/borrar (guardando `googleEventId`); Google → app con un comando programado cada 5-15 min usando `events.list` + `syncToken` (sin push `events.watch`). Descartado que Google sea la única fuente (rompe búsqueda y avisos).
  - **Autenticación:** cuenta de servicio con un calendario compartido con ella (sin OAuth ni tokens que caduquen) frente a OAuth 2.0 con la cuenta personal (ojo: en modo "Testing" el refresh token caduca a los 7 días). Hacer antes una prueba manual para comprobar si los eventos creados por la cuenta de servicio avisan en el móvil.
  - **Calendario:** preferible uno dedicado "MyDiary" en lugar del principal.
  - **Necesario:** `google/auth` + `symfony/http-client` (evitar `google/apiclient`), `GoogleCalendarClient` concreto sin interfaz, campos `googleEventId`/`googleEtag`/estado de sync en `Reminder`, estado del `syncToken`, reintentos desde el comando programado (no Messenger), secreto JSON fuera del repo, `MockHttpClient` en tests, logs con campos prefijados (`google_*`).
  - **Abierto:** eventos recurrentes de Google (importar instancias de solo lectura o ignorarlos), duración por defecto de los recordatorios con hora, conflictos de edición (gana el `updated` más reciente), pérdida de descripción/duración de eventos importados.

## Notas

- La adaptación a API para la app de iPhone tiene su propio plan por fases en `ROADMAP.md`.

- Ninguna de estas es urgente: respetar la regla de "introducir un patrón solo cuando el problema ya existe" (`Especificaciones.md`, sección 4).
- Cada una que se aborde sigue el flujo estándar del repo: `git flow feature start <nombre>` + `openspec change` antes de tocar código (ver `CLAUDE.md`).
