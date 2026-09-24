## Purpose

Análisis estático de calidad de código (bugs, code smells, duplicación) vía SonarQube self-hosted, ejecutado automáticamente en CI con quality gate bloqueante.
## Requirements
### Requirement: Configuración de proyecto SonarQube
El sistema SHALL definir `sonar-project.properties` en la raíz del repositorio con `sonar.projectKey=MyDiary`, `sonar.qualitygate.wait=true`, `sonar.sources=src`, `sonar.tests=tests`, y exclusiones de `vendor/`, `var/`, `migrations/`, `data/`, `logs/` y `.scannerwork/`. El fichero SHALL NOT contener `sonar.host.url` ni `sonar.token`.

#### Scenario: Propiedades del proyecto presentes
- **WHEN** se inspecciona `sonar-project.properties`
- **THEN** contiene `sonar.projectKey=MyDiary` y `sonar.qualitygate.wait=true`, sin ninguna URL de servidor ni token

#### Scenario: Análisis manual desde la raíz no falla por bind mounts de Docker
- **WHEN** se ejecuta `sonar-scanner` con `sonar.sources=.` desde la raíz del repositorio
- **THEN** el escáner no intenta indexar `data/` ni `logs/` (bind mounts de Docker con permisos restringidos), y no falla con `AccessDeniedException`

### Requirement: Análisis automático en CI
El sistema SHALL ejecutar el análisis de SonarQube mediante un workflow de GitHub Actions en cada `push` a `main`/`develop` y en cada `pull_request`, usando los secrets `SONAR_HOST_URL` y `SONAR_TOKEN` del repositorio para autenticar contra el servidor SonarQube self-hosted.

#### Scenario: Push a develop dispara el análisis
- **WHEN** se hace push a `develop`
- **THEN** se ejecuta el workflow de SonarQube, que analiza el código y reporta el resultado (incluido el quality gate) en GitHub Actions

#### Scenario: Pull request dispara el análisis
- **WHEN** se abre o actualiza un pull request
- **THEN** se ejecuta el workflow de SonarQube sobre el código de esa rama

### Requirement: Análisis manual en local vía Makefile
El sistema SHALL proporcionar un target `make sonar` que ejecute el análisis de SonarQube sobre el repositorio usando la imagen Docker `sonarsource/sonar-scanner-cli`, sin requerir `sonar-scanner` instalado en el host. `SONAR_HOST_URL` SHALL tener como valor por defecto `https://sonarqube.jfarinos.keenetic.pro` y SHALL poder sobrescribirse. `SONAR_TOKEN` SHALL leerse de la variable de entorno y SHALL NOT escribirse en ningún fichero versionado.

#### Scenario: Análisis con token
- **WHEN** se ejecuta `SONAR_TOKEN=<token> make sonar` desde la raíz del repositorio
- **THEN** se lanza el escáner contra `https://sonarqube.jfarinos.keenetic.pro` con el projectKey `MyDiary` y se espera al resultado del quality gate

#### Scenario: Falta el token
- **WHEN** se ejecuta `make sonar` sin `SONAR_TOKEN` definido
- **THEN** el target termina con error y un mensaje indicando que hay que definir `SONAR_TOKEN`, sin lanzar el contenedor

#### Scenario: URL alternativa
- **WHEN** se ejecuta `SONAR_HOST_URL=<otra-url> SONAR_TOKEN=<token> make sonar`
- **THEN** el análisis se envía a `<otra-url>`

