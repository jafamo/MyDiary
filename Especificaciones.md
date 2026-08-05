# Especificación del proyecto — Telegram Voice Notes (PHP/Symfony)

> Documento de arranque para desarrollo en Visual Studio Code. Recoge stack, arquitectura, modelo de datos, flujos y plan de construcción acordados.

## 1. Objetivo del proyecto

Aplicación web que recibe notas de voz por Telegram, las transcribe automáticamente, genera un resumen diario con los temas tratados, y permite consultar/editar todo desde una interfaz web con login.

## 2. Stack tecnológico

| Componente | Tecnología |
|---|---|
| Lenguaje / runtime | PHP >= 8.4 |
| Framework | Symfony (última LTS o última estable) |
| Vistas | Twig |
| ORM | Doctrine |
| Logging | Monolog |
| Base de datos | PostgreSQL 16 |
| Cola / async | Symfony Messenger (transporte Redis) |
| Scheduler | Symfony Scheduler (componente `symfony/scheduler`) |
| Contenedores | Docker + Docker Compose |
| Transcripción | Open WebUI (Whisper local/remoto) o `whisper.cpp` como fallback |
| Resumen / extracción de temas | Ollama (modelos existentes: qwen2.5:14b, llama3.1:8b, gemma2:27b, deepseek-r1:14b) vía API compatible OpenAI |
| Infraestructura destino | Mini PC con 32GB RAM (recursos no son una restricción) |

**Decisión explícita de arquitectura:** NO se usa hexagonal estricta ni CQRS ni EasyAdmin (ver sección 4 — Decisiones de diseño y motivos).

## 3. Funcionalidad

### 3.1 Flujo de captura de audio
1. Usuario envía un audio al bot de Telegram.
2. Webhook de Symfony recibe la notificación. Si el `chat_id` del update no coincide con `TELEGRAM_AUTHORIZED_CHAT_ID`, se descarta: se responde 200 OK a Telegram (para que no reintente) sin crear ningún registro ni responder al remitente, y se deja constancia en logs (Monolog) para poder revisarlo.
3. Si `telegram_message_id` ya existe en BD (reintento del propio webhook de Telegram, no reenvío del usuario), se responde 200 OK sin reprocesar nada.
4. El objeto `voice`/`audio` del update trae `file_unique_id` — identifica el contenido del fichero de audio en sí, estable aunque el usuario reenvíe el mismo audio (incluso a otro chat) o pase el tiempo. Se comprueba si ya existe un `AudioRecording` con ese `telegram_file_unique_id`:
   - Si existe y su estado es `ERROR`: es un reenvío tras un fallo. Se trata como reintento automático (ver 3.4-bis): se resetea su estado a `PENDING`, se limpian `error_code`/`error_message`, y se despacha un nuevo `TranscribeAudioMessage` para ese mismo `AudioRecording` (no se crea uno nuevo). Se responde *"Reintentando transcripción 🔄"*.
   - Si existe y su estado es `PENDING` o `TRANSCRIBED`: es un reenvío redundante. Se responde *"Ya tengo este audio 👍"* y no se hace nada más.
   - Si no existe: continúa el flujo normal (pasos 5-8).
5. El objeto `voice`/`audio` del update ya trae `duration` (segundos) — se guarda directamente, sin procesar el fichero.
6. Descarga el fichero de Telegram y lo guarda en el filesystem del servidor.
7. Se crea un registro `AudioRecording` en BD con estado `PENDING`, guardando `telegram_message_id` y `telegram_file_unique_id` (ambos con constraint único).
8. Se responde inmediatamente al usuario en Telegram: *"Audio recibido ✅"* (el webhook debe responder rápido — nada de trabajo pesado síncrono aquí).
9. Se despacha un mensaje asíncrono (Symfony Messenger) `TranscribeAudioMessage`.

### 3.2 Flujo de transcripción (worker asíncrono)
1. El `TranscribeAudioMessageHandler` recoge el mensaje.
2. Llama al servicio de transcripción (Open WebUI / whisper.cpp) pasando el fichero de audio.
3. Guarda el resultado en la entidad `Transcription` (contenido en BD + export a fichero de texto en filesystem).
4. Actualiza el estado del `AudioRecording` a `TRANSCRIBED`.
5. Envía al usuario en Telegram un resumen corto de esa transcripción concreta.
6. **Manejo de errores**: si la llamada al servicio de transcripción falla, Symfony Messenger reintenta el `TranscribeAudioMessage` un número limitado de veces con backoff (`retry_strategy` nativo). Si se agotan los reintentos, `AudioRecording` pasa a estado `ERROR`, se guardan `error_code` y `error_message` (ver sección 6) con la causa técnica, y se notifica al usuario por Telegram (*"No se pudo transcribir este audio ❌"*). El audio no se pierde: queda visible en la web con el motivo del error y opción de reintentar o eliminarlo (flujo 3.4-bis).

