## Why

El análisis de SonarQube solo se ejecuta en CI (GitHub Actions). Para lanzarlo en local hay que recordar el comando completo del escáner, la URL del servidor y cómo pasar el token, y no está documentado en ningún sitio del repo. Un target `make sonar` lo deja a un comando, igual que `make test` o `make cs-check`.

## What Changes

- Nuevo target `sonar` en el `Makefile` que ejecuta el análisis con la imagen Docker `sonarsource/sonar-scanner-cli` (sin instalar nada en el host), montando la raíz del repo.
- `SONAR_HOST_URL` con valor por defecto `https://sonarqube.jfarinos.keenetic.pro`, sobrescribible desde el entorno o la línea de `make`.
- `SONAR_TOKEN` obligatorio vía variable de entorno; si falta, el target falla con un mensaje claro antes de lanzar el contenedor. El token nunca se escribe en ficheros versionados.
- `sonar-project.properties` no cambia (sigue sin URL ni token).

## Capabilities

### New Capabilities

### Modified Capabilities
- `static-analysis`: se añade el requisito de poder ejecutar el análisis en local mediante `make sonar`.

## Impact

- **Ficheros modificados**: `Makefile`.
- **Dependencias**: Docker en el host (ya requerido por el proyecto); descarga de la imagen `sonarsource/sonar-scanner-cli` la primera vez.
- CI no cambia.
