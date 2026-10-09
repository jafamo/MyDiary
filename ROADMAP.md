# Roadmap — API para la app de iPhone

> Plan para exponer la aplicación como API JSON **manteniendo la web actual**. No implica compromiso de fechas. Cada fase es un `openspec change` propio con su rama Git Flow (ver `AGENTS.md`); al abordarla, marcarla aquí con el nombre del change. Las ideas sueltas siguen en `CANDIDATAS.md`.

## Objetivo y alcance

- **Objetivo:** que una app nativa de iPhone pueda hacer lo mismo que la web (consultar, editar, buscar) y además **grabar y subir audios sin pasar por Telegram**.
- **La web no se toca funcionalmente:** las vistas Twig, el login por formulario y el bot de Telegram siguen funcionando igual. La API es una segunda entrada a los mismos servicios.
- **Un solo usuario.** Multiusuario queda fuera (ver «Fuera de alcance»). Única concesión al futuro: los endpoints toman el usuario del token y nunca de un parámetro, para que añadir dueño a los datos más adelante no cambie el contrato de la API.
- **Fuera de este documento:** la app de iPhone en sí (repositorio y roadmap propios).

## Bloqueos de partida

| # | Bloqueo | Dónde | Fase |
|---|---|---|---|
| 1 | No hay API: todo devuelve Twig, con sesión + formulario + CSRF | `src/Controller/*`, `config/packages/security.yaml` | 2, 3, 4 |
| 2 | Lógica de presentación y cálculo dentro de controladores | `EstadisticasController` (rachas, comparativas, consumo IA), `RecordatoriosController` (calendario, paginación), `SearchController` (fusión de resultados), `HistorialController` | 1 |
| 3 | ~~La captura solo entra por Telegram~~ (resuelto) | `AudioRecording.telegramMessageId` / `telegramFileUniqueId` obligatorios y únicos; `AudioRecordingService::receive()` | 5 |
| 4 | No se puede escuchar el audio: ninguna ruta sirve los ficheros | — | 6 |
| 5 | El resumen bajo demanda es síncrono (hasta 3 intentos contra Ollama dentro de la petición; nginx corta a 130 s) | `DailySummaryController`, `DailySummaryService::generateForDate()` | 7 |
| 6 | Los avisos solo salen por Telegram | `TelegramClient::sendMessage()` llamado desde `DailySummaryService`, `TranscribeAudioMessageHandler`, `TranscriptionFailureListener`, `NotifyRemindersCommand` | 8 |

## Decisiones de diseño abiertas

Se deciden al proponer la fase correspondiente (regla «Opciones de diseño antes de implementar» de `AGENTS.md`). Se indica la opción recomendada.

### D1 — Autenticación de la API (fase 2) — decidida: opción A

| Opción | Pros | Contras |
|---|---|---|
| **A. Token opaco propio** (recomendada): tabla `api_token` con el hash del token, autenticador `access_token` nativo de Symfony. La app lo obtiene con `POST /api/v1/login` (usuario + contraseña) y lo guarda en el llavero | Sin dependencias nuevas; revocable por dispositivo; se puede listar/revocar por consola (`app:user:*`) | Una consulta a BD por petición (irrelevante con un usuario) |
| B. JWT (`lexik/jwt-authentication-bundle`) | Sin estado en BD | Dependencia nueva, claves que custodiar, hace falta refresh token y no se puede revocar sin lista negra |
| C. Reutilizar la sesión con cookie | Cero código de autenticación | Obliga a gestionar CSRF y cookies desde la app; frágil |

### D2 — Estilo de la API (fase 2) — decidida: opción A, con Swagger

| Opción | Pros | Contras |
|---|---|---|
| **A. Controladores planos + DTO de salida** (recomendada), bajo `src/Controller/Api/` | Coherente con «sin CRUDs genéricos»; las respuestas (diario, estadísticas) son agregados, no entidades | Serialización escrita a mano |
| B. API Platform | CRUD, OpenAPI y paginación gratis | Dependencia grande; modela recursos, y aquí casi todo son vistas agregadas; choca con las restricciones de arquitectura |

### D3 — Duración de un audio subido desde la app (fase 5) — decidida: opción B

| Opción | Pros | Contras |
|---|---|---|
| **A. La envía la app** (recomendada) y el servidor la valida como entero razonable | Igual que hoy con Telegram (`duration` viene dado); sin dependencias | Se confía en el cliente |
| B. Se calcula en servidor con `ffprobe` | Dato fiable | Añade `ffmpeg` a la imagen PHP |

### D4 — Resumen bajo demanda (fase 7)

