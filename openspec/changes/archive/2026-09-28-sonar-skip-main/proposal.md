## Why

SonarQube Community no distingue ramas: `main` y `develop` analizan el mismo proyecto `MyDiary`. En una release se hace push de `main` y `develop` a la vez, los dos análisis llegan al servidor casi al mismo tiempo y uno de ellos falla (`CE Task finished abnormally with status: FAILED`, exit code 3), dejando en rojo el CI de `main` (release 0.16.0, run `36438655138`). Además, `main` solo recibe merges de código que ya se ha analizado en `develop`, así que su análisis es un duplicado.

## What Changes

- El job `sonar` de `.github/workflows/ci.yml` deja de ejecutarse en los push a `main`. Sigue ejecutándose en los push a `develop` y en los pull requests.
- Los jobs `lint` y `tests` siguen ejecutándose en `main`.
- Se actualizan las menciones al alcance de la CI en `AGENTS.md`, `README.md` y `doc/README_ES.md`.

## Capabilities

### New Capabilities
<!-- ninguna -->

### Modified Capabilities
- `static-analysis`: el análisis automático en CI ya no se ejecuta en push a `main`.

## Impact

- `.github/workflows/ci.yml` (condición `if` en el job `sonar`).
- Documentación: `AGENTS.md`, `README.md`, `doc/README_ES.md`.
- El proyecto de SonarQube refleja siempre el estado de `develop`, que es lo que ya pasaba en la práctica.
