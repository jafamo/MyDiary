## Why

Los logs del canal `messenger` (worker de Messenger, Scheduler) llegan a Kibana sin ningún campo que indique en qué punto del ciclo de vida está cada mensaje: solo el texto libre de `message` ("Received message ...", "... was handled successfully ...", "Sending for retry ..."). No se puede filtrar ni agregar en Kibana por "mensajes fallidos" o "reintentos" sin búsquedas de texto frágiles.

## What Changes

- Nuevo processor de Monolog (`App\Logger\MessengerStatusProcessor`) que, para los registros del canal `messenger`, añade `extra.messenger_status` con el estado del mensaje deducido del log que emite Symfony Messenger:
  - `received` — "Received message {class}"
  - `sent` — "Sending message {class} with {alias} sender ..."
  - `handled` — "Message {class} handled by {handler}"
  - `no_handler` — "No handler for message {class}"
  - `acknowledged` — "{class} was handled successfully (acknowledging to transport)."
  - `retry` — "Error thrown while handling message {class}. Sending for retry ..."
  - `failed` — "Error thrown while handling message {class}. Removing from transport ..."
  - `rejected` — "Rejected message {class} will be sent to the failure transport ..."
- Gracias a `FlattenedContextJsonFormatter`, `messenger_status` sale como campo de primer nivel en el JSON y Filebeat lo indexa como `messenger_status` en Kibana.
- Los registros de otros canales, o del canal `messenger` que no correspondan a ningún estado conocido (p. ej. "Stopping worker."), no se modifican.

## Capabilities

### New Capabilities
- `structured-logging`: campos estructurados que la aplicación añade a sus logs JSON para poder filtrarlos en Kibana; empieza con el `messenger_status` de los mensajes Messenger.

### Modified Capabilities

## Impact

- Código: `src/Logger/MessengerStatusProcessor.php` (nuevo), registro como `monolog.processor` (autoconfigurado vía atributo `#[AsMonologProcessor(channel: 'messenger')]`).
- Tests: `tests/Logger/MessengerStatusProcessorTest.php`.
- Sin migraciones ni cambios de infraestructura; Filebeat ya indexa los campos de primer nivel. En Kibana puede ser necesario refrescar el Data View `filebeat-*` para que aparezca el campo nuevo.
