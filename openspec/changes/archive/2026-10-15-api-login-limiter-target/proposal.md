## Why

Tras desplegar 0.19.0, producción registra en el canal `deprecation`: «Relying solely on the name of parameter "$apiLoginLimiter" of "__construct()" to match a named autowiring alias is deprecated; use the "#[Target]" attribute» (`symfony/dependency-injection` 8.1). Hoy funciona, pero en Symfony 9 el limitador del login de la API dejaría de inyectarse y la app no arrancaría.

## What Changes

- `AuthController` indica el limitador `api_login` con `#[Target]` en lugar de depender del nombre del parámetro `$apiLoginLimiter`.
- Test nuevo que falla si la compilación del contenedor deja alguna deprecación, para que la siguiente se detecte en CI y no en producción.
- Sin cambios de comportamiento: el login sigue limitado a 5 intentos fallidos cada 15 minutos.

## Capabilities

### New Capabilities
<!-- ninguna -->

### Modified Capabilities
- `symfony-application-bootstrap`: se añade el requisito «Contenedor sin deprecaciones de compilación».

## Impact

- Código: `src/Controller/Api/AuthController.php`.
- Tests: `tests/ContainerDeprecationsTest.php` (nuevo); los tests de la API que ya existen cubren el límite de intentos.
- Sin migraciones, variables ni pasos de despliegue: el aviso desaparece cuando `make deploy` reconstruye la caché.
