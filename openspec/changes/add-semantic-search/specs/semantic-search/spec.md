## ADDED Requirements

### Requirement: Búsqueda en lenguaje natural sobre transcripciones y resúmenes diarios
El sistema SHALL ofrecer una vista de Búsqueda, accesible tras login, donde el usuario introduce una consulta en lenguaje natural. El sistema SHALL generar el embedding de la consulta y SHALL devolver una lista combinada de `Transcription` y `DailySummary` con embedding guardado, ordenada por similitud coseno descendente (más similares primero), enlazando cada resultado al día correspondiente en Historial/Diario e indicando de qué tipo es (transcripción individual o resumen del día).

#### Scenario: Búsqueda con resultados de transcripciones
- **WHEN** el usuario busca "dinero" y existen transcripciones semánticamente relacionadas aunque no contengan la palabra literal (p. ej. una que dice "presupuesto")
- **THEN** el sistema devuelve esas transcripciones entre los resultados, ordenadas por similitud

#### Scenario: Búsqueda con resultados de resúmenes diarios
- **WHEN** el usuario busca "dinero" y existe un `DailySummary` semánticamente relacionado aunque no contenga la palabra literal
- **THEN** el sistema devuelve ese resumen diario entre los resultados, ordenado por similitud junto con las transcripciones, e identificado como resumen del día

#### Scenario: Búsqueda sin resultados relevantes
- **WHEN** no existe ninguna `Transcription` ni `DailySummary` con embedding guardado
- **THEN** el sistema muestra la vista de Búsqueda sin resultados, sin error

### Requirement: Solo se buscan registros con embedding disponible
El sistema SHALL excluir de los resultados de búsqueda cualquier `Transcription` o `DailySummary` sin embedding guardado (p. ej. por fallo de generación en su momento), sin fallar la búsqueda por su ausencia.

#### Scenario: Transcripción sin embedding no aparece en resultados
- **WHEN** existe una `Transcription` cuyo embedding no se pudo generar (campo `embedding` nulo)
- **THEN** esa `Transcription` no aparece en ningún resultado de búsqueda, y el resto de la búsqueda funciona con normalidad

#### Scenario: Resumen diario sin embedding no aparece en resultados
- **WHEN** existe un `DailySummary` cuyo embedding no se pudo generar (campo `embedding` nulo)
- **THEN** ese `DailySummary` no aparece en ningún resultado de búsqueda, y el resto de la búsqueda funciona con normalidad
