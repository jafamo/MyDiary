## ADDED Requirements

### Requirement: Búsqueda en lenguaje natural sobre transcripciones
El sistema SHALL ofrecer una vista de Búsqueda, accesible tras login, donde el usuario introduce una consulta en lenguaje natural. El sistema SHALL generar el embedding de la consulta y SHALL devolver las `Transcription` con embedding guardado ordenadas por similitud coseno descendente (más similares primero), enlazando cada resultado al día correspondiente en Historial/Diario.

#### Scenario: Búsqueda con resultados
- **WHEN** el usuario busca "dinero" y existen transcripciones semánticamente relacionadas aunque no contengan la palabra literal (p. ej. una que dice "presupuesto")
- **THEN** el sistema devuelve esas transcripciones entre los resultados, ordenadas por similitud

#### Scenario: Búsqueda sin resultados relevantes
- **WHEN** no existe ninguna `Transcription` con embedding guardado
- **THEN** el sistema muestra la vista de Búsqueda sin resultados, sin error

### Requirement: Solo se buscan transcripciones con embedding disponible
El sistema SHALL excluir de los resultados de búsqueda cualquier `Transcription` sin embedding guardado (p. ej. por fallo de generación en su momento), sin fallar la búsqueda por su ausencia.

#### Scenario: Transcripción sin embedding no aparece en resultados
- **WHEN** existe una `Transcription` cuyo embedding no se pudo generar (campo `embedding` nulo)
- **THEN** esa `Transcription` no aparece en ningún resultado de búsqueda, y el resto de la búsqueda funciona con normalidad
