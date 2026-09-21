## ADDED Requirements

### Requirement: `make deploy` ejecuta los tests antes de reiniciar
El sistema SHALL ejecutar el suite de tests (equivalente a `make test`) como parte de `make deploy`, después de `git pull origin main` y antes de `cache-clear` y del reinicio de `diary-php`/`diary-messenger-worker`. Si el suite de tests falla, `make deploy` SHALL detenerse sin ejecutar `cache-clear` ni el reinicio.

#### Scenario: Deploy con tests en verde
- **WHEN** se ejecuta `make deploy` y, tras el `git pull`, todos los tests pasan
- **THEN** se ejecuta `cache-clear` y se reinician `diary-php` y `diary-messenger-worker` con normalidad

#### Scenario: Deploy con tests en rojo
- **WHEN** se ejecuta `make deploy` y, tras el `git pull`, al menos un test falla
- **THEN** `make deploy` termina con código de error, sin ejecutar `cache-clear` ni reiniciar los contenedores de aplicación
