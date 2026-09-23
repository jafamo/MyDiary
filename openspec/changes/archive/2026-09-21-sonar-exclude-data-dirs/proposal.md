## Why

Al ejecutar `sonar-scanner` manualmente con `-Dsonar.sources=.` (pisando el `sonar.sources=src` de `sonar-project.properties`, ya que las propiedades de línea de comandos tienen prioridad), el escáner intentó recorrer `data/postgres`, un bind mount de Docker con permisos `700` propiedad de `root`, y falló con `AccessDeniedException`. Aunque el workflow de CI usa `sonar.sources=src` sin pisarlo y no debería verse afectado, conviene que `sonar-project.properties` sea robusto también ante un `sonar.sources=.` manual, y de paso limpiar el directorio `.scannerwork/` que generó esa ejecución local (sin trackear en git).

## What Changes

- `sonar-project.properties`: añade `data/`, `logs/` y `.scannerwork/` a `sonar.exclusions`, para que un análisis desde la raíz (`sonar.sources=.`) no falle por los bind mounts de Docker.
- `.gitignore`: añade `.scannerwork/` (directorio de trabajo local del scanner, generado al ejecutar `sonar-scanner` manualmente).

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `static-analysis`: amplía las exclusiones de `sonar-project.properties` para cubrir también `data/`, `logs/` y `.scannerwork/`.

## Impact

- **Ficheros afectados**: `sonar-project.properties`, `.gitignore`.
- Sin impacto en el workflow de CI (ya usaba `sonar.sources=src`, no `.`), pero hace la configuración más robusta ante ejecuciones manuales.
