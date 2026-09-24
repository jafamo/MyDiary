## Why

Hoy lo único que corre en GitHub Actions es el scan de SonarQube, y lo hace sin cobertura: Sonar ve un 0 % y no puede valorar el riesgo real de los cambios. Los tests y el estilo solo se validan en local (`make test`, hook `pre-commit`), así que se pueden saltar (`--no-verify`, clon sin `core.hooksPath`) y nada lo detecta antes del merge. Además, la GitHub Release de cada versión se crea a mano después del `finish`, y es fácil olvidarla.

## What Changes

- Nuevo workflow `.github/workflows/ci.yml` que **sustituye** a `sonarqube.yml`, con jobs encadenados: `lint` (php-cs-fixer + PHPStan) → `tests` (PHPUnit con cobertura) → `sonar` (scan con la cobertura de `tests`). Mismos disparadores que hoy: `push` a `main`/`develop` y `pull_request`.
- Job `lint`: `php-cs-fixer fix --dry-run --diff` sobre todo el repo, con la misma configuración `.php-cs-fixer.dist.php`.
- Job `tests`: PHPUnit contra Postgres con pgvector como service container, con la BD de test (`telegram_notes_test`) creada y migrada en el propio job, generando `coverage.xml` (clover) con PCOV y publicándolo como artefacto.
- Job `sonar`: descarga `coverage.xml` y se lo pasa al scanner vía `sonar.php.coverage.reportPaths`. Si fallan los tests, no se analiza.
- **PHPStan** como nueva herramienta de análisis estático: `phpstan/phpstan`, `phpstan/phpstan-symfony` y `phpstan/phpstan-doctrine` en `require-dev`, con `phpstan.dist.neon` a nivel 5 y un `phpstan-baseline.neon` para los errores existentes. Nuevo target `make phpstan`.
- Nuevo workflow `.github/workflows/release.yml`: al hacer push de una tag SemVer sin prefijo `v` (`X.Y.Z`), crea la GitHub Release con título `X.Y.Z` y como cuerpo la sección `## [X.Y.Z]` de `CHANGELOG.md`. Si la release ya existe, no hace nada. Si la sección no existe o está vacía, falla.
- `AGENTS.md`: el paso de publicar pasa a ser solo `git push origin main develop --tags`; la creación de la release la hace CI. El comando `gh release create` manual queda como alternativa si falla el workflow. Se documenta `make phpstan`.

## Capabilities

### New Capabilities
- `release-automation`: creación automática de la GitHub Release a partir de la tag y del CHANGELOG.

### Modified Capabilities
- `code-style-enforcement`: el estilo PSR-12 también se verifica en CI, no solo en el hook local.
- `automated-testing`: el suite se ejecuta en CI contra una BD de test efímera y genera un informe de cobertura.
- `static-analysis`: se añade PHPStan (local y CI) y el análisis de SonarQube pasa a depender de los tests y a incluir su cobertura.

## Impact

- **Ficheros nuevos**: `.github/workflows/ci.yml`, `.github/workflows/release.yml`, `phpstan.dist.neon`, `phpstan-baseline.neon`.
- **Ficheros eliminados**: `.github/workflows/sonarqube.yml`, sustituido por `ci.yml`.
- **Ficheros modificados**: `composer.json`/`composer.lock` (nuevas dev-deps), `Makefile` (target `phpstan`), `sonar-project.properties` (ruta de cobertura), `AGENTS.md`, `.gitignore` (`coverage.xml`), `CHANGELOG.md`.
- **Secrets**: sin cambios (`SONAR_HOST_URL`, `SONAR_TOKEN`). El workflow de release usa el `GITHUB_TOKEN` integrado con `contents: write`.
- **Runtime/producción**: ninguno. Solo afecta a dev-deps y CI.
- **Tiempo de CI**: pasa de ~1 min (solo Sonar) a unos pocos minutos por push, por el `composer install` y los tests con BD.
