## ADDED Requirements

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
