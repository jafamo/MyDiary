## 1. Infraestructura

- [x] 1.1 Cambiar la imagen de `diary-postgres` en `docker-compose.yml` de `postgres:${POSTGRES_VERSION}` a `pgvector/pgvector:pg${POSTGRES_VERSION}`.
- [x] 1.2 Añadir el paquete Composer `pgvector/pgvector` (Doctrine DBAL type para columnas `vector`).
- [x] 1.3 Añadir variable de entorno `OLLAMA_EMBEDDING_MODEL` (por defecto `nomic-embed-text`) en `.env`, `.env.example` y `services.yaml`.
- [x] 1.4 Documentar en README/Especificaciones.md que hay que hacer `docker pull`/`ollama pull` del modelo de embeddings en el servidor de Ollama antes de desplegar.

## 2. Migración de base de datos

- [x] 2.1 Migración Doctrine: `CREATE EXTENSION IF NOT EXISTS vector;`.
- [x] 2.2 Migración Doctrine: añadir columna `embedding vector(768)` nullable a la tabla `transcription`.
- [x] 2.3 Migración Doctrine: añadir columna `embedding vector(768)` nullable a la tabla `daily_summary`.
- [x] 2.4 Añadir índices `ivfflat`/`hnsw` sobre `embedding` en `transcription` y `daily_summary` para que la búsqueda no haga escaneo secuencial (evaluar cuál según volumen esperado).

## 3. Puerto y generación de embeddings

- [x] 3.1 Crear `src/Contract/EmbeddingGenerationException.php` (análogo a `SummaryGenerationException`/`TranscriptionException`).
- [x] 3.2 Crear `src/Contract/EmbeddingGeneratorInterface.php` con `generate(string $text): array` (vector de floats).
- [x] 3.3 Crear `src/Service/Ollama/OllamaEmbeddingGenerator.php`, llamando al endpoint `/api/embeddings` de Ollama con `OLLAMA_EMBEDDING_MODEL`.
- [x] 3.4 Registrar el binding del puerto en `config/services.yaml` (`App\Contract\EmbeddingGeneratorInterface: '@App\Service\Ollama\OllamaEmbeddingGenerator'`).
- [x] 3.5 Añadir columna/propiedad `embedding` a `src/Entity/Transcription.php` usando el tipo Doctrine de `pgvector/pgvector`.
- [x] 3.6 Añadir columna/propiedad `embedding` a `src/Entity/DailySummary.php` usando el mismo tipo Doctrine.

## 4. Integración en el ciclo de vida de la transcripción y el resumen diario

- [x] 4.1 En `TranscribeAudioMessageHandler::__invoke`, tras persistir la `Transcription`, generar y guardar su embedding; envolver en try/catch para no bloquear el flujo si falla, registrando log `transcription.embedding_generation_failed`.
- [x] 4.2 En `TranscriptionEditor::applyManualEdit`, regenerar el embedding tras guardar el nuevo `content`, con el mismo manejo de fallo no bloqueante.
- [x] 4.3 En `DailySummaryService::saveDailySummary`, tras guardar el `DailySummary`, generar/regenerar y guardar su embedding; envolver en try/catch para no bloquear el flujo si falla, registrando log `daily_summary.embedding_generation_failed`.

## 5. Búsqueda

- [x] 5.1 Método en `TranscriptionRepository` para buscar por similitud coseno (`ORDER BY embedding <=> :queryEmbedding LIMIT :limit`), excluyendo `Transcription` con `embedding IS NULL`.
- [x] 5.2 Método análogo en `DailySummaryRepository` para buscar `DailySummary` por similitud coseno, excluyendo `embedding IS NULL`.
- [x] 5.3 `SearchController` + ruta protegida por login: recibe la query, genera su embedding vía `EmbeddingGeneratorInterface`, consulta ambos repositorios, fusiona y ordena los resultados por distancia, y los renderiza.
- [x] 5.4 Vista Twig de Búsqueda (input + lista de resultados con enlace al día en Historial/Diario, indicando el tipo de cada resultado).
- [x] 5.5 Añadir la entrada "Búsqueda" a la navegación existente.

## 6. Comando de backfill

- [x] 6.1 `bin/console app:transcription:backfill-embeddings` (siguiendo el patrón de `app:audio:retry-transcription`): regenera el embedding de las `Transcription` con `embedding IS NULL`.
- [x] 6.2 `bin/console app:daily-summary:backfill-embeddings`: regenera el embedding de los `DailySummary` con `embedding IS NULL`.
- [x] 6.3 Añadir shortcut `make embeddings-backfill` en el Makefile (ejecuta ambos comandos).

## 7. Tests

- [x] 7.1 Test de `OllamaEmbeddingGenerator` (mock HTTP, éxito y fallo).
- [x] 7.2 Test de generación de embedding en `TranscribeAudioMessageHandler` (éxito y fallo no bloqueante).
- [x] 7.3 Test de regeneración de embedding en `TranscriptionEditor::applyManualEdit`.
- [x] 7.4 Test de generación/regeneración de embedding en `DailySummaryService::saveDailySummary` (éxito y fallo no bloqueante).
- [x] 7.5 Test de `TranscriptionRepository` de búsqueda por similitud (con datos de test insertados con embeddings conocidos).
- [x] 7.6 Test de `DailySummaryRepository` de búsqueda por similitud, mismo patrón.
- [x] 7.7 Test funcional de `SearchController` (con y sin resultados, mezclando transcripciones y resúmenes).
- [x] 7.8 Test de ambos comandos de backfill.

## 8. Verificación

- [x] 8.1 `make cs-check` y `make test` en verde.
- [x] 8.2 Probar manualmente: levantar el stack con la nueva imagen de Postgres, comprobar que `docker compose up` no rompe datos existentes, hacer una búsqueda real.
- [x] 8.3 Actualizar `Especificaciones.md` con el nuevo flujo de búsqueda semántica y el cambio de imagen de PostgreSQL.