| Opción | Pros | Contras |
|---|---|---|
| **A. Asíncrono por Messenger** (recomendada): el endpoint responde `202` y la app consulta el estado | Sin timeouts; misma cola y worker que ya existen | **Amplía la restricción** «Messenger solo para Telegram → transcripción»: hay que aceptarlo y actualizar `AGENTS.md` y `Especificaciones.md` |
| B. Mantenerlo síncrono también en la API | Sin cambios de arquitectura | La app espera hasta 2 min con la conexión abierta; en móvil se corta con facilidad |

### D5 — Canal de notificaciones push (fase 8)

| Opción | Pros | Contras |
|---|---|---|
| **A. APNs directo** (recomendada): HTTP/2 con `symfony/http-client` y clave `.p8` | Sin Firebase ni SDK; solo hay iPhone | Solo sirve para Apple |
| B. Firebase Cloud Messaging | Serviría también para Android | Cuenta y SDK de Firebase en la app, dependencia de un tercero |

En ambos casos hace falta la cuenta de pago de Apple Developer.

## Fases

> Estado: fases 1 y 2 finalizadas (publicadas en la release `0.17.0`) y fase 5 finalizada (en `develop`). Fase 0 pendiente. Las finalizadas llevan ✅ en el título.

### Fase 0 — Endurecer lo que ya está expuesto

Independiente de la API, pero conviene antes de abrir más superficie.

- Mover el secreto del webhook de la URL (`/telegram/webhook/{token}`) a la cabecera `X-Telegram-Bot-Api-Secret-Token`: hoy el token queda escrito en el access log de nginx (`request_uri`) y llega a Elasticsearch. Requiere volver a registrar el webhook (`app:telegram:set-webhook`) en el despliegue.
- Activar `login_throttling` en el firewall `main` (añade `symfony/rate-limiter`).

### Fase 1 — Sacar la lógica de los controladores ✅

> Hecha en el change `extract-controller-logic` (rama `feature/extract-controller-logic`): `EstadisticasService`, `HistorialService`, `RecordatoriosService`, `SearchService`, `MonthGrid` y `TokensChartBuilder`.

Refactor sin cambio funcional: los controladores web quedan en «leer petición → llamar servicio → renderizar». Es el requisito para que la API no duplique cálculos.

- Estadísticas: series, rachas, día récord, comparativa con el periodo anterior y consumo IA a un servicio (hoy métodos privados de `EstadisticasController`).
- Recordatorios: rejilla del calendario y paginación de próximos/históricos.
- Búsqueda: embedding de la consulta + fusión de transcripciones y resúmenes.
- Historial: rejilla del mes y entradas del día.
- Los servicios devuelven arrays/objetos de datos, no HTML ni JSON ya codificado (p. ej. `series_json` se codifica en el controlador web).
- Criterio de hecho: los tests funcionales actuales pasan sin modificarse; se añaden tests unitarios de los servicios extraídos.

### Fase 2 — Base de la API ✅

> Hecha en el change `api-base` (rama `feature/api-base`). D1: token opaco con caducidad de 90 días por inactividad. D2: controladores planos documentados con Swagger (`nelmio/api-doc-bundle`), más colecciones Postman y `.http` en `doc/`.

- Firewall `api` sin estado para `^/api`, declarado antes de `main`, con la autenticación de D1. Sin CSRF.
- `POST /api/v1/login`, `POST /api/v1/logout` (revoca el token) y `GET /api/v1/me`. Límite de intentos en el login.
- Convenciones comunes: prefijo `/api/v1`, fechas en ISO 8601 (instantes en UTC; los «días» como `AAAA-MM-DD` en `LocalTimezone`), paginación `page`/`per_page`, y un único formato de error JSON (`code`, `message`) para 400/401/403/404/409/422.
- Logs: `HttpRequestLogListener` ya registra `route` y `status`; los campos nuevos, planos y con prefijo `api_` (p. ej. `api_token_id`). No loguear nunca el token.
- Tests funcionales de autenticación (sin token, token inválido, token revocado).

### Fase 3 — Endpoints de lectura

Equivalentes a las vistas actuales, reutilizando los servicios de la fase 1:

| Endpoint | Equivale a |
|---|---|
| `GET /api/v1/diario` | Diario (entradas de hoy, resumen, racha, semana, tema del mes) |
| `GET /api/v1/historial?month=` y `GET /api/v1/historial/{fecha}` | Calendario y día de Historial |
| `GET /api/v1/resumenes` | Resúmenes |
| `GET /api/v1/busqueda?q=` | Búsqueda semántica + recordatorios |
| `GET /api/v1/estadisticas?range=&status=` | Estadísticas y Consumo IA |
| `GET /api/v1/recordatorios` y `GET /api/v1/recordatorios/proximos` | Recordatorios y campana |
| `GET /api/v1/topics` | Temas |

Todos aceptan los mismos filtros que la web (estado, rango, mes).

### Fase 4 — Endpoints de escritura

