## Context

- CI actual: `.github/workflows/sonarqube.yml`, un único job que ejecuta `sonarsource/sonarqube-scan-action@v4` sin cobertura, en `push` a `main`/`develop` y en `pull_request`.
- Stack: PHP 8.4, Symfony 8.1, Doctrine ORM 3, PostgreSQL 16 con pgvector (la migración `Version20260906135100` hace `CREATE EXTENSION vector`), Redis como transporte de Messenger. En el entorno `test`, Messenger usa `in-memory://`, así que los tests no necesitan Redis.
- BD de test: `config/packages/doctrine.yaml` añade el sufijo `_test` al nombre de la BD en `when@test`, así que con `DATABASE_URL=.../telegram_notes` los tests usan `telegram_notes_test`.
- `.env` **no está versionado** (solo `.env.example`, `.env.dev` y `.env.test`), pero `tests/bootstrap.php` y `bin/console` llaman a `Dotenv::bootEnv('.env')`, que falla si el fichero no existe.
- La imagen `docker/php` ya lleva PCOV, así que `make test-coverage` funciona en local.
- Proyecto personal de un solo usuario: no hay PRs desde forks, y los secrets siempre están disponibles.

## Goals / Non-Goals

**Goals:**
- Que ningún push o PR a `develop`/`main` pase sin detectarse con estilo roto, errores nuevos de PHPStan o tests en rojo.
- Que SonarQube reciba la cobertura real de los tests.
- Que la GitHub Release se cree sola al publicar la tag, con el cuerpo tomado del CHANGELOG.
- Mantener el CI simple: un workflow de calidad y otro de release, sin acciones exóticas.

**Non-Goals:**
- Dependabot, `composer audit`, build o escaneo de la imagen Docker, despliegue automático, branch protection (quedan para otro change).
- Subir el nivel de PHPStan por encima de 5 o eliminar el baseline.
- `objectManagerLoader` de phpstan-doctrine: se puede añadir más adelante si hace falta más precisión en los repositorios de Doctrine.
- Cobertura en `make sonar` local: las rutas del clover generado dentro de `diary-php` (`/var/www/html`) no coinciden con las del contenedor del scanner (`/usr/src`). El análisis local sigue funcionando, pero sin cobertura.
- Matriz de versiones de PHP: solo 8.4, la de producción.

## Decisions

### 1. Un solo `ci.yml` con jobs encadenados, en lugar de un workflow por herramienta
Jobs: `lint` → `tests` → `sonar` (con `needs:`). `sonar` necesita el `coverage.xml` de `tests`, y los artefactos solo se comparten entre jobs del mismo run. Con workflows separados habría que usar `workflow_run` y descargar artefactos entre runs, lo que complica bastante. `sonarqube.yml` se elimina.

`concurrency: { group: ci-${{ github.ref }}, cancel-in-progress: true }` cancela el run anterior al hacer push rápido sobre la misma rama.

### 2. `lint` agrupa php-cs-fixer y PHPStan en un solo job
Ambos necesitan lo mismo (PHP + `composer install`), y son rápidos. Dos jobs paralelos repetirían el setup a cambio de ganar muy poco tiempo. Se ejecutan como pasos separados para que el log diga cuál ha fallado. Si falla el estilo, PHPStan también se ejecuta (`if: ${{ !cancelled() }}`) para ver ambos resultados en un solo push.

`tests` depende de `lint`: si el estilo está roto, no merece la pena gastar minutos en la BD.

### 3. PHP con `shivammathur/setup-php`, no con la imagen Docker del proyecto
`setup-php` con `php-version: '8.4'`, `extensions: pdo_pgsql, intl, zip, redis` y `coverage: pcov` (solo en `tests`; `none` en `lint`). La caché de Composer se gestiona con `actions/cache` sobre el directorio que devuelve `composer config cache-files-dir`.

Alternativa descartada: construir `docker/php` y ejecutar dentro. Es más fiel a producción, pero añade el build de la imagen a cada run y complica los service containers. El riesgo de divergencia es bajo porque las extensiones son las mismas y la versión de PHP es la misma.

### 4. Postgres como service container `pgvector/pgvector:pg16`
El job `tests` levanta `pgvector/pgvector:pg16` (misma versión mayor que `serverVersion=16`), con usuario `app`, contraseña `app` y BD `telegram_notes`, un healthcheck `pg_isready` y el puerto 5432 mapeado. El usuario por defecto del contenedor es superusuario, así que `CREATE EXTENSION vector` funciona.

Pasos antes de PHPUnit:
```
bin/console doctrine:database:create --env=test --if-not-exists
bin/console doctrine:migrations:migrate --env=test --no-interaction
```

No hace falta Redis: Messenger usa `in-memory://` en `test`.

### 5. `.env` en CI: `cp .env.example .env` + variables de entorno reales
Como `bootEnv` exige `.env`, el primer paso tras el checkout es `cp .env.example .env`. Los valores que importan se pasan como `env:` del job (`DATABASE_URL=postgresql://app:app@127.0.0.1:5432/telegram_notes?serverVersion=16&charset=utf8`), y las variables reales tienen prioridad sobre los ficheros `.env*`.

Alternativa descartada: versionar `.env`. Es la convención de Symfony, pero choca con la decisión actual del repo de no versionarlo (contiene la configuración del stack Docker). Otra alternativa descartada: hacer `bootEnv` tolerante a que falte `.env`, que toca código de runtime por una necesidad de CI.

