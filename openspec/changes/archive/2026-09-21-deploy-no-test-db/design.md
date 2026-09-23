## Context

`deploy-run-tests` añadió `$(MAKE) test` al target `deploy` para que un suite en rojo bloquease el reinicio de contenedores. En la práctica, `make test` fuerza `APP_ENV=test`, que Doctrine resuelve contra `telegram_notes_test` (sufijo `_test`, ver `config/packages/doctrine.yaml`). Esa base de datos nunca se creó en el servidor de producción — solo existe en dev, donde se creó manualmente al configurar PHPUnit (`openspec/changes/archive/2026-08-04-add-phpunit-testing/design.md`).

## Goals / Non-Goals

**Goals:**
- `make deploy` vuelve a funcionar en cualquier servidor sin requisitos adicionales de infraestructura (no necesita `telegram_notes_test`).

**Non-Goals:**
- No se busca una alternativa de gate de tests en el deploy (p. ej. CI externo) en este cambio; queda fuera de alcance. Si se quiere en el futuro, será una propuesta aparte.

## Decisions

- Revertir literalmente el cambio en el `Makefile`: quitar la línea `$(MAKE) test` de `deploy`.
- Revertir el requisito correspondiente en `openspec/specs/docker-infrastructure/spec.md` (vía `## REMOVED Requirements` en la delta spec), para que la spec no documente un comportamiento que ya no existe.

## Risks / Trade-offs

- [Se pierde la protección de no desplegar código con tests rotos] → Mitigación: sigue siendo responsabilidad de ejecutar `make test` en dev antes de mergear a `main`/hacer release, como ya establece el flujo Git Flow del repo (`CLAUDE.md`).

## Migration Plan

No aplica migración de datos. `deploy-run-tests` nunca llegó a `main`, así que no hay que revertir nada en producción.