### 3.3 Flujo de resumen diario (scheduled, 21:00 Europe/Madrid)
1. Symfony Scheduler dispara el comando `app:generate-daily-summary` cada día a las 21:00 (timezone Europe/Madrid).
2. Si existe algún `AudioRecording` del día en curso con estado `PENDING` (aún no transcrito), el comando espera/reintenta con backoff durante una ventana corta antes de continuar, para no dejar fuera transcripciones casi listas.
3. Recoge todas las transcripciones en estado `TRANSCRIBED` del día en curso.
4. Llama a Ollama para generar: (a) resumen del día, (b) lista de temas tratados.
5. Guarda/actualiza el registro `DailySummary` de esa fecha.
6. **Manejo de errores**: si la llamada a Ollama falla, se reintenta un par de veces con espera corta; si sigue fallando, se loguea el error (Monolog), no se genera `DailySummary` ese día (la vista Diario simplemente no muestra resumen) y se notifica al usuario por Telegram (*"No se pudo generar el resumen de hoy ⚠️"*). No hay reintento automático al día siguiente.

### 3.4 Edición y eliminación
- Desde la web se puede editar manualmente el texto de una transcripción.
- Se puede eliminar una transcripción **ya generada** que no sirva (p. ej. audio inentendible, mala calidad de sonido): se borran `AudioRecording`, `Transcription` y los ficheros asociados en filesystem (audio + export de texto) sin dejar rastro. No existe una función de "regenerar desde el audio original" para este caso — si el resultado no es válido por culpa del propio audio, el usuario reenvía uno nuevo con mejor sonido, lo que dispara de nuevo el flujo 3.1.
- La BD es la fuente de verdad; el fichero de texto es un export/backup regenerado tras cada edición (no hay doble mantenimiento manual de ambas fuentes).

### 3.4-bis Reintento tras fallo técnico (estado `ERROR`)

Distinto del caso anterior: aquí el audio en sí es válido, pero el servicio de transcripción falló (p. ej. Ollama/Whisper apagado o inaccesible). No se pierde el audio original, así que no hace falta reenviarlo:

- En **Diario**/**Historial**, un `AudioRecording` en estado `ERROR` se muestra junto a su `error_code`/`error_message`, para saber la causa sin mirar logs del servidor.
- Se ofrece un botón **"Reintentar"** inline, que resetea el `AudioRecording` a `PENDING`, limpia `error_code`/`error_message`, y despacha un nuevo `TranscribeAudioMessage` (relanza el flujo 3.2 sobre el mismo registro).
- Reenviar el mismo audio por Telegram tiene el mismo efecto de forma automática, gracias al dedupe por `telegram_file_unique_id` (ver 3.1, paso 4).
- Si tras reintentar sigue fallando, se puede eliminar igualmente el `AudioRecording` (mismo borrado en cascada de 3.4) si se prefiere desistir de ese audio.

### 3.5 Vistas / menús (navegación vertical, tras login)
- **Login**
- **Diario** — audios + transcripciones del día actual, con el resumen del día al final (visible cuando ya se generó, a partir de las 21:00).
- **Historial** — vista de calendario (mes) navegable, con acceso a los audios/transcripciones de cada día anterior.
- **Estadísticas** — agregados: nº de audios/día, duración media, temas más frecuentes.
- **Logout**

## 4. Decisiones de diseño y motivos (importante para no reintroducir complejidad innecesaria)

Estas decisiones se tomaron explícitamente para evitar sobre-ingeniería en un proyecto personal con un solo usuario:

- **Sin EasyAdmin.** Las vistas reales (Diario, Historial, Estadísticas) son dashboards custom, no CRUDs genéricos. Un solo usuario (tú mismo) no necesita un back-office separado. Controladores Symfony + `FormType` estándar cubren la edición de transcripciones.
- **Sin hexagonal estricta / sin capas Domain-Application-Infrastructure separadas.** Las entidades Doctrine SON el modelo de dominio (no hay clases de dominio duplicadas ni mappers entre entidad de dominio y entidad Doctrine).
- **Sin CQRS ni bus de comandos/queries general.** Servicios de aplicación normales con métodos claros (`AudioRecordingService::store()`, etc.).
- **Sí se usan interfaces (puertos) puntuales** donde existe razón real para ello, por experiencia previa de cambiar de proveedor:
  - `TranscriberInterface` (implementaciones: Open WebUI / whisper.cpp)
  - `SummaryGeneratorInterface` (implementación: Ollama)
- **Symfony Messenger solo para lo async real**: la cadena Telegram → transcripción. No se convierte en bus general de la aplicación.
- **Gestión de usuarios solo por consola, sin web.** Entidad `User` en BD (Symfony Security). Los usuarios se crean y las contraseñas se cambian con comandos (`bin/console app:user:create`, `bin/console app:user:change-password`) — quien tiene acceso al servidor/contenedor puede cambiar una contraseña sin conocer la actual. No hay registro, ni recuperación de contraseña vía web (sin email, sin tokens), ni gestión de usuarios desde la interfaz.
- Regla general aplicada: **introducir un patrón solo cuando el problema que resuelve ya existe**, no de forma anticipada. Si en el futuro aparece una necesidad real de más desacoplo en un punto concreto, se extrae la interfaz correspondiente entonces.

## 5. Estructura de carpetas propuesta

```
src/
├── Entity/
│   ├── AudioRecording.php
│   ├── Transcription.php
│   ├── DailySummary.php
│   ├── Topic.php
│   └── User.php
├── Repository/
│   ├── AudioRecordingRepository.php
│   ├── TranscriptionRepository.php
│   ├── DailySummaryRepository.php
│   ├── TopicRepository.php
│   └── UserRepository.php
├── Service/
│   ├── AudioRecordingService.php
│   ├── DailySummaryService.php
│   ├── Whisper/WhisperTranscriber.php        # implementa TranscriberInterface
│   └── Ollama/OllamaSummaryGenerator.php     # implementa SummaryGeneratorInterface
├── Contract/
│   ├── TranscriberInterface.php
│   └── SummaryGeneratorInterface.php
├── Message/
│   └── TranscribeAudioMessage.php
├── MessageHandler/
│   └── TranscribeAudioMessageHandler.php
├── Controller/
│   ├── DiarioController.php
│   ├── HistorialController.php
│   ├── EstadisticasController.php
│   ├── TranscriptionController.php            # editar / eliminar
│   ├── AudioRecordingController.php           # reintentar (solo AudioRecording en estado ERROR)
│   ├── SecurityController.php                 # login/logout
│   └── TelegramWebhookController.php
├── Form/
│   └── TranscriptionEditType.php
└── Command/
    ├── GenerateDailySummaryCommand.php         # invocado por Scheduler a las 21:00
    ├── CreateUserCommand.php                   # app:user:create
    └── ChangeUserPasswordCommand.php            # app:user:change-password
```

## 6. Modelo de datos (borrador)

### `audio_recording`
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| telegram_message_id | string | ID del mensaje original de Telegram — **unique**, garantiza idempotencia ante reintentos del webhook |
| telegram_file_unique_id | string | ID de Telegram estable para el contenido del fichero — **unique**, detecta reenvíos del mismo audio (ver 3.1/3.4-bis) |
| file_path | string | Ruta del audio en el filesystem |
| received_at | datetime | |
| status | enum | `PENDING`, `TRANSCRIBED`, `ERROR` |
| duration_seconds | int | Viene del campo `duration` del update de Telegram (`voice`/`audio`), no se calcula |
| error_code | string | nullable. Solo relevante si `status = ERROR`. Código corto (p. ej. `404`, `TIMEOUT`, `SERVICE_UNAVAILABLE`), no necesariamente HTTP — pensado como clave estable, no texto libre |
| error_message | string | nullable. Solo relevante si `status = ERROR`. Clave/texto asociado al `error_code` (p. ej. `NOT_FOUND`, `OLLAMA_UNREACHABLE`), preparado para poder mapearse a claves de traducción más adelante |

### `transcription`
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| audio_recording_id | FK (1:1), `onDelete: CASCADE` | |
| content | text | Fuente de verdad, editable |
| file_path | string | Export de backup, regenerado tras cada edición |
| edited_manually | bool | default false |
| created_at | datetime | |
| updated_at | datetime | |

No hay flujo de "regenerar": eliminar borra `AudioRecording` + `Transcription` + ficheros en cascada (ver 3.4).

### `daily_summary`
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| date | date | unique |
| summary_text | text | |
| generated_at | datetime | |

Relación N:M con `topic` a través de tabla pivote `daily_summary_topic`.

### `topic`
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| name | string | **unique** — nombre normalizado del tema (permite agregarlo en Estadísticas: "temas más frecuentes" entre días) |

No hay flujo de gestión manual de `topic`: se crean automáticamente al generar el resumen diario (3.3) si no existen ya con ese `name`.

### `app_user`
Nota: la tabla se llama `app_user`, no `user` — `user` es palabra reservada en PostgreSQL y provoca errores intermitentes de "column does not exist" en consultas generadas por Doctrine si no se cita de forma consistente; se evita el problema de raíz renombrando la tabla.
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| username | string | **unique** |
| password_hash | string | hash de la contraseña (Symfony `PasswordHasher`) |
| roles | json | p. ej. `["ROLE_USER"]` — sin niveles de rol complejos, un único usuario |

Sin registro ni recuperación de contraseña vía web. Gestión exclusivamente por consola: `bin/console app:user:create <username>` (pide/genera la contraseña) y `bin/console app:user:change-password <username>` (fija una contraseña nueva sin pedir la actual — requiere acceso al servidor/contenedor, que ya implica confianza de administrador).

## 7. Infraestructura y servicios Docker Compose

```
docker-compose.yml (previsto)
├── app            # PHP-FPM 8.4 + Symfony
├── nginx           # o Caddy
├── postgres:16      # volumen persistente para datos de BD
├── redis           # transporte de Symfony Messenger
├── messenger-worker  # misma imagen que app, comando: messenger:consume
└── (Ollama / Open WebUI ya están montados aparte, en 192.168.4.200 — solo se consumen por URL vía variables de entorno)
```

Volúmenes persistentes necesarios (compartidos entre `app` y `messenger-worker`, para que ambos accedan a los mismos ficheros):
- `postgres_data` → datos de PostgreSQL.
- `audio_storage` → ficheros de audio descargados de Telegram (`AudioRecording.file_path`).
- `transcription_exports` → export de texto de transcripciones (`Transcription.file_path`).

Variables de entorno necesarias (borrador):
```
DATABASE_URL=postgresql://user:pass@postgres:5432/telegram_notes
MESSENGER_TRANSPORT_DSN=redis://redis:6379/messages
TELEGRAM_BOT_TOKEN=
TELEGRAM_AUTHORIZED_CHAT_ID=
OLLAMA_BASE_URL=http://192.168.4.200:11434
OPENWEBUI_STT_BASE_URL=http://192.168.4.200:9006
OPENWEBUI_API_KEY=
APP_TIMEZONE=Europe/Madrid
```

El usuario (`user` en BD) se crea con `bin/console app:user:create`, no vía variables de entorno.

## 8. Pendiente de confirmar antes/durante el desarrollo

- [x] Confirmar en el panel de admin de Open WebUI qué motor de Speech-to-Text está activo y su URL/puerto real. **Confirmado (2026-08-04):** Open WebUI v0.10.2 corriendo en `192.168.4.200:9006`, motor STT = **Whisper local**. `OPENWEBUI_STT_BASE_URL=http://192.168.4.200:9006`.
- [x] ~~Si Open WebUI no tiene STT operativo server-side, montar `whisper.cpp` como contenedor propio.~~ No hace falta: Whisper local ya está operativo dentro de Open WebUI.
- [x] Decidir transporte de Symfony Messenger. **Confirmado (2026-08-04): Redis** (nuevo contenedor `redis` en `docker-compose.yml`, ver sección 7).
- [x] Definir si "Historial" usa vista calendario o listado simple por fecha. **Confirmado (2026-08-04): vista calendario.**
- [x] Definir criterio exacto de "temas" en Estadísticas. **Confirmado (2026-08-04): tabla `topic` normalizada**, relación N:M con `daily_summary` (ver sección 6).

## 9. Orden de construcción recomendado

1. Confirmar y conectar servicio de transcripción (pieza de mayor incertidumbre).
2. Symfony base + Doctrine + entidades + migraciones (modelo de datos de la sección 6).
3. Webhook de Telegram + Messenger async (recepción + transcripción).
4. Symfony Scheduler para el resumen de las 21:00.
5. Vistas Twig: Diario, Historial, Estadísticas, login/logout.
6. Edición/eliminación/regeneración de transcripciones.
7. Pulido, logging (Monolog) y manejo de errores en cada punto de integración externa (Telegram, Whisper, Ollama).