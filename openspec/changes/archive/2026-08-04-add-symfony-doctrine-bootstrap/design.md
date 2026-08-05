## Context

`diary-php` corre PHP 8.4-fpm con extensiones ya instaladas (`pdo_pgsql`, `intl`, `opcache`, `zip`, `redis`) pero sin código dentro — el bind mount `./:/var/www/html` está vacío salvo `docker/`, `.env*`, `openspec/`, etc. `diary-postgres` está operativo con credenciales en `.env`. `diary-nginx` ya apunta a `/var/www/html/public` y hace proxy FastCGI a `diary-php:9000`, así que en cuanto exista `public/index.php` empezará a servir la app sin tocar su configuración.

Restricciones fijas de `CLAUDE.md`: sin EasyAdmin, sin hexagonal estricta, sin CQRS, sin entidad `User` en BD (provider `in_memory`), interfaces solo puntuales (`TranscriberInterface`, `SummaryGeneratorInterface` — no se crean en este change, se dejan para cuando se implemente la transcripción).

## Goals / Non-Goals

**Goals:**
- Symfony instalado y arrancando dentro de `diary-php`, servido correctamente por `diary-nginx` en el puerto 9008.
- Doctrine conectado a `diary-postgres` vía `DATABASE_URL`.
- Las 4 entidades de la sección 6 de `Especificaciones.md` creadas con sus constraints (`unique` en `telegram_message_id`/`telegram_file_unique_id`, cascade delete en `Transcription`, tabla pivote `daily_summary_topic`).
- Migraciones generadas y aplicadas — `diary-postgres` con el esquema real.
- Login vía `in_memory` provider configurado (aunque el `SecurityController`/formulario de login real se implementa en el change de vistas — aquí solo la configuración de seguridad y el usuario).

**Non-Goals:**
- No se implementa el webhook de Telegram, `TranscribeAudioMessage`/handler, ni conexión real a Redis desde Messenger (siguiente change, paso 3 de la sección 9).
- No se implementan controladores de negocio (`DiarioController`, `HistorialController`, etc.) ni vistas Twig reales — solo lo mínimo para confirmar que el skeleton sirve páginas.
- No se implementa `TranscriberInterface`/`SummaryGeneratorInterface` todavía (no hay problema real que resolver aún — regla general de `CLAUDE.md`: introducir un patrón solo cuando el problema ya existe).

## Decisions

### 1. Instalación del skeleton dentro del contenedor ya construido
Se ejecuta `docker compose exec diary-php composer create-project symfony/skeleton .` sobre el bind mount, reutilizando la imagen ya construida en el change anterior (no se reconstruye el `Dockerfile`). Alternativa descartada: generar el skeleton en el host y luego copiarlo — añade un paso extra sin beneficio, ya que Composer está instalado en la imagen (`docker/php/Dockerfile`) precisamente para esto.

### 2. Símfony LTS vía `symfony/skeleton`, no `symfony/website-skeleton`
Se usa el skeleton mínimo (`symfony/skeleton`) y se añaden paquetes según se necesiten (`orm-pack`, `twig-pack`, `security-bundle`, etc.) vía Symfony Flex, en vez del skeleton "full" con Twig/seguridad preinstalados. Coherente con la filosofía de `Especificaciones.md`: añadir solo lo que hace falta en cada paso — Twig real se necesitará en el change de vistas (paso 5), aquí basta con `orm-pack` + `security-bundle` para entidades y login.

### 3. `DATABASE_URL` apuntando al hostname interno `diary-postgres`
Ya está definida la base en `.env.example` (`DATABASE_URL=postgresql://user:pass@postgres:5432/...` de `Especificaciones.md`) pero debe corregirse al hostname real del servicio Compose: `diary-postgres`. Se actualiza `.env.example` y `.env` con `DATABASE_URL=postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@diary-postgres:5432/${POSTGRES_DB}?serverVersion=16&charset=utf8` (Doctrine no interpola variables anidadas de `.env`, así que se fija el valor literal usando los mismos valores que `POSTGRES_*`).

### 4. Migraciones vía `doctrine/doctrine-migrations-bundle`, no `orm:schema:update`
Se generan migraciones versionadas (`bin/console make:migration` / `doctrine:migrations:migrate`) en vez de aplicar el esquema directo, porque el proyecto ya tiene git history y conviene versionar los cambios de esquema desde el principio, aunque sea un proyecto personal — evita sorpresas si `data/postgres` se resetea o se despliega en el mini PC destino.

### 5. `diary-messenger-worker`: comando real pero sin consumidor todavío
Se cambia `command: ["tail", "-f", "/dev/null"]` por `command: ["php", "bin/console", "messenger:consume", "async", "-vv"]` en `docker-compose.yml`, aunque el transporte `async` con mensajes reales (`TranscribeAudioMessage`) se implementa en el siguiente change. Symfony Messenger con `messenger.yaml` por defecto no falla si no hay handlers registrados aún — el worker simplemente queda esperando. Alternativa descartada: dejarlo en placeholder hasta el próximo change — se prefiere dejarlo correcto ahora para no volver a tocar `docker-compose.yml` innecesariamente.

