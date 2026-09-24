## Context

El proyecto ya usa `sonar-project.properties` (projectKey `MyDiary`, `sources=src`, `qualitygate.wait=true`) y un workflow de GitHub Actions con los secrets `SONAR_HOST_URL`/`SONAR_TOKEN`. En local no hay forma documentada de lanzar el análisis.

## Goals / Non-Goals

**Goals:**
- `SONAR_TOKEN=xxx make sonar` lanza el análisis contra el SonarQube self-hosted y espera al quality gate.
- No requerir `sonar-scanner` instalado en el host.

**Non-Goals:**
- Cobertura de PHPUnit en el análisis.
- Guardar el token en ningún fichero del repo.

## Decisions

- **Imagen Docker `sonarsource/sonar-scanner-cli` con `docker run --rm`** en vez de un servicio en `docker-compose.yml`: es una herramienta puntual, no parte del stack de la app. Alternativa descartada: exigir `sonar-scanner` instalado en el host.
- **`--user $(id -u):$(id -g)`**: evita que `.scannerwork/` quede con propietario distinto al usuario del host.
- **URL por defecto con `?=`**: el servidor es único y no es secreto; se puede sobrescribir (`SONAR_HOST_URL=... make sonar`).
- **Token solo desde entorno**, con comprobación previa (`test -n`) y mensaje de error explícito, en lugar de dejar que el escáner falle con un 401 poco claro.

## Risks / Trade-offs

- [El token queda en el historial de la shell si se pasa inline] → Recomendar `export SONAR_TOKEN=...` desde un fichero no versionado o un gestor de secretos; es decisión del usuario.
- [Bind mounts `data/`/`logs/` no legibles] → Ya cubierto por `sonar.exclusions` y `sources=src`.
