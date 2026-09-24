## Context

Los logs de PHP (web y `messenger-worker`) se escriben en JSON con `FlattenedContextJsonFormatter`, que aplana `context` y `extra` al nivel raíz; Filebeat los indexa tal cual en Elasticsearch. Symfony Messenger emite sus propios logs en el canal `messenger` (`SendMessageMiddleware`, `HandleMessageMiddleware`, `Worker`, `SendFailedMessageForRetryListener`, `SendFailedMessageToFailureTransportListener`), con `class`, `message_id`, `handler`... en el contexto, pero sin ningún campo de estado.

## Goals / Non-Goals

**Goals:**
- Campo `messenger_status` filtrable en Kibana para los logs de Messenger, sin tocar el código de Symfony ni duplicar líneas de log.

**Non-Goals:**
- Añadir `messenger_status` a logs propios de la app (`app`) o a otros canales.
- Cambiar la configuración de Filebeat o el mapeo de Elasticsearch.
- Métricas/contadores de mensajes (esto es solo logging).

## Decisions

- **Processor de Monolog limitado al canal `messenger`** (`#[AsMonologProcessor(channel: 'messenger')]`) que escribe `extra.messenger_status`. El formatter ya lo sube a la raíz. Alternativa descartada: un `EventSubscriber` sobre `WorkerMessageReceivedEvent` / `WorkerMessageHandledEvent` / `WorkerMessageFailedEvent` que emitiera líneas propias con `messenger_status`: duplicaría cada evento en el log y no cubriría `sent`/`no_handler`.
- **Clasificación por fragmentos fijos del mensaje** (`str_starts_with` / `str_contains` sobre textos como `Received message `, `was handled successfully`, `Sending for retry`...). Un processor a nivel de logger ve la plantilla sin interpolar (`{class}`), pero los fragmentos elegidos no contienen placeholders, así que la clasificación funciona igual con plantilla o mensaje interpolado. El orden importa: `retry`/`failed` antes que cualquier regla genérica sobre "handling message".
- **`extra` y no `context`**: si el contexto ya trae una clave `messenger_status`, `FlattenedContextJsonFormatter` da prioridad al contexto y no se pisa.
- **Nombre `messenger_status` y no `status`**: nginx y PHP indexan en el mismo data stream de Filebeat, donde `status` ya está mapeado como numérico (código HTTP del access log de nginx). Un `status` de texto provocaría `mapper_parsing_exception` y Elasticsearch descartaría la línea. `status` queda reservado para el código HTTP.
- **Valores en inglés, minúsculas y estables** (`received`, `acknowledged`...), igual que los códigos de error: claves para filtrar, no texto para humanos.

## Risks / Trade-offs

- [Symfony cambia el texto de sus logs en una actualización] → el registro queda sin `messenger_status` (no rompe nada); los tests unitarios fijan los textos actuales y la regresión se detecta revisando Kibana tras actualizar Symfony.
- [En prod el handler `main` es `fingers_crossed`] → los logs INFO de Messenger solo llegan a fichero cuando se vuelca el buffer; esto no cambia con esta propuesta.
