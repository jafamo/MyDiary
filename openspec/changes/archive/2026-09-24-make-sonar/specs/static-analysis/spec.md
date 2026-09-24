## ADDED Requirements

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
