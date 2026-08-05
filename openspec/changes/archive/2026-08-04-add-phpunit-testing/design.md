## Context

`composer.json` ya existe (Symfony 8.1, Doctrine, entidades, comandos de usuario del change `add-symfony-doctrine-bootstrap`), pero no hay ningún paquete de testing instalado ni directorio `tests/`. `config/packages/doctrine.yaml` ya trae preconfigurado `dbname_suffix: '_test%env(default::TEST_TOKEN)%'` bajo `when@test`, generado por el recipe de `doctrine/doctrine-bundle` — la base para una BD de test separada ya está lista, solo falta activarla.

## Goals / Non-Goals

**Goals:**
- PHPUnit instalado y ejecutable dentro de `diary-php` vía `make test`.
- Base de datos de test separada (`telegram_notes_test`) en el mismo `diary-postgres`, sin tocar los datos de desarrollo.
- Tests unitarios para las entidades y los dos comandos de usuario ya implementados.
- `CLAUDE.md` actualizado para reflejar que ya hay comandos de test reales.

**Non-Goals:**
- No se añaden tests funcionales/de integración del webhook de Telegram, transcripción ni resumen diario — no existen todavía.
- No se integra el test suite en el hook `pre-commit` (decisión explícita del usuario: mantenerlo rápido, solo PSR-12).
- No se configura CI (GitHub Actions ni similar) en este change — fuera de alcance, no pedido.
- No se persigue cobertura específica; se testea lo que ya existe, sin inventar funcionalidad nueva solo para tener algo que testear.

## Decisions

### 1. `symfony/test-pack`, no PHPUnit "pelado"
Se instala `symfony/test-pack` (metapaquete que trae `phpunit/phpunit` vía `symfony/phpunit-bridge`, más `symfony/browser-kit`/`symfony/css-selector` para cuando hagan falta tests funcionales de controladores). El bridge de Symfony gestiona la versión de PHPUnit y añade utilidades (`KernelTestCase`, deprecaciones, etc.) sin tener que fijar versión de PHPUnit a mano. Alternativa descartada: `phpunit/phpunit` directo — funciona, pero pierde la integración con el kernel de Symfony que se necesitará para testear los comandos (`KernelTestCase` + `CommandTester`).

### 2. Base de datos de test vía `dbname_suffix`, no un servicio Postgres aparte
Ya viene configurado en `config/packages/doctrine.yaml` (`when@test: dbname_suffix: '_test...'`), heredado del recipe de doctrine-bundle. Se activa creando `telegram_notes_test` en el mismo `diary-postgres` con `bin/console doctrine:database:create --env=test` y aplicando las migraciones ahí también. Evita levantar un contenedor Postgres adicional solo para tests — coherente con no añadir infraestructura sin necesidad real.

### 3. Tests unitarios puros para entidades, `KernelTestCase` + `CommandTester` para comandos
- Entidades (`AudioRecording`, `Transcription`, `Topic`, `DailySummary`, `User`, `AudioRecordingStatus`): tests unitarios puros (`PHPUnit\Framework\TestCase`), sin arrancar el kernel — solo getters/setters y la lógica no trivial (`User::getRoles()` siempre añade `ROLE_USER`; `DailySummary::addTopic()`/`removeTopic()` no duplica).
- `CreateUserCommand`/`ChangeUserPasswordCommand`: requieren `EntityManagerInterface` y `UserPasswordHasherInterface` reales (contra la BD de test), así que se testean con `KernelTestCase` + `CommandTester`, limpiando la tabla `app_user` en cada test (`setUp`/`tearDown`) para que sean idempotentes.

### 4. `make test` ejecuta el suite completo dentro de `diary-php`
`docker compose exec diary-php bin/phpunit`. Sin flags de filtrado por defecto — se puede pasar `ARGS="--filter=X"` si hace falta filtrar, siguiendo el mismo patrón que `make logs SERVICE=...`.

## Risks / Trade-offs

- **[Riesgo] Tests de comandos requieren BD de test poblada con el esquema actual**: si se olvida ejecutar las migraciones contra `telegram_notes_test` tras añadir una migración nueva, los tests de comandos fallarán con tablas inexistentes. → Mitigación: documentar el paso en `design.md`/tasks, y considerar en un cambio futuro automatizarlo dentro de `make test` si resulta tedioso en la práctica (no anticipar la solución ahora, solo si el problema aparece de verdad).
- **[Trade-off] Sin cobertura de código configurada** (`--coverage-html`, Xdebug/PCOV): añadir cobertura requiere una extensión adicional en la imagen `diary-php` y no se ha pedido. Se deja fuera; se puede añadir cuando haga falta.

## Migration Plan

No aplica (no hay test suite previo). Pasos de puesta en marcha:
1. `composer require --dev symfony/test-pack`.
2. Crear `telegram_notes_test` y aplicar migraciones en ese entorno.
3. Escribir los tests.
4. `make test` verde.

## Open Questions

Ninguna bloqueante.
