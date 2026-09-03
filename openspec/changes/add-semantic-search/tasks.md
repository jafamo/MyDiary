## 1. Infraestructura

- [ ] 1.1 Cambiar la imagen de `diary-postgres` en `docker-compose.yml` de `postgres:${POSTGRES_VERSION}` a `pgvector/pgvector:pg${POSTGRES_VERSION}`.
- [ ] 1.2 Añadir el paquete Composer `pgvector/pgvector` (Doctrine DBAL type para columnas `vector`).
- [ ] 1.3 Añadir variable de entorno `OLLAMA_EMBEDDING_MODEL` (por defecto `nomic-embed-text`) en `.env`, `.env.example` y `services.yaml`.
- [ ] 1.4 Documentar en README/Especificaciones.md que hay que hacer `docker pull`/`ollama pull` del modelo de embeddings en el servidor de Ollama antes de desplegar.

## 2. Migración de base de datos

- [ ] 2.1 Migración Doctrine: `CREATE EXTENSION IF NOT EXISTS vector;`.
- [ ] 2.2 Migración Doctrine: añadir columna `embedding vector(768)` nullable a la tabla `transcription`.
- [ ] 2.3 Añadir índice `ivfflat`/`hnsw` sobre `embedding` para que la búsqueda no haga escaneo secuencial (evaluar cuál según volumen esperado).

## 3. Puerto y generación de embeddings

- [ ] 3.1 Crear `src/Contract/EmbeddingGenerationException.php` (análogo a `SummaryGenerationException`/`TranscriptionException`).
- [ ] 3.2 Crear `src/Contract/EmbeddingGeneratorInterface.php` con `generate(string $text): array` (vector de floats).
- [ ] 3.3 Crear `src/Service/Ollama/OllamaEmbeddingGenerator.php`, llamando al endpoint `/api/embeddings` de Ollama con `OLLAMA_EMBEDDING_MODEL`.
- [ ] 3.4 Registrar el binding del puerto en `config/services.yaml` (`App\Contract\EmbeddingGeneratorInterface: '@App\Service\Ollama\OllamaEmbeddingGenerator'`).
- [ ] 3.5 Añadir columna/propiedad `embedding` a `src/Entity/Transcription.php` usando el tipo Doctrine de `pgvector/pgvector`.

## 4. Integración en el ciclo de vida de la transcripción

- [ ] 4.1 En `TranscribeAudioMessageHandler::__invoke`, tras persistir la `Transcription`, generar y guardar su embedding; envolver en try/catch para no bloquear el flujo si falla, registrando log `transcription.embedding_generation_failed`.
- [ ] 4.2 En `TranscriptionEditor::applyManualEdit`, regenerar el embedding tras guardar el nuevo `content`, con el mismo manejo de fallo no bloqueante.

## 5. Búsqueda

- [ ] 5.1 Método en `TranscriptionRepository` para buscar por similitud coseno (`ORDER BY embedding <=> :queryEmbedding LIMIT :limit`), excluyendo `Transcription` con `embedding IS NULL`.
- [ ] 5.2 `SearchController` + ruta protegida por login: recibe la query, genera su embedding vía `EmbeddingGeneratorInterface`, y renderiza los resultados.
- [ ] 5.3 Vista Twig de Búsqueda (input + lista de resultados con enlace al día en Historial/Diario).
- [ ] 5.4 Añadir la entrada "Búsqueda" a la navegación existente.

## 6. Comando de backfill

- [ ] 6.1 `bin/console app:transcription:backfill-embeddings` (siguiendo el patrón de `app:audio:retry-transcription`): regenera el embedding de las `Transcription` con `embedding IS NULL`.
- [ ] 6.2 Añadir shortcut `make embeddings-backfill` en el Makefile.

## 7. Tests

- [ ] 7.1 Test de `OllamaEmbeddingGenerator` (mock HTTP, éxito y fallo).
- [ ] 7.2 Test de generación de embedding en `TranscribeAudioMessageHandler` (éxito y fallo no bloqueante).
- [ ] 7.3 Test de regeneración de embedding en `TranscriptionEditor::applyManualEdit`.
- [ ] 7.4 Test de `TranscriptionRepository` de búsqueda por similitud (con datos de test insertados con embeddings conocidos).
- [ ] 7.5 Test funcional de `SearchController` (con y sin resultados).
- [ ] 7.6 Test del comando de backfill.

## 8. Verificación

- [ ] 8.1 `make cs-check` y `make test` en verde.
- [ ] 8.2 Probar manualmente: levantar el stack con la nueva imagen de Postgres, comprobar que `docker compose up` no rompe datos existentes, hacer una búsqueda real.
- [ ] 8.3 Actualizar `Especificaciones.md` con el nuevo flujo de búsqueda semántica y el cambio de imagen de PostgreSQL.
