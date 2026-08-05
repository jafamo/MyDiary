## Why

El proyecto ya tiene código real (entidades Doctrine, comandos de gestión de usuarios) pero ningún test automatizado. `CLAUDE.md` señala explícitamente: "No hay tests ni build configurados todavía — este bloque se actualizará en cuanto exista `composer.json` con comandos reales." Ese `composer.json` ya existe (change `add-symfony-doctrine-bootstrap`), así que toca cerrar ese hueco antes de seguir añadiendo funcionalidad (webhook de Telegram, siguiente paso).

## What Changes

- Instalar `symfony/test-pack` (incluye PHPUnit + `symfony/phpunit-bridge` + `symfony/browser-kit` etc.) vía Composer/Flex.
- Configurar `phpunit.dist.xml` (generado por Flex) para el entorno de test, incluyendo una base de datos de test separada (`telegram_notes_test`, mismo Postgres, mismo patrón que usa Symfony por defecto con `dbname_suffix: '_test%env(default::TEST_TOKEN)%'` ya presente en `config/packages/doctrine.yaml`).
- Escribir tests unitarios para el código ya existente:
  - Entidades: getters/setters, `User::getRoles()` (siempre incluye `ROLE_USER`), relación `DailySummary`↔`Topic` (add/remove).
  - Comandos de consola `app:user:create` y `app:user:change-password` vía `CommandTester`, contra la BD de test.
- Añadir target `make test` al `Makefile` que ejecute PHPUnit dentro de `diary-php`.
- Actualizar `CLAUDE.md` (sección "Flujo de trabajo") con el comando real de test, sustituyendo la nota de "no hay tests configurados todavía".
- El hook `pre-commit` existente **no cambia**: sigue comprobando solo PSR-12. Los tests se ejecutan bajo demanda con `make test` (decisión explícita del usuario, para no ralentizar cada commit).

## Capabilities

### New Capabilities
- `automated-testing`: infraestructura de tests unitarios (PHPUnit configurado, base de datos de test, comando `make test`) y los tests iniciales para el código existente.

### Modified Capabilities
(ninguna — el hook `pre-commit` de `code-style-enforcement` no se toca en este change)

## Impact

- Ficheros nuevos: `phpunit.dist.xml` (o `.dist.xml` equivalente generado por Flex), directorio `tests/` con los tests unitarios, posible `.env.test`.
- Ficheros modificados: `composer.json` (nueva dependencia `require-dev`), `Makefile` (target `test`), `.githooks/pre-commit`, `CLAUDE.md`.
- No incluye tests de integración/funcionales del webhook de Telegram ni de la transcripción — eso no existe todavía (siguiente paso de `Especificaciones.md` sección 9). Este change cubre solo lo que ya está implementado: entidades y comandos de usuario.
