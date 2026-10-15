## Context

`FrameworkBundle` registra cada limitador de `config/packages/rate_limiter.yaml` como alias con nombre para autowiring (`RateLimiterFactoryInterface $apiLoginLimiter`, target `api_login.limiter`). Desde `symfony/dependency-injection` 8.1, resolverlo solo por el nombre del parámetro está deprecado. Al compilar el contenedor, la deprecación se guarda en `<build_dir>/<ContainerClass>Deprecations.log`. Es lo que lee `debug:container --deprecations` y lo que se ve en producción tras reconstruir la caché.

## Goals / Non-Goals

**Goals:** quitar la deprecación y que CI detecte la próxima.
**Non-Goals:** cambiar el límite de intentos o su configuración.

## Decisions

- **`#[Target('api_login.limiter')]` en el parámetro**, como pide Symfony. Se descarta un `bind` en `services.yaml`: es justo el mecanismo de nombres que se está retirando, y alejaría la configuración del único sitio que la usa. Elegido con el usuario.
- **Test sobre el log de deprecaciones del contenedor**: arranca el kernel de test y comprueba que `<build_dir>/<container_class>Deprecations.log` no tenga ninguna entrada (o no exista). Así cubre cualquier deprecación de compilación, no solo esta. Riesgo aceptado: una actualización de dependencias que traiga deprecaciones de terceros romperá CI, y hay que corregirlas en ese momento.

## Risks / Trade-offs

- [El log solo se regenera al recompilar el contenedor] → En CI la caché siempre es nueva. En local, el contenedor de test se recompila al cambiar el código (`debug`).
