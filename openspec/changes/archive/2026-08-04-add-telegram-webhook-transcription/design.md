## Context

Symfony + Doctrine + las 5 entidades + Messenger/Redis ya están operativos (changes anteriores). Ollama y Open WebUI ya están confirmados y accesibles: `192.168.4.200:11434` (Ollama, se usará en el paso 4, no aquí) y `192.168.4.200:9006` (Open WebUI, Whisper local — el que sí se usa en este change). Falta decidir varios detalles concretos que `Especificaciones.md` deja abiertos a nivel de implementación: formato exacto del `retry_strategy`, taxonomía de `error_code`, y cómo detectar "se agotaron los reintentos" con Symfony Messenger.

## Goals / Non-Goals

**Goals:**
- Flujo 3.1 completo: webhook → whitelist → dedupe (mensaje y fichero) → descarga → `AudioRecording` → respuesta rápida → `TranscribeAudioMessage`.
- Flujo 3.2 pasos 1-5: handler asíncrono transcribe con Open WebUI, guarda `Transcription`, marca `TRANSCRIBED`, notifica.
- Flujo 3.2 paso 6: reintentos con backoff nativo de Messenger; al agotarse, `ERROR` + notificación.
- Mensajes de Telegram exactos a los de la especificación ("Audio recibido ✅", "Ya tengo este audio 👍", "Reintentando transcripción 🔄", "No se pudo transcribir este audio ❌").

**Non-Goals:**
- No se implementa el botón web "Reintentar" (3.4-bis) — no hay vistas todavía. El único camino de reintento en este change es el automático por reenvío de Telegram.
- No se implementa edición/eliminación de transcripciones (3.4) — requiere las vistas del paso 5/6.
- No se implementa el resumen diario ni Ollama (3.3, paso 4).
- No se genera un SDK/wrapper genérico de la API de Telegram — solo los 3 métodos que hacen falta (`sendMessage`, `getFile`, descarga del binario).

## Decisions

### 1. Token del bot en la URL del webhook, no solo en el body
Ruta: `POST /telegram/webhook/{token}`. El controlador compara `{token}` con `TELEGRAM_BOT_TOKEN` (o un secreto derivado) y devuelve 404 si no coincide, **antes** de parsear el body. Es la práctica estándar recomendada por Telegram para que la URL del webhook no sea adivinable por un tercero que solo conozca el dominio. No añade complejidad real (una comparación de string) y cierra un vector obvio de spam/abuso sin necesidad de autenticación completa.

### 2. `TelegramClient` propio sobre `symfony/http-client`, no un SDK de Telegram
Solo se necesitan 3 llamadas (`sendMessage`, `getFile`, descarga del binario por `file_path`). Un SDK completo (p. ej. `irazasyed/telegram-bot-sdk`) añadiría superficie y dependencias no usadas — contradice la regla general de `CLAUDE.md` de no introducir más de lo que el problema requiere. `symfony/http-client` (`HttpClientInterface`) ya es una dependencia transitiva de Symfony y basta.

### 3. `AudioRecordingService::receive()` centraliza el flujo 3.1 (pasos 2-9)
El controlador (`TelegramWebhookController`) solo parsea el update, delega en `AudioRecordingService`, y traduce el resultado a una respuesta Telegram. `AudioRecordingService` expone algo como:
```php
receive(string $telegramMessageId, string $telegramFileUniqueId, int $chatId, int $durationSeconds, callable $downloadFile): AudioRecordingReceiveResult
```
donde `AudioRecordingReceiveResult` es un enum/DTO simple (`CREATED`, `DUPLICATE_MESSAGE`, `DUPLICATE_FILE`, `RETRYING_AFTER_ERROR`) que el controlador traduce a los textos exactos de la especificación. Mantiene la lógica de negocio testeable sin HTTP de por medio.

### 4. Descarga de audio: patrón `download → temp → move`, ruta final en `var/audio/`
`TelegramClient::downloadFile()` guarda el binario recibido de Telegram directamente en `%kernel.project_dir%/var/audio/<telegram_file_unique_id>.<ext>` (extensión inferida del `file_path` que devuelve Telegram). Esa ruta (relativa al proyecto) es la que se guarda en `AudioRecording.file_path`. Coincide con el volumen `${DATA_PATH}/audio` ya montado en `diary-php`/`diary-messenger-worker` (change de Docker).

### 5. `TranscribeAudioMessage` lleva solo el id, no la entidad
```php
final class TranscribeAudioMessage
{
    public function __construct(public readonly int $audioRecordingId) {}
}
```
Evita serializar la entidad completa a través del transporte Redis (antipatrón conocido de Messenger) — el handler recarga el `AudioRecording` por id desde el repositorio.

