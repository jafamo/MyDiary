## Why

El repositorio ya tiene PSR-12 (`make cs-check`/`cs-fix`) y tests (`make test`), pero ningún análisis estático de calidad (bugs, code smells, duplicación, complejidad). El usuario ha creado un proyecto en su SonarQube propio (self-hosted, `projectKey=MyDiary`) y quiere conectarlo al repo.

## What Changes

- Nuevo fichero `sonar-project.properties` en la raíz: `projectKey`, `qualitygate.wait=true`, `sources=src`, `tests=tests`, exclusiones de `vendor/`, `var/` y `migrations/` (autogeneradas).
- Nuevo workflow de GitHub Actions (`.github/workflows/sonarqube.yml`) que ejecuta el análisis en cada push/PR, usando `SONAR_HOST_URL` y `SONAR_TOKEN` como secrets del repo (sin hardcodear ni URL ni token). Es el primer workflow de CI del repo.
- Solo análisis estático por ahora: sin cobertura de PHPUnit (`sonar.php.coverage.reportPaths`), se puede añadir después si aporta valor.

## Capabilities

### New Capabilities
- `static-analysis`: análisis estático de calidad de código vía SonarQube, ejecutado en CI, con quality gate bloqueante.

### Modified Capabilities
(ninguna)

## Impact

- **Ficheros nuevos**: `sonar-project.properties`, `.github/workflows/sonarqube.yml`.
- **Requiere configuración fuera del repo**: secrets `SONAR_HOST_URL` y `SONAR_TOKEN` en GitHub (Settings → Secrets and variables → Actions), a cargo del usuario — no se puede automatizar desde aquí.
- **Sin impacto en el código de la aplicación** ni en `make test`/`make deploy`.
