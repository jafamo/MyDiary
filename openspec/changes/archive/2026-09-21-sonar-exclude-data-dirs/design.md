## Context

`data/` y `logs/` son bind mounts de Docker (ver `docker-infrastructure`), con subdirectorios propiedad de `root` o de usuarios de los contenedores (p. ej. `data/postgres` con permisos `700`), no legibles por el usuario que ejecuta `sonar-scanner` en el host.

## Goals / Non-Goals

**Goals:**
- Que `sonar-project.properties` no falle si alguien ejecuta el scanner con `sonar.sources=.` desde la raíz.

**Non-Goals:**
- No se cambia `sonar.sources` (sigue siendo `src`); esto es una red de seguridad adicional en las exclusiones, no el mecanismo principal.

## Decisions

- Añadir `data/**`, `logs/**` y `.scannerwork/**` a `sonar.exclusions` (lista separada por comas ya existente), en vez de crear un `sonar.exclusions` distinto por escenario: mantiene una única fuente de exclusiones válida tanto para CI (`sonar.sources=src`, donde ya no aplicarían de todos modos) como para un análisis manual desde la raíz.

## Risks / Trade-offs

- Ninguno relevante: son exclusiones adicionales, no cambian el comportamiento ya validado en CI.

## Migration Plan

No aplica.
