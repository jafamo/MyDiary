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
| Cola / async | Symfony Messenger (transporte Doctrine o Redis) |
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
3. El objeto `voice`/`audio` del update ya trae `duration` (segundos) — se guarda directamente, sin procesar el fichero.
4. Descarga el fichero de Telegram y lo guarda en el filesystem del servidor.
5. Se crea un registro `AudioRecording` en BD con estado `PENDING`, usando `telegram_message_id` (con constraint único) para detectar reintentos: si el update ya existe, se responde 200 OK sin crear duplicado ni volver a despachar el mensaje async.
6. Se responde inmediatamente al usuario en Telegram: *"Audio recibido ✅"* (el webhook debe responder rápido — nada de trabajo pesado síncrono aquí).
7. Se despacha un mensaje asíncrono (Symfony Messenger) `TranscribeAudioMessage`.

### 3.2 Flujo de transcripción (worker asíncrono)
1. El `TranscribeAudioMessageHandler` recoge el mensaje.
2. Llama al servicio de transcripción (Open WebUI / whisper.cpp) pasando el fichero de audio.
3. Guarda el resultado en la entidad `Transcription` (contenido en BD + export a fichero de texto en filesystem).
4. Actualiza el estado del `AudioRecording` a `TRANSCRIBED`.
5. Envía al usuario en Telegram un resumen corto de esa transcripción concreta.
6. **Manejo de errores**: si la llamada al servicio de transcripción falla, Symfony Messenger reintenta el `TranscribeAudioMessage` un número limitado de veces con backoff (`retry_strategy` nativo). Si se agotan los reintentos, `AudioRecording` pasa a estado `ERROR` y se notifica al usuario por Telegram (*"No se pudo transcribir este audio ❌"*). El audio no se pierde: queda visible en la web, con opción de eliminarlo (flujo 3.4).

### 3.3 Flujo de resumen diario (scheduled, 21:00 Europe/Madrid)
1. Symfony Scheduler dispara el comando `app:generate-daily-summary` cada día a las 21:00 (timezone Europe/Madrid).
2. Si existe algún `AudioRecording` del día en curso con estado `PENDING` (aún no transcrito), el comando espera/reintenta con backoff durante una ventana corta antes de continuar, para no dejar fuera transcripciones casi listas.
3. Recoge todas las transcripciones en estado `TRANSCRIBED` del día en curso.
4. Llama a Ollama para generar: (a) resumen del día, (b) lista de temas tratados.
5. Guarda/actualiza el registro `DailySummary` de esa fecha.
6. **Manejo de errores**: si la llamada a Ollama falla, se reintenta un par de veces con espera corta; si sigue fallando, se loguea el error (Monolog), no se genera `DailySummary` ese día (la vista Diario simplemente no muestra resumen) y se notifica al usuario por Telegram (*"No se pudo generar el resumen de hoy ⚠️"*). No hay reintento automático al día siguiente.

### 3.4 Edición y eliminación
- Desde la web se puede editar manualmente el texto de una transcripción.
- Se puede eliminar una transcripción que no sirva (p. ej. audio inentendible): se borran `AudioRecording`, `Transcription` y los ficheros asociados en filesystem (audio + export de texto) sin dejar rastro. No existe una función de "regenerar desde el audio original" — si el resultado no es válido, el usuario reenvía un audio nuevo con mejor sonido, lo que dispara de nuevo el flujo 3.1.
- La BD es la fuente de verdad; el fichero de texto es un export/backup regenerado tras cada edición (no hay doble mantenimiento manual de ambas fuentes).

### 3.5 Vistas / menús (navegación vertical, tras login)
- **Login**
- **Diario** — audios + transcripciones del día actual, con el resumen del día al final (visible cuando ya se generó, a partir de las 21:00).
- **Historial** — vista histórica navegable por fecha/calendario de días anteriores.
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
- **Sin entidad `User` en BD.** Un único usuario, credenciales definidas por variables de entorno y `in_memory` provider de Symfony Security (hash de password en `.env`/config, no en tabla). No hay registro, recuperación de contraseña ni gestión de usuarios.
- Regla general aplicada: **introducir un patrón solo cuando el problema que resuelve ya existe**, no de forma anticipada. Si en el futuro aparece una necesidad real de más desacoplo en un punto concreto, se extrae la interfaz correspondiente entonces.

