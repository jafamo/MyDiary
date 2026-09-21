## Context

`Topic` (`src/Entity/Topic.php`) tiene solo `id` y `name` (unique), y una relación N:M inversa con `DailySummary` a través de la tabla pivote `daily_summary_topic` (propietaria en `DailySummary::$topics`). Los `Topic` se crean automáticamente en `DailySummaryService::generate()` (`src/Service/DailySummaryService.php:150-163`): por cada resumen generado se limpian los `Topic` previos del `DailySummary` y se buscan/crean vía `TopicRepository::findOneByName()` a partir de los nombres devueltos por el LLM. No hay ningún flujo manual sobre `Topic` hoy.

El proyecto sigue el patrón de vistas custom (controlador Symfony + `FormType`, sin EasyAdmin ni CRUD genérico) ya usado en Diario/Historial/Estadísticas/Resúmenes/Recordatorios.

## Goals / Non-Goals

**Goals:**
- Listar los `Topic` existentes con su número de `DailySummary` asociados (frecuencia de uso), para identificar duplicados a simple vista.
- Permitir renombrar un `Topic` sin duplicar nombres (comparación case-insensitive).
- Permitir fusionar 2+ `Topic` en uno superviviente, reasignando sus `DailySummary` sin duplicar filas de la tabla pivote, y borrando los `Topic` fusionados.

**Non-Goals:**
- No se toca la generación automática de `Topic` en `DailySummaryService` (sigue creando por nombre exacto vía LLM).
- No se añade fusión/renombrado automático (sin heurísticas de similitud ni sugerencias); es una acción manual explícita del usuario.
- No se relaciona `Topic` directamente con `Transcription` (la relación sigue siendo solo con `DailySummary`, como hoy).

## Decisions

- **Vista y ruta**: `TopicController` con `#[Route('/topics', name: 'app_topics')]` (listado, `GET`) y acciones POST para renombrar (`/topics/{id}/renombrar`) y fusionar (`/topics/fusionar`). Sigue el patrón de un controlador por vista + `FormType` normal (p. ej. `TranscriptionEditType`), no un CRUD genérico.
- **Renombrado**: `FormType` simple (`TopicRenameType`) con un campo `name`. Validación de unicidad case-insensitive en el controlador/servicio (no solo la constraint `UNIQUE` de BD, que en MySQL/MariaDB con collation `*_ci` ya es case-insensitive por defecto — se confirma la collation de la columna `name` antes de implementar; si no lo es, se añade validación explícita comparando en minúsculas).
- **Fusión**: formulario multi-select (`FormType` con `EntityType` de `Topic`, `multiple: true`) para elegir los `Topic` origen + un `Topic` destino (superviviente). La lógica de fusión vive en un método nuevo del `TopicRepository` o en un pequeño `TopicMerger` (servicio de aplicación, no un bus/handler): para cada `DailySummary` de cada `Topic` origen, `addTopic($destino)` si no lo tiene ya (la propia `Collection` de Doctrine evita duplicados por `contains()`), y al final se hace `remove()` de los `Topic` origen (Doctrine limpia las filas pivote automáticamente al eliminar la entidad propietaria del lado inverso, dado el `mappedBy` en `Topic::$dailySummaries`... revisar en implementación si hace falta `removeTopic` explícito por cada `DailySummary` antes del `remove()`, ya que el lado propietario de la relación es `DailySummary`).
- **Sin nueva entidad ni migración de esquema**: el modelo de datos actual (`Topic` + tabla pivote) ya soporta todo lo necesario; solo se añade UI/lógica sobre datos existentes.
- **Navegación**: nuevo enlace "Temas" en `templates/base.html.twig` (sidebar + bottom-nav), junto a los existentes.

## Risks / Trade-offs

- [Fusionar con `DailySummary` ya vinculado a ambos temas origen y destino] → Mitigación: usar `Collection::contains()` (vía `addTopic`, que ya es idempotente) antes de añadir, para no duplicar la fila pivote ni fallar por constraint.
- [Renombrar a un nombre que ya existe] → Mitigación: validación explícita case-insensitive antes de persistir, con mensaje de error claro sugiriendo fusionar en vez de renombrar.
- [Fusión irreversible (se listan los `Topic` origen)] → Mitigación: la vista de fusión muestra una confirmación explícita con los nombres y el conteo de resúmenes afectados antes de ejecutar.

## Migration Plan

No aplica migración de base de datos (sin cambios de esquema). Se despliega como una feature más: `git flow feature finish` → merge a `develop` → siguiente release.

## Open Questions

- Confirmar en implementación si la collation de la columna `topic.name` en MySQL/MariaDB ya es case-insensitive (afecta si la validación de duplicados al renombrar necesita lógica explícita en PHP o basta con la constraint de BD).
