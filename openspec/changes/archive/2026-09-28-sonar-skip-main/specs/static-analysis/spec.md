## MODIFIED Requirements

### Requirement: Análisis automático en CI
El sistema SHALL ejecutar el análisis de SonarQube mediante un job del workflow de CI de GitHub Actions en cada `push` a `develop` y en cada `pull_request`, usando los secrets `SONAR_HOST_URL` y `SONAR_TOKEN` del repositorio para autenticar contra el servidor SonarQube self-hosted. El job SHALL NOT ejecutarse en los `push` a `main`: SonarQube Community no distingue ramas y `main` solo recibe código ya analizado en `develop`. El job SHALL ejecutarse solo después de que terminen con éxito los jobs de estilo, PHPStan y tests, y SHALL enviar a SonarQube el informe de cobertura (`coverage.xml`) generado por el job de tests.

#### Scenario: Push a develop dispara el análisis
- **WHEN** se hace push a `develop` y los jobs de estilo, PHPStan y tests terminan con éxito
- **THEN** se ejecuta el análisis de SonarQube con la cobertura de los tests y se reporta el resultado (incluido el quality gate) en GitHub Actions

#### Scenario: Pull request dispara el análisis
- **WHEN** se abre o actualiza un pull request y los jobs previos terminan con éxito
- **THEN** se ejecuta el análisis de SonarQube sobre el código de esa rama

#### Scenario: Push a main no dispara el análisis
- **WHEN** se hace push a `main` (p. ej. al terminar una release o un hotfix)
- **THEN** se ejecutan los jobs de estilo, PHPStan y tests, y el job de SonarQube se omite

#### Scenario: Tests fallidos bloquean el análisis
- **WHEN** falla el job de tests (o el de estilo, o el de PHPStan)
- **THEN** el análisis de SonarQube no se ejecuta
