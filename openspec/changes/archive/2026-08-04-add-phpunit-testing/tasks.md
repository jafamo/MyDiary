## 1. Instalación

- [x] 1.1 `composer require --dev symfony/test-pack` dentro de `diary-php`
- [x] 1.2 Revisar `phpunit.dist.xml` generado por el recipe y confirmar que apunta a `tests/`
- [x] 1.3 Arreglar propiedad de los ficheros generados (patrón `chown` ya usado en changes anteriores)

## 2. Base de datos de test

- [x] 2.1 Crear la base de datos de test: `bin/console doctrine:database:create --env=test`
- [x] 2.2 Aplicar migraciones contra la BD de test: `bin/console doctrine:migrations:migrate --env=test --no-interaction`
- [x] 2.3 Verificar conexión: `bin/console dbal:run-sql --env=test "SELECT 1"`

## 3. Tests unitarios de entidades

- [x] 3.1 `tests/Entity/AudioRecordingTest.php`
- [x] 3.2 `tests/Entity/TranscriptionTest.php`
- [x] 3.3 `tests/Entity/TopicTest.php`
- [x] 3.4 `tests/Entity/DailySummaryTest.php` (incluye add/remove topic sin duplicados)
- [x] 3.5 `tests/Entity/UserTest.php` (incluye `getRoles()` con `ROLE_USER` garantizado)
- [x] 3.6 `tests/Entity/AudioRecordingStatusTest.php` (valores del enum)

## 4. Tests de comandos

- [x] 4.1 `tests/Command/CreateUserCommandTest.php` (`KernelTestCase` + `CommandTester`, limpia `app_user` en `setUp`/`tearDown`)
- [x] 4.2 `tests/Command/ChangeUserPasswordCommandTest.php` (ídem, verifica que el hash cambia sin pedir la contraseña anterior)

## 5. Makefile y documentación

- [x] 5.1 Añadir target `test` al `Makefile`: `docker compose exec diary-php bin/phpunit $(ARGS)`
- [x] 5.2 Actualizar `CLAUDE.md` sección "Flujo de trabajo": sustituir la nota "no hay tests ni build configurados todavía" por el comando real (`make test`)

## 6. Verificación

- [x] 6.1 `make test` pasa en verde con todos los tests nuevos — 19 tests, 43 assertions
- [x] 6.2 Confirmar que los tests de comandos no dejan registros residuales en `app_user` (BD de test) tras ejecutar el suite dos veces seguidas
- [x] 6.3 Confirmar que la BD de desarrollo (`telegram_notes`) no se ve afectada tras ejecutar `make test` (el usuario `jfarinos` creado en el change anterior sigue existiendo)
- [x] 6.4 `make cs-check` sigue pasando (el nuevo código de `tests/` también cumple PSR-12) — 0/25 ficheros con violaciones
