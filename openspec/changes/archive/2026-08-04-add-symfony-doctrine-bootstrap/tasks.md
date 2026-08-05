## 1. Skeleton de Symfony

- [x] 1.1 Ejecutar `composer create-project symfony/skeleton` dentro de `diary-php` (usar directorio temporal + merge si el repo no está vacío) y confirmar que no sobrescribe `docker/`, `openspec/`, `Especificaciones.md`, `CLAUDE.md`, `README*`, `Makefile`, `docker-compose.yml`, `.env*`, `.gitignore` — instalado Symfony 8.1.2 (última estable, PHP 8.4 compatible)
- [x] 1.2 Añadir `orm-pack` (Doctrine ORM + Migrations) vía Composer/Flex
- [x] 1.3 Añadir `security-bundle` vía Composer/Flex
- [x] 1.4 Confirmar `.gitignore` generado por Symfony Flex se combina bien con el existente (no se pisa el bloque de Docker)

## 2. Conexión a base de datos

- [x] 2.1 Actualizar `.env.example` y `.env` con `DATABASE_URL` apuntando a `diary-postgres`
- [x] 2.2 Verificar conexión: `docker compose exec diary-php bin/console dbal:run-sql "SELECT 1"` (el comando `doctrine:query:sql` no existe en esta versión; el correcto es `dbal:run-sql`)

## 3. Entidades del modelo de datos

- [x] 3.1 Crear entidad `AudioRecording` (campos y constraints de `Especificaciones.md` sección 6) — `status` como PHP enum nativo (`AudioRecordingStatus`) mapeado con `enumType`
- [x] 3.2 Crear entidad `Transcription` (relación 1:1 con `AudioRecording`, `onDelete: CASCADE`)
- [x] 3.3 Crear entidad `Topic` (`name` unique)
- [x] 3.4 Crear entidad `DailySummary` (`date` unique, relación N:M con `Topic` vía `daily_summary_topic`)
- [x] 3.5 Crear entidad `User` (`username` unique, `password_hash` vía `getPassword()`/`setPassword()`, `roles` json, implementando `UserInterface`/`PasswordAuthenticatedUserInterface`)
- [x] 3.6 Revisar a mano las anotaciones/atributos generados: uniques, nullable, cascade, tipos de columna, contra el borrador de la sección 6 — confirmado con `doctrine:mapping:info` (5 entidades OK)

## 4. Migraciones

- [x] 4.1 Generar migración: `docker compose exec diary-php bin/console doctrine:migrations:diff` (MakerBundle no está instalado; `doctrine:migrations:diff` viene con `doctrine-migrations-bundle` y no requiere añadir una dependencia extra)
- [x] 4.2 Revisar el SQL generado por la migración antes de aplicarla — uniques, cascade y tabla pivote confirmados contra la sección 6
- [x] 4.3 Aplicar migración: `docker compose exec diary-php bin/console doctrine:migrations:migrate`
- [x] 4.4 Verificar esquema: `bin/console doctrine:migrations:status` muestra todo aplicado

## 5. Seguridad

- [x] 5.1 Configurar `config/packages/security.yaml` con provider Doctrine (`entity: { class: App\Entity\User, property: username }`) y `password_hashers` automático
- [x] 5.2 Crear comando `app:user:create <username>` (prompt oculto para la contraseña, hashea con `UserPasswordHasherInterface`, persiste `User`)
- [x] 5.3 Crear comando `app:user:change-password <username>` (prompt oculto para la contraseña nueva, sobrescribe `password_hash` sin pedir la actual)
- [x] 5.4 Crear el primer usuario con `bin/console app:user:create` para poder probar el login más adelante — usuario `jfarinos` creado y verificado el cambio de contraseña
- [x] 5.5 *(hallazgo durante implementación)* Renombrar la tabla de `user` a `app_user`: `user` es palabra reservada en PostgreSQL y causaba `SQLSTATE[42703]: column t0.id does not exist` en las consultas generadas por Doctrine (citado inconsistente del identificador). Añadido `#[ORM\Table(name: 'app_user')]`; `Especificaciones.md` actualizado

## 6. PSR-12 / calidad de código

- [x] 6.1 Añadir `friendsofphp/php-cs-fixer` como dependencia `require-dev`
- [x] 6.2 Crear `.php-cs-fixer.dist.php` con ruleset `@PSR12` (Symfony Flex generó uno con `@Symfony` vía recipe; se ajustó a `@PSR12`)
- [x] 6.3 Crear script `.githooks/pre-commit` que ejecute PHP-CS-Fixer `--dry-run` sobre los ficheros `.php` en staging dentro de `diary-php`, abortando el commit si hay violaciones
- [x] 6.4 Documentar/activar `git config core.hooksPath .githooks` (a nivel de repo, o instrucción en README para que cada clon lo active) — activado localmente; pendiente añadir nota en README para nuevos clones
- [x] 6.5 Añadir targets `make cs-check` y `make cs-fix` al `Makefile` — `make cs-check` verificado: 0/16 ficheros con violaciones

## 7. Docker Compose

- [x] 7.1 Cambiar `command` de `diary-messenger-worker` en `docker-compose.yml` de `["tail", "-f", "/dev/null"]` a `["php", "bin/console", "messenger:consume", "async", "-vv"]`
- [x] 7.2 Relanzar el stack y confirmar que `diary-messenger-worker` no crashea en bucle. *(hallazgos)*: (a) Symfony Flex generó `compose.override.yaml` con un servicio `database` redundante — eliminado, ya tenemos `diary-postgres`; (b) instalado `symfony/messenger` (no venía en el skeleton) y configurado transporte `async` con `MESSENGER_TRANSPORT_DSN=redis://diary-redis:6379/messages`; (c) instalado `symfony/redis-messenger`, requerido para que el DSN `redis://` funcione (no viene con `symfony/messenger` base)

## 8. Verificación

- [x] 8.1 `curl http://localhost:9008` devuelve una respuesta de Symfony (no 404 de nginx) — confirmado: 404 de la app Symfony ("Welcome to Symfony!"), no de nginx
- [x] 8.2 `bin/console doctrine:migrations:status` confirma esquema aplicado en `diary-postgres`
- [x] 8.3 `make cs-check` pasa sin errores sobre el código generado por el skeleton/entidades (0/16 ficheros con violaciones)
- [x] 8.4 Probar el hook: modificar un fichero `.php` con una violación PSR-12 deliberada, hacer `git add` + `git commit`, confirmar que el commit se aborta; revertir el cambio — confirmado, commit bloqueado y revertido
- [x] 8.5 `docker compose ps` confirma `diary-messenger-worker` en estado `Up` (no reiniciando en bucle) tras el cambio de comando