- Transcripción: editar (`PATCH`) y eliminar (`DELETE`), sobre `TranscriptionEditor` y el borrado en cascada existente.
- Audio: reintentar (`POST .../reintentar`), solo en estado `ERROR` (409 en otro caso, como hoy).
- Recordatorios: crear, editar y eliminar, validando con las mismas reglas que `ReminderType`.
- Temas: renombrar y fusionar, sobre `TopicMerger`.

### Fase 5 — Captura de audio sin Telegram ✅

> Hecha en el change `api-audio-upload` (rama `feature/api-audio-upload`). D3: duración con `ffprobe` en servidor, detrás de `AudioProbeInterface`. Formatos `m4a`, `mp3`, `ogg` y `wav` validados por contenido, 25 MB como máximo. Los audios de la app avisan por Telegram igual que los demás.

El cambio de modelo de datos del roadmap.

- Migración en `audio_recording`: `telegram_message_id` y `telegram_file_unique_id` pasan a opcionales (siguen siendo únicos cuando existen); nuevo campo `source` (`telegram` / `app`, los existentes se rellenan con `telegram`); nuevo `content_hash` (SHA-256 del fichero, único) para deduplicar subidas repetidas.
- `POST /api/v1/audios` (multipart): guarda el fichero, crea el `AudioRecording` en `PENDING` y despacha `TranscribeAudioMessage`. Respuestas alineadas con `AudioRecordingReceiveResult`: creado, duplicado, o reintento si el existente está en `ERROR`.
- `AudioRecordingService` gana un método de entrada para subidas; el de Telegram no cambia de comportamiento.
- Límites de subida: hoy nginx admite 1 MB por petición (valor por defecto de `client_max_body_size`) y PHP 2 MB (`upload_max_filesize`); hay que subirlos en `docker/nginx/default.conf` y en la imagen PHP, y validar tipo y tamaño en el endpoint. Requiere recrear `diary-nginx` en el despliegue.
- Formato: el iPhone graba en `.m4a` (AAC); comprobar en la propuesta que Whisper vía Open WebUI lo acepta igual que el `.ogg` de Telegram.
- Mensajes de Telegram: decidir en la propuesta si un audio con `source = app` sigue enviando «resumen de la transcripción» por Telegram o solo los de origen Telegram.
- `Especificaciones.md`: secciones 3.1 y 6.

### Fase 6 — Escuchar los audios

- `GET /api/v1/audios/{id}/fichero`: descarga autenticada con soporte de `Range` (reproducción con salto).
- Opcional en el mismo cambio: reproductor en Diario/Historial de la web usando una ruta equivalente con sesión.

### Fase 7 — Resumen bajo demanda sin bloquear

Según D4. Con la opción A:

- Mensaje y handler nuevos que llaman a `DailySummaryService::generateForDate($date, waitForPending: false)`.
- `POST /api/v1/diario/resumen` responde `202`; el estado se consulta en `GET /api/v1/diario`. Hace falta guardar «generación en curso» y «última generación fallida» para poder mostrarlo.
- El botón de la web puede pasar a usar el mismo mecanismo o quedarse síncrono; decidirlo en la propuesta.

### Fase 8 — Notificaciones push

Depende de que exista la app y la cuenta de Apple Developer.

- Registro de dispositivos: `POST` / `DELETE /api/v1/dispositivos` (token de APNs asociado al token de API).
- Aquí aparece el segundo canal real, así que es el momento de introducir un notificador con dos implementaciones (Telegram y push) en lugar de llamar a `TelegramClient` desde cuatro sitios. Antes de esta fase no se introduce la interfaz.
- Avisos a cubrir: transcripción lista, transcripción fallida, resumen del día, resumen fallido y recordatorios de las 8:00.
- Telegram sigue activo; decidir si se duplican avisos o se elige canal por configuración.

## Orden y dependencias

- 0 es independiente. 1 → 2 son la base y van en ese orden.
- Tras la 2, las fases 3, 4, 5, 6 y 7 son independientes entre sí. Para tener cuanto antes una app útil: **5 (subir audio, hecha) → 3 (leer) → 6 → 4 → 7**.
- 8 va al final y depende de la app.

## Fuera de alcance (a largo plazo)

Solo si algún día la app se abre a más usuarios; no adelantar nada de esto:

- Dueño (`user`) en `AudioRecording`, `DailySummary`, `Topic` y `Reminder`, únicos por usuario y filtrado en todos los repositorios.
- Registro, verificación de email, recuperación de contraseña y borrado de cuenta desde la app (hoy la gestión es solo por consola, por decisión explícita).
- Zona horaria y hora del resumen por usuario (`LocalTimezone` y `Schedule` son globales).
- Traducciones e idioma del resumen.
- Capacidad de Whisper/Ollama para varios usuarios e imagen Docker de producción propia.
