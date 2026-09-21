## Why

Los `Topic` se crean automáticamente al generar cada `DailySummary` (vía LLM), sin ninguna forma de corregirlos después: no se pueden fusionar duplicados con nombres distintos para el mismo tema (p. ej. "trabajo" vs "curro") ni renombrar uno mal etiquetado. Con el tiempo esto ensucia el ranking de temas en Estadísticas y las etiquetas mostradas en Resúmenes.

## What Changes

- Nueva vista de gestión de temas (`/topics`) que lista todos los `Topic` existentes con su frecuencia de uso (número de `DailySummary` asociados).
- Acción de **renombrar** un `Topic`: cambia su `name`, validando que no colisione con otro `Topic` ya existente (case-insensitive, para evitar duplicados tipo "Trabajo" / "trabajo").
- Acción de **fusionar** dos o más `Topic` en uno superviviente: reasigna todas las relaciones `DailySummary` de los temas fusionados al superviviente (sin duplicar relaciones si un `DailySummary` ya estaba vinculado a ambos) y elimina los `Topic` fusionados.
- Sin nuevo CRUD genérico ni EasyAdmin: controlador Symfony + `FormType` normales, siguiendo el patrón de las vistas custom existentes (Diario, Historial, Estadísticas).

## Capabilities

### New Capabilities
- `topic-management`: gestión manual de `Topic` — listado con frecuencia de uso, renombrado y fusión de duplicados, con reasignación de sus `DailySummary` asociados.

### Modified Capabilities
(ninguna — no cambia el comportamiento de generación automática de `Topic` en `daily-summary-generation`, ni los requisitos ya definidos en `summaries-view`; solo se añade una gestión posterior sobre los datos existentes)

## Impact

- **Nuevo código**: `TopicController` (o similar) + plantillas Twig para listado/renombrado/fusión; posible `TopicMergeType`/`TopicRenameType` (`FormType`).
- **`TopicRepository`**: nuevo método para frecuencia de uso por `Topic` (reutilizable o similar a `findTopicFrequencyInRange`), y lógica de fusión (reasignar filas de la tabla pivote `daily_summary_topic` y borrar los `Topic` sobrantes).
- **Navegación**: añadir enlace a `/topics` en el menú existente (junto a Diario, Historial, Estadísticas, Resúmenes).
- **Sin cambios** en `DailySummaryService` (generación automática de `Topic` sigue igual) ni en el modelo de datos (`Topic`/`DailySummary` ya soportan N:M).