## 5. Estructura de carpetas propuesta

```
src/
├── Entity/
│   ├── AudioRecording.php
│   ├── Transcription.php
│   └── DailySummary.php
├── Repository/
│   ├── AudioRecordingRepository.php
│   ├── TranscriptionRepository.php
│   └── DailySummaryRepository.php
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
│   ├── TranscriptionController.php            # editar / eliminar / regenerar
│   ├── SecurityController.php                 # login/logout
│   └── TelegramWebhookController.php
├── Form/
│   └── TranscriptionEditType.php
└── Command/
    └── GenerateDailySummaryCommand.php         # invocado por Scheduler a las 21:00
```

## 6. Modelo de datos (borrador)

### `audio_recording`
| Campo | Tipo | Notas |
|---|---|---|
| id | int/uuid | PK |
| telegram_message_id | string | ID del mensaje original de Telegram — **unique**, garantiza idempotencia ante reintentos del webhook |
| file_path | string | Ruta del audio en el filesystem |
| received_at | datetime | |
| status | enum | `PENDING`, `TRANSCRIBED`, `ERROR` |
| duration_seconds | int | Viene del campo `duration` del update de Telegram (`voice`/`audio`), no se calcula |

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
| topics | json (o tabla `topic` normalizada N:M) | |
| generated_at | datetime | |

## 7. Infraestructura y servicios Docker Compose

```
docker-compose.yml (previsto)
├── app            # PHP-FPM 8.4 + Symfony
├── nginx           # o Caddy
├── postgres:16      # volumen persistente para datos de BD
├── messenger-worker  # misma imagen que app, comando: messenger:consume
├── whisper (opcional) # solo si no se reutiliza el STT de Open WebUI existente
└── (Ollama / Open WebUI ya están montados aparte — solo se consumen por URL vía variables de entorno)
```

Volúmenes persistentes necesarios (compartidos entre `app` y `messenger-worker`, para que ambos accedan a los mismos ficheros):
- `postgres_data` → datos de PostgreSQL.
- `audio_storage` → ficheros de audio descargados de Telegram (`AudioRecording.file_path`).
- `transcription_exports` → export de texto de transcripciones (`Transcription.file_path`).

Variables de entorno necesarias (borrador):
```
DATABASE_URL=postgresql://user:pass@postgres:5432/telegram_notes
TELEGRAM_BOT_TOKEN=
TELEGRAM_AUTHORIZED_CHAT_ID=
OLLAMA_BASE_URL=http://192.168.4.200:PUERTO
OPENWEBUI_STT_BASE_URL=http://192.168.4.200:PUERTO
OPENWEBUI_API_KEY=
APP_TIMEZONE=Europe/Madrid
APP_AUTH_USERNAME=
APP_AUTH_PASSWORD_HASH=
```

## 8. Pendiente de confirmar antes/durante el desarrollo

- [ ] Confirmar en el panel de admin de Open WebUI qué motor de Speech-to-Text está activo (Web API / Local Whisper / OpenAI-compatible remoto) y su URL/puerto real.
- [ ] Si Open WebUI no tiene STT operativo server-side, montar `whisper.cpp` como contenedor propio.
- [ ] Decidir transporte de Symfony Messenger (Doctrine vs Redis) — Redis ya disponible en la infraestructura, se recomienda usarlo.
- [ ] Definir si "Historial" usa vista calendario o listado simple por fecha.
- [ ] Definir criterio exacto de "temas" en Estadísticas (¿tabla `topic` normalizada o array JSON?).

## 9. Orden de construcción recomendado

1. Confirmar y conectar servicio de transcripción (pieza de mayor incertidumbre).
2. Symfony base + Doctrine + entidades + migraciones (modelo de datos de la sección 6).
3. Webhook de Telegram + Messenger async (recepción + transcripción).
4. Symfony Scheduler para el resumen de las 21:00.
5. Vistas Twig: Diario, Historial, Estadísticas, login/logout.
6. Edición/eliminación/regeneración de transcripciones.
7. Pulido, logging (Monolog) y manejo de errores en cada punto de integración externa (Telegram, Whisper, Ollama).