### 6. Seguridad con entidad `User` en BD y gestión exclusiva por consola
Decisión revisada respecto al borrador inicial (que usaba `in_memory`): el usuario pidió explícitamente poder crear usuarios y cambiar contraseñas con comandos Symfony, con los usuarios persistidos en BD. Se añade `security.yaml` con un provider Doctrine (`entity: { class: App\Entity\User, property: username }`) y `password_hashers` estándar (`auto` → `PasswordAuthenticatedUserInterface`).

Dos comandos nuevos (`src/Command/`):
- `app:user:create <username>` — pide la contraseña por prompt oculto (`SymfonyStyle::askHidden`), la hashea con `UserPasswordHasherInterface` y persiste el `User`.
- `app:user:change-password <username>` — pide la contraseña **nueva** por prompt oculto y sobreescribe `password_hash` directamente, sin pedir ni validar la contraseña anterior. Deliberado: quien ejecuta el comando ya tiene acceso al servidor/contenedor `diary-php`, que es el límite de confianza asumido (mismo modelo que `passwd` de un admin de sistema, no un self-service).

Explícitamente fuera de alcance (confirmado con el usuario): sin ruta de registro, sin flujo de recuperación de contraseña por email/token en la web — toda la gestión de usuarios es de consola. Esto revierte la restricción original de `CLAUDE.md`/`Especificaciones.md` ("sin entidad User en BD"), documentos ya actualizados para reflejarlo.

### 7. PSR-12 vía PHP-CS-Fixer + git hook nativo, sin framework de hooks
Se instala `friendsofphp/php-cs-fixer` como dependencia `require-dev`, con `.php-cs-fixer.dist.php` en la raíz usando el ruleset `@PSR12`. Para el pre-commit se usa un **hook de git nativo** (`.githooks/pre-commit`, versionado en el repo, activado con `git config core.hooksPath .githooks`) en vez de un paquete como GrumPHP o Captainhook — coherente con la filosofía de `CLAUDE.md` de no añadir dependencias/frameworks para un problema que un script simple ya resuelve (proyecto personal, un solo desarrollador). El hook ejecuta `docker compose exec -T diary-php vendor/bin/php-cs-fixer fix --dry-run --diff` solo sobre los ficheros PHP en staging (vía `git diff --cached --name-only --diff-filter=ACM -- '*.php'`), y aborta el commit (`exit 1`) si hay diffs. Alternativa descartada: hook fuera de Docker (requiere PHP 8.4 instalado en el host, que no se puede asumir) — se prefiere delegar en `diary-php`, que ya tiene el PHP/extensiones correctos.

Se añade también un target `make cs-fix` (aplica el fix automáticamente) y `make cs-check` (solo valida, lo que usa el hook), para poder ejecutarlo a mano sin pasar por git.

## Risks / Trade-offs

- **[Riesgo] Permisos de `var/` (cache, logs) dentro del bind mount**: igual que con `diary-redis` en el change anterior, Symfony escribe en `var/cache` y `var/log` con el uid del proceso PHP-FPM (`www-data`, uid 33 en la imagen oficial), que puede no coincidir con el uid del host. → Mitigación: si aparece el error real al ejecutar `composer create-project` o `bin/console`, aplicar el mismo patrón de `chown` con contenedor auxiliar ya usado para `diary-redis`, documentado en `design.md` del change anterior.
- **[Riesgo] `composer create-project` sobre un directorio con contenido previo**: la raíz del repo ya tiene `docker/`, `openspec/`, `.env*`, `Makefile`, `docker-compose.yml`, `Especificaciones.md`, `CLAUDE.md`, `README*`. `composer create-project symfony/skeleton .` en un directorio no vacío puede fallar o pedir confirmación. → Mitigación: usar `composer create-project symfony/skeleton /tmp/symfony-skeleton-tmp` y mover/mergear el contenido generado a la raíz, o usar `composer create-project --no-install` seguido de merge manual, verificando que no se sobrescriban ficheros propios del proyecto.
- **[Trade-off] Migraciones generadas automáticamente pueden no reflejar exactamente el borrador de la sección 6**: `make:entity`/`make:migration` infieren tipos según las anotaciones PHP; hay que revisar a mano que columnas `unique`, `nullable` y `onDelete: CASCADE` coincidan con lo documentado antes de aplicar la migración.

## Migration Plan

1. Confirmar `diary-php`/`diary-postgres` levantados (`make up`).
2. Instalar skeleton dentro del contenedor (ver Decisión 1 y Riesgo de directorio no vacío).
3. Añadir `orm-pack`, `doctrine/doctrine-migrations-bundle`, `security-bundle` vía Composer/Flex.
4. Actualizar `DATABASE_URL` en `.env`/`.env.example`.
5. Crear las 4 entidades y sus relaciones.
6. Generar y revisar la migración, aplicarla contra `diary-postgres`.
7. Configurar `security.yaml` con provider `in_memory`.
8. Actualizar `command` de `diary-messenger-worker` en `docker-compose.yml` y relanzar (`make build && make up` si cambia la imagen, o solo `make restart` si no).
9. Verificar `http://localhost:9008` sirve la respuesta por defecto de Symfony (o una ruta mínima de comprobación) en vez de 404.

## Open Questions

- Ninguna bloqueante. Pendiente para el siguiente change (paso 3): decidir el formato exacto de reintentos de Messenger (`retry_strategy`) para `TranscribeAudioMessage`, ya descrito a alto nivel en `Especificaciones.md` 3.2 pero sin valores concretos (número de intentos, backoff).
