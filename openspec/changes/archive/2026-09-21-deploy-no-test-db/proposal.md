## Why

El cambio `deploy-run-tests` (ya en `develop`, no llegó a `main`) hizo que `make deploy` ejecutase `make test` antes de reiniciar los contenedores. Al probarlo, producción no tiene la base de datos `telegram_notes_test` que PHPUnit necesita (solo se creó a mano en el entorno de desarrollo), por lo que `make deploy` fallaría siempre en ese servidor. En vez de crear esa BD en producción, se prefiere que `deploy` no dependa en absoluto de tener Postgres de test en el servidor: el gate de tests queda fuera del propio deploy.

## What Changes

- El target `deploy` del `Makefile` deja de invocar `make test`. Vuelve a ser: `git pull origin main` → `cache-clear` → `restart diary-php diary-messenger-worker`.
- Se retira el requisito "`make deploy` ejecuta los tests antes de reiniciar" añadido a la spec `docker-infrastructure` por `deploy-run-tests`.
- **BREAKING** (respecto al comportamiento introducido por `deploy-run-tests`, que nunca llegó a `main`): `make deploy` ya no bloquea el reinicio si hay tests en rojo. La responsabilidad de no desplegar código roto vuelve a recaer en ejecutar `make test` manualmente en dev antes de mergear/pushear a `main`, como ya indica `CLAUDE.md`.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `docker-infrastructure`: revierte el requisito de `deploy-run-tests` sobre `make deploy` ejecutando tests.

## Impact

- **Fichero afectado**: `Makefile` (target `deploy`).
- Sin impacto en el suite de tests en sí (`make test` sigue existiendo y funcionando igual en local/dev).
