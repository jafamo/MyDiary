## ADDED Requirements

### Requirement: Configuración de proyecto SonarQube
El sistema SHALL definir `sonar-project.properties` en la raíz del repositorio con `sonar.projectKey=MyDiary`, `sonar.qualitygate.wait=true`, `sonar.sources=src`, `sonar.tests=tests`, y exclusiones de `vendor/`, `var/` y `migrations/`. El fichero SHALL NOT contener `sonar.host.url` ni `sonar.token`.

#### Scenario: Propiedades del proyecto presentes
- **WHEN** se inspecciona `sonar-project.properties`
- **THEN** contiene `sonar.projectKey=MyDiary` y `sonar.qualitygate.wait=true`, sin ninguna URL de servidor ni token

### Requirement: Análisis automático en CI
El sistema SHALL ejecutar el análisis de SonarQube mediante un workflow de GitHub Actions en cada `push` a `main`/`develop` y en cada `pull_request`, usando los secrets `SONAR_HOST_URL` y `SONAR_TOKEN` del repositorio para autenticar contra el servidor SonarQube self-hosted.

#### Scenario: Push a develop dispara el análisis
- **WHEN** se hace push a `develop`
- **THEN** se ejecuta el workflow de SonarQube, que analiza el código y reporta el resultado (incluido el quality gate) en GitHub Actions

#### Scenario: Pull request dispara el análisis
- **WHEN** se abre o actualiza un pull request
- **THEN** se ejecuta el workflow de SonarQube sobre el código de esa rama
