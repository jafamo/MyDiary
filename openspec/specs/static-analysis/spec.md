## Purpose

Análisis estático de calidad de código (bugs, code smells, duplicación) vía SonarQube self-hosted, ejecutado automáticamente en CI con quality gate bloqueante.
## Requirements
### Requirement: Configuración de proyecto SonarQube
El sistema SHALL definir `sonar-project.properties` en la raíz del repositorio con `sonar.projectKey=MyDiary`, `sonar.qualitygate.wait=true`, `sonar.sources=src`, `sonar.tests=tests`, `sonar.php.coverage.reportPaths=coverage.xml`, y exclusiones de `vendor/`, `var/`, `migrations/`, `data/`, `logs/` y `.scannerwork/`. El fichero SHALL NOT contener `sonar.host.url` ni `sonar.token`.

#### Scenario: Propiedades del proyecto presentes
- **WHEN** se inspecciona `sonar-project.properties`
- **THEN** contiene `sonar.projectKey=MyDiary`, `sonar.qualitygate.wait=true` y `sonar.php.coverage.reportPaths=coverage.xml`, sin ninguna URL de servidor ni token

#### Scenario: Análisis manual desde la raíz no falla por bind mounts de Docker
- **WHEN** se ejecuta `sonar-scanner` con `sonar.sources=.` desde la raíz del repositorio
- **THEN** el escáner no intenta indexar `data/` ni `logs/` (bind mounts de Docker con permisos restringidos), y no falla con `AccessDeniedException`

#### Scenario: Análisis local sin informe de cobertura
- **WHEN** se ejecuta `make sonar` sin que exista `coverage.xml` en la raíz
- **THEN** el análisis se completa igualmente, sin datos de cobertura

### Requirement: Análisis automático en CI
El sistema SHALL ejecutar el análisis de SonarQube mediante un job del workflow de CI de GitHub Actions en cada `push` a `main`/`develop` y en cada `pull_request`, usando los secrets `SONAR_HOST_URL` y `SONAR_TOKEN` del repositorio para autenticar contra el servidor SonarQube self-hosted. El job SHALL ejecutarse solo después de que terminen con éxito los jobs de estilo, PHPStan y tests, y SHALL enviar a SonarQube el informe de cobertura (`coverage.xml`) generado por el job de tests.

#### Scenario: Push a develop dispara el análisis
- **WHEN** se hace push a `develop` y los jobs de estilo, PHPStan y tests terminan con éxito
- **THEN** se ejecuta el análisis de SonarQube con la cobertura de los tests y se reporta el resultado (incluido el quality gate) en GitHub Actions

#### Scenario: Pull request dispara el análisis
- **WHEN** se abre o actualiza un pull request y los jobs previos terminan con éxito
- **THEN** se ejecuta el análisis de SonarQube sobre el código de esa rama

#### Scenario: Tests fallidos bloquean el análisis
- **WHEN** falla el job de tests (o el de estilo, o el de PHPStan)
- **THEN** el análisis de SonarQube no se ejecuta

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

### Requirement: Análisis estático con PHPStan
El sistema SHALL incluir `phpstan/phpstan`, `phpstan/phpstan-symfony` y `phpstan/phpstan-doctrine` como dependencias de desarrollo, configurados en `phpstan.dist.neon` para analizar `src/` y `tests/` con nivel 5 y las extensiones de Symfony y Doctrine. Los errores existentes al introducir la herramienta SHALL registrarse en `phpstan-baseline.neon`, incluido desde `phpstan.dist.neon`, de modo que solo fallen los errores nuevos.

#### Scenario: Código sin errores nuevos
- **WHEN** se ejecuta PHPStan sobre un código cuyos únicos errores están en el baseline
- **THEN** PHPStan termina con código de salida 0

#### Scenario: Error nuevo
- **WHEN** se ejecuta PHPStan sobre código que introduce un error de nivel ≤ 5 que no está en el baseline (p. ej. una llamada a un método inexistente)
- **THEN** PHPStan reporta el error con fichero y línea, y termina con código de salida distinto de 0

### Requirement: PHPStan en local vía Makefile
El sistema SHALL proporcionar un target `make phpstan` que ejecute PHPStan dentro del contenedor `diary-php` con la configuración `phpstan.dist.neon`.

#### Scenario: Uso básico
- **WHEN** el usuario ejecuta `make phpstan`
- **THEN** PHPStan se ejecuta dentro de `diary-php` y el resultado se muestra en la terminal del host

### Requirement: PHPStan en CI
El sistema SHALL ejecutar PHPStan en GitHub Actions en cada `push` a `main`/`develop` y en cada `pull_request`, y el job SHALL fallar si PHPStan reporta errores.

#### Scenario: Error nuevo en un pull request
- **WHEN** se abre un pull request que introduce un error de PHPStan que no está en el baseline
- **THEN** el job de análisis falla y el log muestra el error

