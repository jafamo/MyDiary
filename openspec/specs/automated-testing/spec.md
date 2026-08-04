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
