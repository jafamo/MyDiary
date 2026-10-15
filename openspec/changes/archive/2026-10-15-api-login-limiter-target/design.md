## Context

`FrameworkBundle` registra cada limitador de `config/packages/rate_limiter.yaml` como alias con nombre para autowiring (`RateLimiterFactoryInterface $apiLoginLimiter`, target `api_login.limiter`). Desde `symfony/dependency-injection` 8.1, resolverlo solo por el nombre del parámetro está deprecado. Al compilar el contenedor, la deprecación se guarda en `<build_dir>/<ContainerClass>Deprecations.log`. Es lo que lee `debug:container --deprecations` y lo que se ve en producción tras reconstruir la caché.

## Goals / Non-Goals

**Goals:** quitar la deprecación y que CI detecte la próxima.
**Non-Goals:** cambiar el límite de intentos o su configuración.

## Decisions

- **`#[Target('api_login.limiter')]` en el parámetro**, como pide Symfony. Se descarta un `bind` en `services.yaml`: es justo el mecanismo de nombres que se está retirando, y alejaría la configuración del único sitio que la usa. Elegido con el usuario.
- **Test que compila un contenedor nuevo y captura las deprecaciones**: bajo PHPUnit el kernel no escribe `Deprecations.log` (`KernelTrait` lo omite si existe `PHPUNIT_COMPOSER_INSTALL`), así que leer ese fichero no detectaba nada (comprobado: el test pasaba sin la corrección). El test arranca una subclase del `Kernel` de test con la caché en un directorio temporal y recoge con `set_error_handler` las `E_USER_DEPRECATED`/`E_DEPRECATED` de la compilación. Así cubre cualquier deprecación de compilación, no solo esta, y se comprobó que falla sin `#[Target]`. Riesgo aceptado: una actualización de dependencias que traiga deprecaciones de terceros romperá CI, y hay que corregirlas en ese momento.

## Risks / Trade-offs

- [Compilar un contenedor completo hace el test más lento (unos segundos)] → Es un único test, y la caché temporal se borra al terminar.
