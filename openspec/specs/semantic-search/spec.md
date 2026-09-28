## Purpose

Búsqueda en lenguaje natural sobre transcripciones y resúmenes diarios, basada en embeddings vectoriales y similitud coseno vía pgvector, más una búsqueda por palabras (sin distinguir mayúsculas ni tildes) sobre los recordatorios.

## Requirements
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
</content>

### Requirement: Búsqueda por palabras en recordatorios
La vista de Búsqueda SHALL buscar también en el texto de los recordatorios (`Reminder.text`) por coincidencia de subcadena, sin distinguir mayúsculas/minúsculas ni tildes. Los recordatorios que coinciden SHALL mostrarse en una sección propia "Recordatorios", situada encima de los resultados de transcripciones y resúmenes diarios y separada de ellos (no se mezclan en el ranking por similitud). Cada recordatorio SHALL mostrar su fecha, su hora si la tiene y su texto, y SHALL enlazar al día correspondiente en `/recordatorios`. Los recordatorios SHALL ordenarse por fecha descendente y limitarse a 20.

#### Scenario: Recordatorio con la palabra buscada
- **WHEN** existe un recordatorio con texto "Cita con el dentista" y el usuario busca "dentista"
- **THEN** la sección "Recordatorios" muestra ese recordatorio con su fecha, su hora (si tiene) y un enlace a su día en `/recordatorios`

#### Scenario: Coincidencia sin distinguir mayúsculas ni tildes
- **WHEN** existe un recordatorio con texto "Llamar al Médico" y el usuario busca "medico"
- **THEN** ese recordatorio aparece en la sección "Recordatorios"

#### Scenario: Ningún recordatorio coincide
- **WHEN** ningún recordatorio contiene el texto buscado
- **THEN** la sección "Recordatorios" no se muestra y los resultados semánticos se muestran como hasta ahora

#### Scenario: Recordatorios sin resultados semánticos
- **WHEN** hay recordatorios que coinciden pero ninguna transcripción ni resumen diario con embedding
- **THEN** la vista muestra la sección "Recordatorios" y no muestra el mensaje de "Sin resultados"

#### Scenario: Caracteres comodín en la consulta
- **WHEN** el usuario busca un texto que contiene `%` o `_`
- **THEN** esos caracteres se buscan de forma literal, no como comodines

### Requirement: La búsqueda de recordatorios no depende del embedding de la consulta
La búsqueda en recordatorios SHALL ejecutarse aunque falle la generación del embedding de la consulta (p. ej. Ollama inaccesible). En ese caso la vista SHALL mostrar los recordatorios que coinciden, sin resultados semánticos, y el fallo SHALL seguir registrándose en el log como hasta ahora.

#### Scenario: Ollama caído
- **WHEN** la generación del embedding de la consulta falla y existe un recordatorio que contiene el texto buscado
- **THEN** la vista muestra ese recordatorio en la sección "Recordatorios" y se registra el evento `search.embedding_generation_failed`

