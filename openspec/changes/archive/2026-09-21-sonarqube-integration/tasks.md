## 1. Configuración de Sonar

- [x] 1.1 Crear `sonar-project.properties` con `projectKey`, `qualitygate.wait`, `sources`, `tests` y exclusiones de `vendor/`, `var/`, `migrations/`.

## 2. Workflow de CI

- [x] 2.1 Crear `.github/workflows/sonarqube.yml` con triggers `push` (`main`, `develop`) y `pull_request`, checkout con `fetch-depth: 0` (requerido por Sonar para blame/histórico) y el step `sonarsource/sonarqube-scan-action@v4` usando `SONAR_HOST_URL`/`SONAR_TOKEN` desde secrets.

## 3. Verificación

- [x] 3.1 Validar la sintaxis del YAML del workflow. (`python3 -c "import yaml; yaml.safe_load(...)"` → válido.)
- [x] 3.2 Documentar en el resumen de la tarea que faltan los secrets `SONAR_HOST_URL` y `SONAR_TOKEN` en GitHub (paso manual del usuario, no automatizable desde aquí).
