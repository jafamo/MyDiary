## Context

No hay `.github/workflows/` en el repo todavía. El proyecto en SonarQube ya existe (`projectKey=MyDiary`, `sonar.qualitygate.wait=true` pedidos por el usuario). El servidor es self-hosted (no SonarCloud), así que no requiere `sonar.organization`, pero sí necesita conocer la URL del servidor.

## Goals / Non-Goals

**Goals:**
- Que cada push/PR dispare un análisis de SonarQube y el resultado (incluido el quality gate) quede visible en GitHub Actions.
- No commitear secretos: ni token, ni URL interna del servidor si se considera sensible.

**Non-Goals:**
- Cobertura de PHPUnit en el análisis (fuera de alcance de este cambio, ver `proposal.md`).
- Bloquear el merge de PRs en base al quality gate (GitHub branch protection) — no se pide y añadiría fricción no solicitada; se puede proponer aparte si se quiere.

## Decisions

- **`sonar.host.url` no va en `sonar-project.properties`**, sino como variable de entorno `SONAR_HOST_URL` en el workflow, leída de un secret de GitHub. Motivo: es una URL de un servidor personal/doméstico; aunque no es tan sensible como un token, mantenerla fuera del repo evita exponer infraestructura privada en un repositorio que podría hacerse público en el futuro, y sigue el mismo patrón que el token (una sola fuente de configuración: secrets de GitHub).
- **`sonar.token` tampoco va en el fichero de properties**: se pasa como variable de entorno `SONAR_TOKEN`, que `sonar-scanner` reconoce automáticamente. Nunca se commitea.
- **Acción de GitHub usada**: `sonarsource/sonarqube-scan-action@v4` (acción oficial de SonarSource), que ya sabe leer `SONAR_HOST_URL` y `SONAR_TOKEN` del entorno sin configuración adicional. Evita mantener a mano la instalación del CLI `sonar-scanner`.
- **Exclusiones**: `vendor/**` (dependencias de Composer), `var/**` (cache/logs generados), `migrations/**` (migraciones de Doctrine autogeneradas, poco valor analizarlas). `public/build/**` no existe en este proyecto (sin bundler de assets), así que no se excluye.
- **Trigger del workflow**: `push` a `main`/`develop` y `pull_request` contra cualquier rama, para cubrir tanto el flujo Git Flow (`feature/*` → PR opcional, o push directo a `develop`) como las releases hacia `main`.

## Risks / Trade-offs

- [Si no se configuran los secrets `SONAR_HOST_URL`/`SONAR_TOKEN` en GitHub, el workflow fallará en rojo permanentemente] → Mitigación: se documenta explícitamente como paso manual a cargo del usuario en `tasks.md`; no se puede automatizar desde este cambio.
- [El servidor SonarQube self-hosted podría no ser alcanzable desde los runners de GitHub Actions (red doméstica/NAT) si no está expuesto públicamente] → Mitigación: fuera del alcance de este cambio verificarlo (depende de la red del usuario); si falla por conectividad, se revisará como incidencia aparte.

## Migration Plan

No aplica migración de datos. Cambio aditivo (ficheros nuevos), no toca código de la aplicación.
