## Context

`deploy` (`Makefile`) hoy es: `git pull origin main` → `make cache-clear` → `restart diary-php diary-messenger-worker`. `test` ya existe y corre `bin/phpunit` dentro de `diary-php` contra `telegram_notes_test`.

## Goals / Non-Goals

**Goals:**
- Que `make deploy` no reinicie los contenedores si el suite de tests falla tras el `git pull`.

**Non-Goals:**
- No se añade `cs-check` al flujo de deploy (fuera de lo pedido; se puede proponer aparte si se quiere).
- No se cambia el comportamiento de `make test` en sí.

## Decisions

- **No usar `deploy: test` como prerequisito declarativo de Make.** Un prerequisito se ejecuta *antes* que el cuerpo del target, así que los tests correrían contra el código previo al `git pull`, no contra el código que se va a desplegar — justo lo contrario de lo que se busca.
- En su lugar, el cuerpo de `deploy` invoca `$(MAKE) test` explícitamente **después** del `git pull` y **antes** de `cache-clear`/`restart`. Make aborta la ejecución de una receta en cuanto un comando devuelve código de salida distinto de cero (comportamiento por defecto, sin necesidad de `set -e` ni flags extra), así que un test en rojo detiene el deploy ahí mismo.

## Risks / Trade-offs

- [El suite de tests tarda más que un restart simple, alargando el deploy] → Mitigación: aceptable para un proyecto personal de un solo usuario; el suite ya corre en unos segundos (182 tests en ~13s según la última ejecución).
- [Un test flaky bloquea un deploy legítimo] → Mitigación: no se añade retry automático; si ocurre, se soluciona el test como cualquier fallo de CI, no se hace bypass silencioso.

## Migration Plan

No aplica migración; es un cambio de un target de `Makefile`.
