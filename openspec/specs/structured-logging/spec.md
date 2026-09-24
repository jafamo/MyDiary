# structured-logging Specification

## Purpose
Campos estructurados que la aplicación añade a sus logs JSON (aplanados a primer nivel) para poder filtrarlos y agregarlos en Kibana: estado de los mensajes Messenger, código HTTP de cada petición y eventos de negocio.
## Requirements
### Requirement: Estado de los mensajes Messenger en los logs
Los registros de log del canal `messenger` SHALL incluir un campo `messenger_status` de primer nivel en el JSON con el estado del mensaje en su ciclo de vida, deducido del log que emite Symfony Messenger:

| Log de Symfony Messenger | `messenger_status` |
|---|---|
| `Received message {class}` | `received` |
| `Sending message {class} with {alias} sender using {sender}` | `sent` |
| `Message {class} handled by {handler}` | `handled` |
| `No handler for message {class}` | `no_handler` |
| `{class} was handled successfully (acknowledging to transport).` | `acknowledged` |
| `Error thrown while handling message {class}. Sending for retry ...` | `retry` |
| `Error thrown while handling message {class}. Removing from transport ...` | `failed` |
| `Rejected message {class} will be sent to the failure transport {transport}.` | `rejected` |

#### Scenario: Mensaje recibido por el worker
- **WHEN** el worker registra `Received message App\Message\TranscribeAudioMessage` en el canal `messenger`
- **THEN** la línea JSON del log incluye `"messenger_status": "received"` en el nivel raíz

#### Scenario: Mensaje procesado con éxito
- **WHEN** el worker registra `... was handled successfully (acknowledging to transport).`
- **THEN** la línea JSON del log incluye `"messenger_status": "acknowledged"`

#### Scenario: Mensaje reenviado a reintento
- **WHEN** Messenger registra `Error thrown while handling message ... Sending for retry #1 ...`
- **THEN** la línea JSON del log incluye `"messenger_status": "retry"`

#### Scenario: Mensaje descartado tras agotar reintentos
- **WHEN** Messenger registra `Error thrown while handling message ... Removing from transport after 3 retries ...`
- **THEN** la línea JSON del log incluye `"messenger_status": "failed"`

### Requirement: Sin `messenger_status` en logs no reconocidos
El campo `messenger_status` SHALL añadirse solo a registros del canal `messenger` que correspondan a uno de los estados definidos; el resto de registros (otros canales, o logs de `messenger` como `Stopping worker.`) MUST quedar sin modificar.

#### Scenario: Log de otro canal
- **WHEN** se registra `Lock acquired, now computing item "scheduler_checkpoint_default"` en el canal `cache`
- **THEN** la línea JSON del log no incluye campo `messenger_status`

#### Scenario: Log de messenger sin estado asociado
- **WHEN** el worker registra `Stopping worker.` en el canal `messenger`
- **THEN** la línea JSON del log no incluye campo `messenger_status`

