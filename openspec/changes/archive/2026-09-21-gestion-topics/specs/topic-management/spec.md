## ADDED Requirements

### Requirement: Vista de gestión de temas
El sistema SHALL exponer una vista "Temas" (`/topics`) que lista todos los `Topic` existentes ordenados por frecuencia de uso descendente, mostrando para cada uno su `name` y el número de `DailySummary` asociados.

#### Scenario: Listado ordenado por frecuencia
- **WHEN** el usuario visita `/topics`
- **THEN** se muestran todos los `Topic`, ordenados de mayor a menor número de `DailySummary` asociados

#### Scenario: Sin temas todavía
- **WHEN** el usuario visita `/topics` y no existe ningún `Topic`
- **THEN** la vista se muestra sin error, indicando que no hay temas todavía

### Requirement: Renombrar un tema
El sistema SHALL permitir renombrar un `Topic` existente desde la vista de gestión, validando que el nuevo `name` no coincida (de forma case-insensitive) con el de otro `Topic` ya existente.

#### Scenario: Renombrado válido
- **WHEN** el usuario renombra el `Topic` "curro" a "trabajo" y no existe ya un `Topic` "trabajo"
- **THEN** el `Topic` pasa a llamarse "trabajo" y sigue asociado a los mismos `DailySummary` que antes

#### Scenario: Renombrado a un nombre duplicado
- **WHEN** el usuario intenta renombrar un `Topic` a un `name` que ya usa otro `Topic` (comparando sin distinguir mayúsculas/minúsculas)
- **THEN** el sistema rechaza el renombrado y muestra un error indicando que ya existe un tema con ese nombre

### Requirement: Fusionar temas duplicados
El sistema SHALL permitir fusionar dos o más `Topic` en un `Topic` superviviente elegido por el usuario: todos los `DailySummary` asociados a los `Topic` fusionados quedan asociados al superviviente (sin duplicar la asociación si un `DailySummary` ya estaba vinculado a ambos), y los `Topic` fusionados se eliminan.

#### Scenario: Fusión de dos temas sin solapamiento
- **WHEN** el usuario fusiona el `Topic` "curro" (asociado a los `DailySummary` A y B) dentro del `Topic` "trabajo" (asociado al `DailySummary` C)
- **THEN** "trabajo" queda asociado a A, B y C; el `Topic` "curro" deja de existir

#### Scenario: Fusión con `DailySummary` compartido
- **WHEN** el usuario fusiona el `Topic` "curro" dentro del `Topic` "trabajo" y el `DailySummary` D ya estaba asociado a ambos
- **THEN** tras la fusión, D queda asociado a "trabajo" una sola vez (sin fila duplicada ni error de constraint)

#### Scenario: Confirmación antes de fusionar
- **WHEN** el usuario inicia una fusión de temas
- **THEN** el sistema muestra los nombres de los `Topic` origen, el `Topic` destino y el número de `DailySummary` afectados, y solo ejecuta la fusión tras confirmación explícita
