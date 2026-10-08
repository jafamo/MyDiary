## Purpose

Infraestructura de tests unitarios del proyecto: PHPUnit configurado, base de datos de test separada, y los tests que cubren el código ya implementado.
## Requirements
### Requirement: PHPUnit configurado vía `symfony/test-pack`
El sistema SHALL incluir `symfony/test-pack` como dependencia de desarrollo, con PHPUnit ejecutable dentro de `diary-php`.

#### Scenario: Ejecutar el suite completo
- **WHEN** se ejecuta `make test`
- **THEN** PHPUnit corre dentro de `diary-php` y reporta el resultado de todos los tests

### Requirement: Base de datos de test separada
El sistema SHALL usar una base de datos de test (`telegram_notes_test`) distinta de la de desarrollo, en la misma instancia de `diary-postgres`, sin afectar los datos de desarrollo.

#### Scenario: Tests no afectan datos de desarrollo
- **WHEN** se ejecuta `make test` y los tests de comandos insertan/borran registros en `app_user`
- **THEN** la tabla `app_user` de la base de datos de desarrollo (`telegram_notes`) no se ve afectada

### Requirement: Tests unitarios de entidades
El sistema SHALL incluir tests unitarios para las entidades `AudioRecording`, `Transcription`, `Topic`, `DailySummary`, `User` y el enum `AudioRecordingStatus`, cubriendo su comportamiento no trivial.

#### Scenario: `User::getRoles()` siempre incluye ROLE_USER
- **WHEN** se instancia un `User` sin roles asignados y se llama a `getRoles()`
- **THEN** el resultado incluye `ROLE_USER`

#### Scenario: `DailySummary` no duplica temas
- **WHEN** se llama `addTopic()` dos veces con el mismo `Topic` sobre un `DailySummary`
- **THEN** el topic aparece una sola vez en `getTopics()`

### Requirement: Tests de los comandos de gestión de usuarios
El sistema SHALL incluir tests, vía `CommandTester`, para `app:user:create` y `app:user:change-password` contra la base de datos de test.

#### Scenario: Crear usuario por consola
- **WHEN** se ejecuta el test del comando `app:user:create` con un username nuevo
- **THEN** se persiste un `User` en `app_user` (BD de test) con `password_hash` distinto de la contraseña en claro

#### Scenario: Cambiar contraseña sin conocer la actual
- **WHEN** se ejecuta el test del comando `app:user:change-password` sobre un usuario existente
- **THEN** el `password_hash` cambia sin que el test haya provisto la contraseña anterior

### Requirement: Comando `make test`
El sistema SHALL proveer un target `make test` en el `Makefile` que ejecuta el suite de PHPUnit dentro de `diary-php`.

#### Scenario: Uso básico
- **WHEN** el usuario ejecuta `make test`
- **THEN** se ejecuta `bin/phpunit` dentro del contenedor `diary-php` y el resultado se muestra en la terminal del host

### Requirement: Ejecución del suite en CI
El sistema SHALL ejecutar el suite completo de PHPUnit en GitHub Actions en cada `push` a `main`/`develop` y en cada `pull_request`, contra un PostgreSQL con la extensión pgvector levantado como service container del job. La base de datos de test (`telegram_notes_test`) SHALL crearse y migrarse dentro del propio job antes de ejecutar los tests. El job SHALL fallar si falla algún test.

#### Scenario: Suite en verde
- **WHEN** se hace push a `develop` y todos los tests pasan
- **THEN** el job de tests termina con éxito

#### Scenario: Test en rojo
- **WHEN** se abre un pull request con un cambio que hace fallar un test
- **THEN** el job de tests falla y el log muestra qué test ha fallado

### Requirement: Informe de cobertura en CI
El job de tests SHALL generar un informe de cobertura en formato Clover (`coverage.xml`) del código de `src/` y SHALL publicarlo como artefacto del workflow para que lo usen los jobs posteriores.

#### Scenario: Cobertura disponible tras los tests
- **WHEN** el job de tests termina con éxito
- **THEN** existe un artefacto del workflow que contiene `coverage.xml` en formato Clover

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

