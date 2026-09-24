## 1. PHPStan en local

- [x] 1.1 Añadir `phpstan/extension-installer` a `config.allow-plugins` e instalar `phpstan/phpstan`, `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine` y `phpstan/extension-installer` como dev-deps (`composer require --dev` dentro de `diary-php`)
- [x] 1.2 Crear `phpstan.dist.neon`: `level: 5`, `paths: [src, tests]`, `symfony.containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml` e `includes: [phpstan-baseline.neon]`
- [x] 1.3 Añadir el target `phpstan` al `Makefile` (y a `.PHONY`): `$(COMPOSE) exec diary-php vendor/bin/phpstan analyse --memory-limit=1G`
- [x] 1.4 Generar `phpstan-baseline.neon` con `--generate-baseline` y comprobar que `make phpstan` termina con código 0
- [x] 1.5 Verificar que PHPStan detecta un error nuevo (p. ej. una llamada temporal a un método inexistente en `src/`) y deshacer el cambio

## 2. Configuración de cobertura

- [x] 2.1 Añadir `sonar.php.coverage.reportPaths=coverage.xml` a `sonar-project.properties`
- [x] 2.2 Añadir `coverage.xml` a `.gitignore`
- [x] 2.3 Verificar en local que `bin/phpunit --coverage-clover coverage.xml` (dentro de `diary-php`) genera el fichero

## 3. Workflow de CI (`ci.yml`)

- [x] 3.1 Crear `.github/workflows/ci.yml` con disparadores `push` (`main`, `develop`) y `pull_request`, y `concurrency` por ref con `cancel-in-progress`
- [x] 3.2 Job `lint`: checkout, `cp .env.example .env`, `setup-php` (8.4, `pdo_pgsql, intl, zip, redis`, `coverage: none`), caché de Composer, `composer install`, `cache:warmup --env=dev`, paso `php-cs-fixer fix --dry-run --diff` y paso `phpstan analyse` (este último con `if: ${{ !cancelled() }}`)
- [x] 3.3 Job `tests` (`needs: lint`): service `pgvector/pgvector:pg16` con healthcheck, `DATABASE_URL` en `env:`, setup con `coverage: pcov`, `doctrine:database:create --env=test --if-not-exists`, `doctrine:migrations:migrate --env=test --no-interaction`, `bin/phpunit --coverage-clover coverage.xml` y subir `coverage.xml` con `actions/upload-artifact`
- [x] 3.4 Job `sonar` (`needs: tests`): checkout con `fetch-depth: 0`, descargar el artefacto `coverage.xml` en la raíz y lanzar `sonarsource/sonarqube-scan-action` en su última major, con los secrets `SONAR_HOST_URL` y `SONAR_TOKEN`
- [x] 3.5 Eliminar `.github/workflows/sonarqube.yml`

## 4. Workflow de release (`release.yml`)

- [x] 4.1 Crear `.github/workflows/release.yml` con disparador `push.tags: ['[0-9]+.[0-9]+.[0-9]+']` y `permissions: contents: write`
- [x] 4.2 Paso de script: salir con éxito si `gh release view "$GITHUB_REF_NAME"` existe; extraer la sección con el `awk` de `AGENTS.md` (puntos de la versión escapados); fallar si queda vacía; `gh release create "$VERSION" --title "$VERSION" --notes "$NOTES" --verify-tag` con `GH_TOKEN: ${{ github.token }}`
- [x] 4.3 Probar la extracción del CHANGELOG en local con una versión existente (`0.12.2`) y una inexistente (`9.9.9`, debe fallar)

## 5. Documentación

- [x] 5.1 `AGENTS.md`: en «Publicar», la GitHub Release la crea `release.yml` al empujar la tag; el `gh release create` manual queda como respaldo; «Verificar» sigue igual. En «Flujo de trabajo», mencionar `make phpstan` y que CI ejecuta estilo, PHPStan, tests y Sonar
- [x] 5.2 `README.md`: mencionar `make phpstan` junto a `make test`/`make cs-check`, si esos targets están documentados ahí
- [x] 5.3 Añadir la entrada en `## [Sin publicar]` de `CHANGELOG.md` (Añadido: CI con estilo/PHPStan/tests/cobertura, release automática; Cambiado: `sonarqube.yml` sustituido por `ci.yml`)

## 6. Verificación en GitHub

- [x] 6.1 `make cs-check`, `make phpstan` y `make test` en verde en local
- [x] 6.2 Push de `feature/ci-quality-checks` y abrir un PR a `develop`: los jobs `lint`, `tests` y `sonar` terminan en verde
- [x] 6.3 Comprobar en SonarQube que el análisis del PR/rama muestra cobertura distinta de 0 %. Si no la muestra, reescribir el prefijo de rutas del clover (design, Decisión 6)
- [ ] 6.4 Tras la próxima release, comprobar que `release.yml` creó la GitHub Release (`gh release view X.Y.Z`) con el cuerpo del CHANGELOG
