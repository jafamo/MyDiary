## Context

El proyecto ya tiene Ollama como infraestructura para generación de texto (`SummaryGeneratorInterface`) y Whisper/Open WebUI para transcripción (`TranscriberInterface`), ambos vía puertos puntuales inyectados por Symfony DI. No hay ningún mecanismo de búsqueda: la única forma de encontrar contenido pasado es navegar Historial día a día. `Transcription.content` es el único texto largo por audio; `DailySummary.summary_text` es un agregado diario, no indexado individualmente en este cambio (se deja fuera, ver Non-Goals).

`TranscribeAudioMessageHandler::__invoke` (flujo 3.2) es el punto único donde una `Transcription` se crea; `TranscriptionEditor::applyManualEdit` (flujo 3.4) es el único punto donde su contenido cambia después. Ambos son los dos únicos sitios donde el embedding debe (re)generarse.

## Goals / Non-Goals

**Goals:**
- Buscar transcripciones por significado, no por coincidencia literal de palabra.
- Mantener el embedding de cada `Transcription` sincronizado con su contenido vigente (creación y edición manual).
- Reutilizar el patrón de puertos ya existente (`TranscriberInterface`, `SummaryGeneratorInterface`) para el nuevo `EmbeddingGeneratorInterface`.

**Non-Goals:**
- No se indexan `DailySummary` en esta primera versión (se puede añadir después si hace falta buscar por resumen del día en vez de por audio individual).
- No se implementa búsqueda híbrida (semántica + full-text) ni reranking con LLM — solo similitud coseno pura sobre embeddings.
- No se pagina infinita ni se pondera por fecha/recencia — se devuelve un top-N simple por similitud.
- No se expone la búsqueda por API pública ni por Telegram, solo desde la vista web (mismo perímetro que el resto de la app: login requerido).

## Decisions

- **pgvector como almacén vectorial**, en vez de un vector store externo (Qdrant, Weaviate, etc.). Motivo: un solo usuario y un volumen de datos pequeño (audios diarios, no millones de documentos) no justifican un servicio nuevo; Postgres ya es la BD del proyecto y `pgvector` permite `ORDER BY embedding <=> :query` directamente en SQL, sin sincronización entre dos almacenes. Alternativa descartada: vector store externo — añade un contenedor Docker, un cliente HTTP y un problema de consistencia (¿qué pasa si el borrado en Postgres no se propaga?) sin beneficio real a esta escala.
- **Cambio de imagen Docker de PostgreSQL** a `pgvector/pgvector:pg16` (basada en la oficial `postgres:16`, añade la extensión). Se activa con `CREATE EXTENSION IF NOT EXISTS vector;` en una migración Doctrine.
- **Tipo Doctrine custom para la columna vector**: se usa el paquete `pgvector/pgvector` (composer, incluye un DBAL Type `Pgvector\Doctrine\Vector`) en vez de mapear el vector como texto/JSON y castear a mano en cada query. Evita SQL nativo disperso por el código para leer/escribir el vector.
- **`EmbeddingGeneratorInterface` como puerto nuevo**, análogo a `TranscriberInterface`: `generate(string $text): array` (devuelve el vector de floats). Implementación única: `OllamaEmbeddingGenerator`, que llama al endpoint `/api/embeddings` de Ollama con un modelo de embeddings dedicado (nuevo parámetro de configuración `OLLAMA_EMBEDDING_MODEL`, separado de `OLLAMA_MODEL` que se usa para generación de texto/resúmenes — son modelos de propósito distinto).
- **Modelo de embeddings**: `nomic-embed-text` (768 dimensiones) como valor por defecto configurable — pequeño, rápido, soporta español razonablemente bien. Se documenta como variable de entorno, no hardcodeado, para poder cambiarlo sin tocar código (mismo patrón que `OLLAMA_MODEL`).
- **Generación síncrona dentro del flujo existente** (no un mensaje async nuevo): el embedding se genera justo después de guardar la `Transcription` en `TranscribeAudioMessageHandler` (ya estamos dentro de un worker asíncrono de Messenger, así que no bloquea la respuesta a Telegram) y justo después de guardar la edición en `TranscriptionEditor::applyManualEdit` (edición manual desde la web — llamada síncrona corta, aceptable). No se crea un `GenerateEmbeddingMessage` propio porque no hay necesidad de reintentos independientes: si Ollama falla generando el embedding, se loguea y la transcripción queda sin embedding (buscable solo cuando se regenere; ver Risks).
- **Fallo de generación de embedding no bloquea el flujo principal**: igual que el envío de Telegram en `notify-summary-telegram`, un fallo aquí se loguea (`transcription.embedding_generation_failed`) pero no impide guardar la `Transcription` ni marcar el `AudioRecording` como `TRANSCRIBED`. La búsqueda es una funcionalidad añadida, no puede degradar el flujo core de captura/transcripción.
- **Búsqueda vía nuevo controlador `SearchController`** con una vista Twig simple (input + lista de resultados enlazando a Historial/Diario del día correspondiente), consistente con el resto de vistas custom del proyecto (sin EasyAdmin).

## Risks / Trade-offs

- [Riesgo] Transcripciones sin embedding (por fallo de Ollama en su momento) quedan invisibles para la búsqueda indefinidamente → Mitigación: se deja como limitación conocida en la primera versión; si resulta un problema real, un comando de consola (`app:transcription:backfill-embeddings`, siguiendo el patrón de `app:audio:retry-transcription` ya existente) puede regenerar embeddings faltantes bajo demanda.
- [Riesgo] Cambiar de modelo de embeddings en el futuro invalida todos los vectores ya guardados (dimensiones/espacio semántico distintos) → Mitigación: documentar que un cambio de `OLLAMA_EMBEDDING_MODEL` requiere reindexar todo el histórico con el comando de backfill; no se implementa migración automática de vectores.
- [Riesgo] `pgvector/pgvector:pg16` como imagen base distinta de la oficial `postgres:16` → Mitigación: es una imagen mantenida activamente por el propio proyecto pgvector y usada ampliamente en producción; se fija versión exacta en `.env` igual que ya se hace con `POSTGRES_VERSION`.
- [Riesgo] Latencia de búsqueda (llamada a Ollama en cada consulta, red hacia `192.168.4.200`) → Aceptado: es una operación interactiva puntual del usuario, no un flujo automático; si resulta lenta en la práctica, se puede cachear embeddings de queries repetidas más adelante.

## Migration Plan

1. Cambiar la imagen de `diary-postgres` en `docker-compose.yml` a `pgvector/pgvector:pg16` y hacer `make restart` (o `down`+`up`) del servicio — los datos existentes en el volumen persisten, solo cambia el binario de Postgres.
2. Migración Doctrine: `CREATE EXTENSION IF NOT EXISTS vector;` + añadir columna `embedding vector(768)` nullable a `transcription`.
3. Desplegar el código con el nuevo puerto/implementación y el flujo de generación en creación/edición.
4. (Opcional, bajo demanda) Ejecutar el comando de backfill para las transcripciones ya existentes antes de este cambio, que no tendrán embedding hasta que se regenere.

No hace falta rollback especial: si se revierte el código, la columna `embedding` queda simplemente sin usar (no rompe el resto de la app), y la extensión `vector` puede quedarse instalada sin efecto.

## Open Questions

- ¿Se quiere backfill automático (comando ejecutado una vez tras el despliegue) o manual bajo demanda? Por defecto se deja como comando manual, siguiendo el patrón de `app:audio:retry-transcription`.
