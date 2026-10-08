## ADDED Requirements

### Requirement: Tests unitarios de los servicios de las vistas
El sistema SHALL incluir tests unitarios para los servicios que calculan los datos de las vistas Estadísticas, Historial, Recordatorios y Búsqueda (`EstadisticasService`, `HistorialService`, `RecordatoriosService`, `SearchService`) y para sus helpers (`MonthGrid`, `TokensChartBuilder`). Esos tests SHALL ejercitar los servicios directamente, sin pasar por una petición HTTP, y SHALL fijar la fecha de referencia («hoy») de forma explícita en lugar de depender del reloj.

#### Scenario: Racha y día récord del rango
- **WHEN** se calcula el resumen de Estadísticas para un rango cuyas cuentas diarias de audios son 1, 2, 0, 3, 3
- **THEN** la racha actual es 2, la mejor racha es 2 y el día récord es el cuarto día (el más antiguo de los empatados)

#### Scenario: Rango personalizado inválido
- **WHEN** se resuelve el rango `custom` con una fecha de inicio posterior a la de fin
- **THEN** el rango efectivo son los 30 días que terminan en la fecha de referencia

#### Scenario: Rejilla de un mes
- **WHEN** se construye la rejilla de octubre de 2026
- **THEN** tiene 5 semanas de 7 días, empieza el lunes 28 de septiembre, termina el domingo 1 de noviembre y los días de septiembre y noviembre están marcados como de relleno

#### Scenario: Fusión de resultados de búsqueda
- **WHEN** la búsqueda obtiene transcripciones y resúmenes con distintas distancias
- **THEN** el servicio devuelve una única lista ordenada por distancia ascendente y limitada al máximo de resultados

#### Scenario: Fallo del embedding en la búsqueda
- **WHEN** el generador de embeddings lanza una `EmbeddingGenerationException`
- **THEN** el servicio devuelve una lista de resultados semánticos vacía y sigue devolviendo los recordatorios que coinciden por texto

#### Scenario: Paginación fuera de rango
- **WHEN** se pide una página de recordatorios próximos mayor que el número total de páginas
- **THEN** el servicio devuelve la última página existente