### 6. `retry_strategy` del transporte `async`: 3 reintentos, backoff x2, tope 60s
```yaml
framework:
    messenger:
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 5000       # 5s antes del primer reintento
                    multiplier: 2     # 5s, 10s, 20s
                    max_delay: 60000
```
Valores concretos no fijados en `Especificaciones.md` (quedó como pregunta abierta en el change anterior) — se eligen 3 reintentos con backoff exponencial corto porque los fallos esperados (Whisper/Ollama caído momentáneamente) suelen resolverse en segundos/minutos, no horas; no tiene sentido reintentar horas después sin que el usuario lo sepa (para eso ya existe el reenvío manual con dedupe, 3.4-bis).

### 7. Detección de "se agotaron los reintentos": `WorkerMessageFailedEvent` + `willRetry()`
El hándler (`TranscribeAudioMessageHandler`) simplemente relanza la excepción si la llamada a Whisper falla — Messenger se encarga de reintentar según el `retry_strategy` (decisión 6). Un `EventSubscriber` (`src/EventListener/TranscriptionFailureListener.php`) escucha `WorkerMessageFailedEvent`; cuando `$event->willRetry() === false` (última tentativa agotada) y el mensaje es un `TranscribeAudioMessage`, marca el `AudioRecording` como `ERROR`, mapea la excepción a un `error_code`/`error_message` (ver decisión 8), y notifica por Telegram. Es el patrón recomendado por la documentación de Symfony Messenger para "acción final tras agotar reintentos", evita duplicar lógica de conteo de reintentos a mano.

### 8. Taxonomía de `error_code`/`error_message`
`error_code` sigue siendo una clave corta y estable (ejemplos ya dados en `Especificaciones.md` sección 6: `404`, `TIMEOUT`, `SERVICE_UNAVAILABLE`), pensada para lógica de programa (comparar, filtrar, futura clave de i18n). `error_message` pasa a ser una **frase descriptiva en castellano** (no solo una clave corta tipo `OLLAMA_UNREACHABLE`), a petición explícita del usuario, para que se entienda la causa del fallo desde Diario/Historial sin mirar logs (3.4-bis):

- `TranscriberInterface` lanza `TranscriptionException` con `errorCode` (string) y `errorMessage` (string descriptivo) al fallar.
- `WhisperTranscriber` los puebla según la causa:
  - Timeout de red → `errorCode: 'TIMEOUT'`, `errorMessage: 'No se pudo contactar con el servicio de transcripción (Open WebUI): se agotó el tiempo de espera.'`
  - HTTP 4xx/5xx → `errorCode` = código HTTP como string (p. ej. `'503'`), `errorMessage: 'El servicio de transcripción (Open WebUI) respondió con un error HTTP <código>: <razón/cuerpo si está disponible>.'`
- El listener del punto 7 usa esos valores directamente; si la excepción no es una `TranscriptionException` (fallo inesperado), usa `errorCode: 'UNKNOWN'`, `errorMessage: 'Fallo inesperado al transcribir: <mensaje de la excepción>.'`

### 9. Logging estructurado (JSON) para poder investigar en Kibana
El usuario visualizará los logs en Kibana, así que los mensajes de log de este flujo SHALL ir en JSON con contexto estructurado, no solo texto libre. Se instala `symfony/monolog-bundle` (todavía no estaba instalado pese a figurar en el stack de `Especificaciones.md`) y se configura un handler de fichero con `monolog.formatter.json`, escribiendo a `/var/log/php-diary/app-%kernel.environment%.log` — coincide con el bind mount `${LOGS_PATH}/php` / `${LOGS_PATH}/messenger-worker` ya existente (una ruta interna común, distinto host dir por contenedor), listo para que un recolector tipo Filebeat lo ingiera hacia Kibana en el futuro (fuera de alcance de este change: no se monta ELK aquí, solo se deja el formato preparado).

Puntos de log con contexto explícito (no solo el mensaje):
- **Chat no autorizado** (3.1 paso 2): `warning`, contexto `{event: 'telegram.chat_rejected', chat_id, telegram_message_id}`.
- **Fallo de transcripción, cada intento** (dentro del handler, antes de relanzar la excepción): `warning`, contexto `{event: 'transcription.attempt_failed', audio_recording_id, telegram_file_unique_id, error_code, error_message, exception_class}`.
- **Fallo definitivo tras agotar reintentos** (listener): `error`, contexto `{event: 'transcription.retry_exhausted', audio_recording_id, telegram_file_unique_id, error_code, error_message, exception_class, retry_count}`.

Estos campos (`event`, `audio_recording_id`, `error_code`, etc.) quedan como claves de nivel superior del JSON de log — permiten filtrar/agrupar en Kibana por tipo de evento o por audio concreto sin parsear texto libre.

