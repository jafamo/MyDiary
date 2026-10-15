## 1. Corrección

- [x] 1.1 Añadir `#[Target('api_login.limiter')]` al parámetro `$apiLoginLimiter` de `src/Controller/Api/AuthController.php`
- [x] 1.2 Comprobar en local que `bin/console debug:container --deprecations` no lista ninguna

## 2. Tests

- [x] 2.1 `tests/ContainerDeprecationsTest.php`: el log de deprecaciones del contenedor de test está vacío
- [x] 2.2 Confirmar que los tests del límite de intentos del login siguen en verde

## 3. Verificación

- [x] 3.1 `make cs-check`, `make phpstan` y `make test` en verde
