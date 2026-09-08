## Why

No existe hoy ninguna forma de buscar en el histórico de transcripciones ni de resúmenes diarios: para encontrar "¿cuándo hablé de X?" hay que repasar Historial día a día. Una búsqueda por texto exacto (`LIKE`) solo encontraría coincidencias literales de palabra; el usuario quiere poder preguntar por significado ("¿cuándo hablé de dinero?" debería encontrar audios donde se dijo "presupuesto"), aprovechando que el proyecto ya tiene Ollama disponible como infraestructura.

## What Changes

- Nueva vista/campo de **Búsqueda** accesible desde la navegación, donde el usuario escribe una consulta en lenguaje natural y obtiene una lista combinada de `Transcription` y `DailySummary` ordenada por relevancia semántica (no por coincidencia literal), cada resultado enlazando al día correspondiente en Historial/Diario.
- Al transcribirse un audio (`TranscribeAudioMessageHandler`, flujo 3.2), además de guardar el texto se genera y guarda su **embedding vectorial** vía un nuevo puerto `EmbeddingGeneratorInterface` (implementación Ollama, modelo de embeddings dedicado, p. ej. `nomic-embed-text`).
- Al **editar manualmente** una transcripción, se regenera su embedding para no quedar desincronizado con el texto vigente.
- Al **generar o regenerar** el resumen diario (`DailySummaryService::saveDailySummary`, flujo 3.5), se genera y guarda también el embedding vectorial de `summaryText`.
- La búsqueda genera el embedding de la consulta y ordena los resultados de ambas fuentes por distancia coseno usando `pgvector` (nueva extensión de PostgreSQL).
- **BREAKING** (infraestructura, no API): la imagen Docker de PostgreSQL pasa de `postgres:16` a una variante con `pgvector` preinstalado (p. ej. `pgvector/pgvector:pg16`), y se añade una migración de Doctrine para la extensión y la columna del vector.

## Capabilities

### New Capabilities

- `semantic-search`: búsqueda en lenguaje natural sobre transcripciones y resúmenes diarios, basada en embeddings vectoriales y similitud coseno vía pgvector.

### Modified Capabilities

- `transcription-management`: se añade la generación (y regeneración tras edición) del embedding de cada `Transcription` como parte del ciclo de vida existente.
- `daily-summary-generation`: se añade la generación (y regeneración) del embedding de cada `DailySummary` como parte del ciclo de vida existente.
- `docker-infrastructure`: cambio de imagen de PostgreSQL para incluir la extensión `pgvector`.

## Impact

- **Nueva dependencia de infraestructura**: extensión PostgreSQL `pgvector` (cambio de imagen Docker + migración Doctrine para `CREATE EXTENSION` y columna `vector`).
- **Nuevo modelo Ollama**: un modelo de embeddings (p. ej. `nomic-embed-text`, ~270MB) además de los modelos de generación de texto ya usados — recursos de sobra en la mini PC de 32GB.
- `src/Contract/EmbeddingGeneratorInterface.php` (nuevo puerto, siguiendo el patrón de `TranscriberInterface`/`SummaryGeneratorInterface`).
- `src/Service/Ollama/OllamaEmbeddingGenerator.php` (nueva implementación).
- `src/Entity/Transcription.php`: nueva columna `embedding` (tipo `vector`, vía tipo Doctrine custom o SQL nativo).
- `src/Entity/DailySummary.php`: nueva columna `embedding` (mismo tipo).
- `src/MessageHandler/TranscribeAudioMessageHandler.php`: genera el embedding tras guardar la transcripción.
- Controlador de edición de transcripción existente: regenera el embedding al editar el contenido.
- `src/Service/DailySummaryService.php` (`saveDailySummary`): genera/regenera el embedding tras guardar el resumen diario.
- `src/Repository/TranscriptionRepository.php`: nuevo método de búsqueda por similitud (`ORDER BY embedding <=> :queryEmbedding`).
- `src/Repository/DailySummaryRepository.php`: nuevo método de búsqueda por similitud, mismo patrón.
- Nuevo controlador/vista de Búsqueda + ruta en la navegación, combinando y ordenando resultados de ambas fuentes.
- `docker-compose.yml`: imagen de `diary-postgres`.
- Migración Doctrine para `pgvector` y ambas columnas.