### 6. Cobertura Clover con PCOV, pasada a Sonar como artefacto
`tests` ejecuta `bin/phpunit --coverage-clover coverage.xml` y sube `coverage.xml` con `actions/upload-artifact`. `sonar` hace checkout (`fetch-depth: 0`, igual que hoy), descarga el artefacto en la raíz y ejecuta el scan.

`sonar.php.coverage.reportPaths=coverage.xml` va en `sonar-project.properties` y no como argumento del workflow, para que la configuración esté en un solo sitio. Si el fichero no existe (`make sonar` local), el plugin PHP de Sonar solo emite un warning.

Rutas: el clover contiene rutas absolutas del runner (`/home/runner/work/MyDiary/MyDiary/src/...`). Las versiones actuales de `sonarqube-scan-action` ejecutan el scanner directamente en el runner, sin contenedor Docker, y el checkout de `sonar` está en la misma ruta, así que las rutas coinciden. Se aprovecha el cambio para subir la action a su última major. Hay que verificar en la implementación que la cobertura aparece en SonarQube. Si no aparece, la solución es reescribir el prefijo de rutas con `sed` antes del scan.

### 7. PHPStan: nivel 5 + baseline, con las extensiones de Symfony y Doctrine
- Dev-deps: `phpstan/phpstan`, `phpstan/phpstan-symfony`, `phpstan/phpstan-doctrine` y `phpstan/extension-installer`, que registra las extensiones sin `includes:` manuales. `extension-installer` requiere añadirlo a `config.allow-plugins`.
- `phpstan.dist.neon`: `level: 5`, `paths: [src, tests]`, `includes: [phpstan-baseline.neon]` y `symfony.containerXmlPath: var/cache/dev/App_KernelDevDebugContainer.xml`, para que la extensión de Symfony resuelva servicios y parámetros.
- El XML del contenedor lo genera `cache:clear`/`cache:warmup` en entorno `dev`. En CI, el `composer install` ya ejecuta `cache:clear` vía `auto-scripts`. Aun así, se añade un `bin/console cache:warmup --env=dev` explícito antes de PHPStan para no depender de ello.
- Nivel 5 como punto de partida: detecta tipos de argumentos y métodos inexistentes sin exigir tipado estricto de arrays (niveles 6+). El baseline se genera una vez con `--generate-baseline`.
- `make phpstan` → `$(COMPOSE) exec diary-php vendor/bin/phpstan analyse --memory-limit=1G`.

### 8. Release: workflow por tag con filtro SemVer, `gh` CLI e idempotente
```yaml
on:
  push:
    tags: ['[0-9]+.[0-9]+.[0-9]+']
permissions:
  contents: write
```
El filtro de GitHub (glob con `+`) ya excluye `v0.13.0` y tags arbitrarias. El script:
1. `VERSION=${GITHUB_REF_NAME}`.
2. Si `gh release view "$VERSION"` tiene éxito, `exit 0`.
3. `NOTES=$(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md)`: el mismo `awk` de `AGENTS.md`, con los puntos escapados en la regex. Si `NOTES` queda vacío, o solo con espacios, falla con un mensaje claro.
4. `gh release create "$VERSION" --title "$VERSION" --notes "$NOTES" --verify-tag`.

Se usa `gh`, que viene preinstalado en los runners, en lugar de `softprops/action-gh-release`, para mantener exactamente el mismo comando que ya documenta `AGENTS.md` y no añadir una action de terceros.

El workflow se dispara porque la tag la empuja el usuario con sus credenciales. Los eventos generados con `GITHUB_TOKEN` no disparan workflows, pero aquí no es el caso.

## Risks / Trade-offs

- **[El baseline esconde deuda]** → Es intencionado: el objetivo es bloquear errores nuevos. El baseline se puede ir reduciendo; regenerarlo solo debe hacerse de forma consciente.
- **[El XML del contenedor desactualizado o ausente hace fallar PHPStan en local]** → `make phpstan` documenta que se necesita el cache de `dev`. Si falta, basta con `make cache-clear`.
- **[Tests que dependan de servicios externos (Ollama, OpenWebUI, Telegram)]** → Hoy los tests usan dobles para esos servicios. Si alguno llamase a la red, fallaría en CI. Se detectará en la primera ejecución y se corregirá en el test, no en el CI.
- **[Rutas del clover no casan en Sonar]** → Verificación explícita en tasks. Si no casan, se reescribe el prefijo con `sed` (Decisión 6).
- **[El CI tarda más]** → La caché de Composer y la cancelación de runs obsoletos lo mitigan. El coste en minutos es irrelevante en un repo personal.
- **[La release automática y la manual compiten]** → El workflow es idempotente. Si alguien crea la release a mano antes, el workflow termina en verde sin tocarla.
- **[Un CHANGELOG mal formado publica una release vacía]** → El workflow falla antes de crearla si la sección está vacía.

## Migration Plan

1. Implementar en `feature/ci-quality-checks` y hacer push de la rama para ver el CI en verde, con cobertura visible en SonarQube, antes del `finish`.
2. `git flow feature finish` → el push a `develop` ejecuta `ci.yml`. `sonarqube.yml` ya no existe, así que no hay doble análisis.
3. La siguiente release (`0.13.0`) valida `release.yml`: tras `git push origin main develop --tags`, comprobar `gh release view 0.13.0`.
4. Rollback: revertir el merge. Recupera `sonarqube.yml` y elimina los nuevos workflows. Las dev-deps de PHPStan no afectan al runtime.

## Open Questions

- Ninguna bloqueante. Tras unas semanas se puede decidir si se sube PHPStan a nivel 6 o si se activa branch protection con estos checks como obligatorios (fuera de este change).
