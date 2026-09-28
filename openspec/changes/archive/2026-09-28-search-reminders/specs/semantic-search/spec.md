## ADDED Requirements

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
