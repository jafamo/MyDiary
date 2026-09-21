## MODIFIED Requirements

### Requirement: Configuración de proyecto SonarQube
El sistema SHALL definir `sonar-project.properties` en la raíz del repositorio con `sonar.projectKey=MyDiary`, `sonar.qualitygate.wait=true`, `sonar.sources=src`, `sonar.tests=tests`, y exclusiones de `vendor/`, `var/`, `migrations/`, `data/`, `logs/` y `.scannerwork/`. El fichero SHALL NOT contener `sonar.host.url` ni `sonar.token`.

#### Scenario: Propiedades del proyecto presentes
- **WHEN** se inspecciona `sonar-project.properties`
- **THEN** contiene `sonar.projectKey=MyDiary` y `sonar.qualitygate.wait=true`, sin ninguna URL de servidor ni token

#### Scenario: Análisis manual desde la raíz no falla por bind mounts de Docker
- **WHEN** se ejecuta `sonar-scanner` con `sonar.sources=.` desde la raíz del repositorio
- **THEN** el escáner no intenta indexar `data/` ni `logs/` (bind mounts de Docker con permisos restringidos), y no falla con `AccessDeniedException`