### 10. Comando `app:telegram:set-webhook` para registrar el webhook
En vez de documentar un `curl` manual, se añade un comando de consola (mismo patrón que `app:user:create`) que llama a `setWebhook` de la API de Telegram con la URL pública configurada (`APP_PUBLIC_URL=https://diary.jfarinos.keenetic.pro` + ruta del webhook + token). Queda reproducible y testeable como el resto de comandos del proyecto, sin añadir infraestructura nueva.

### 11. Mensajes de Telegram literales de la especificación
Se centralizan como constantes en `AudioRecordingService`/`TranscribeAudioMessageHandler` (no hace falta un sistema de traducción todavía — un solo idioma, un solo usuario): `"Audio recibido ✅"`, `"Ya tengo este audio 👍"`, `"Reintentando transcripción 🔄"`, `"No se pudo transcribir este audio ❌"`.

## Risks / Trade-offs

- **[Riesgo] `symfony/http-client` no instalado todavía**: hay que verificar/instalar (`composer require symfony/http-client`) — no se confirmó en el change anterior. → Mitigación: primera tarea de implementación, verificar con `composer show`.
- **[Riesgo] Latencia de Open WebUI/Whisper local con audios largos**: si la transcripción tarda más que el timeout HTTP por defecto, se contaría como fallo y consumiría un reintento innecesariamente. → Mitigación: fijar un timeout generoso (p. ej. 120s) en la llamada HTTP de `WhisperTranscriber`, ajustable si en la práctica no basta.
- **[Riesgo] Formato exacto de la API de Open WebUI para STT no confirmado en detalle** (endpoint, forma del payload/respuesta) — solo se confirmó que el motor activo es Whisper local (change anterior). → Mitigación: implementar contra el endpoint estándar de Open WebUI (`POST /api/v1/audio/transcriptions`, compatible con la API de OpenAI Whisper) y ajustar durante la implementación si la respuesta real difiere; requiere `OPENWEBUI_API_KEY` para autenticar.
- **[Trade-off] Sin cola de "dead letter" (`failure_transport`)**: el mensaje se descarta tras agotar reintentos, no queda encolado para reproceso manual — el propio `AudioRecording` en `ERROR` ya cumple ese rol (visible, reintentable por reenvío). Añadir `failure_transport` sería redundante con el estado `ERROR` ya modelado en el dominio.
- **[Trade-off] JSON en el fichero de log local sin ELK montado todavía**: el formato queda listo para Kibana, pero este change no despliega Filebeat/Logstash/Elasticsearch — es responsabilidad de infraestructura fuera del repo. Mientras tanto, el fichero JSON sigue siendo legible con `jq` desde `${LOGS_PATH}/php`/`${LOGS_PATH}/messenger-worker` en el host.
- **[Riesgo] Permisos de bind mount: `php-fpm` corre como `www-data` (uid 33), no como root**: confirmado en la práctica — a diferencia de los comandos ejecutados vía `docker compose exec` (que corren como root dentro de `diary-php`), las peticiones HTTP reales las procesa `php-fpm`, cuyo pool (`www.conf`) está configurado con `user = www-data; group = www-data`. Los bind mounts `logs/php`, `logs/messenger-worker`, `data/audio`, `data/transcriptions` se crean `root:root` por defecto (igual que `diary-redis` en el change de Docker), así que `php-fpm` no podía escribir el log ni descargar audios hasta hacer `chown -R 33:33` sobre esos directorios. → Mismo patrón de mitigación ya documentado: ajustar permisos vía contenedor auxiliar; pendiente de automatizar (o fijar `user:`/uid consistente) en un cambio futuro de infraestructura si vuelve a repetirse en más directorios.

## Migration Plan

No aplica (funcionalidad nueva, sin datos previos que migrar). Pasos de puesta en marcha:
1. Confirmar `symfony/http-client` instalado.
2. Implementar `TelegramClient`, `TranscriberInterface`/`WhisperTranscriber`.
3. Implementar `AudioRecordingService` y `TelegramWebhookController`.
4. Implementar `TranscribeAudioMessage`/Handler y el listener de fallo.
5. Configurar `retry_strategy` y routing en `messenger.yaml`.
6. Fijar `TELEGRAM_BOT_TOKEN`/`TELEGRAM_AUTHORIZED_CHAT_ID` reales en `.env` (el usuario los provee) y ejecutar `bin/console app:telegram:set-webhook` contra `APP_PUBLIC_URL=https://diary.jfarinos.keenetic.pro` para registrar el webhook en Telegram.
7. Tests unitarios de `AudioRecordingService` (dedupe/estados) y del listener de fallo; test del controlador con `KernelTestCase` simulando updates.

## Open Questions

Ninguna bloqueante. El formato exacto de la respuesta de Open WebUI para STT se resolverá ajustando `WhisperTranscriber` contra la instancia real durante la implementación (riesgo ya anotado arriba).
