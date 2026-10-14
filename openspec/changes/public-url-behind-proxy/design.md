## Context

KeenDNS (router Keenetic, `192.168.4.1`) termina el TLS de `diary.jfarinos.keenetic.pro` y reenvía a `192.168.4.200:9008` por HTTP con `Host: 192.168.4.200`, sin `X-Forwarded-Host` ni un `X-Forwarded-Proto` fiable (comprobado con `curl` y en el access log de nginx). `HttpUtils::createRedirectResponse()` del firewall genera las redirecciones absolutas con el host y el esquema de la petición. El `.env` de producción ya tiene `APP_PUBLIC_URL=https://diary.jfarinos.keenetic.pro`.

## Goals / Non-Goals

**Goals:** que las redirecciones y URLs absolutas de las peticiones que llegan por el dominio usen el dominio y HTTPS, sin tocar el router.

**Non-Goals:** cerrar el acceso directo al puerto 9008 desde internet (requiere el router); obtener la IP real del cliente (el router no la manda).

## Decisions

- **Opción D, elegida por el usuario frente a cambiar el router (`preserve-host`) o nginx (`map` + plantillas `envsubst`):** un listener de Symfony, que reutiliza `APP_PUBLIC_URL`, se prueba con PHPUnit y no exige recrear contenedores.
- **`App\EventListener\PublicUrlProxyListener`** en `kernel.request` con prioridad 1024, por delante de `RouterListener` (32), la sesión (128) y el firewall (8). Solo la petición principal. Si `REMOTE_ADDR` coincide con `KEENDNS_PROXY_IP`, escribe en la petición la cabecera `Host` (`host[:puerto]`) y `HTTPS`/`SERVER_PORT` según `APP_PUBLIC_URL`. Se compara `REMOTE_ADDR` directamente: no se usan `trusted_proxies` porque el router no manda cabeceras que confiar.
- **`KEENDNS_PROXY_IP` con valor por defecto vacío** (`parameters: env(KEENDNS_PROXY_IP): ''`): en local, CI y cualquier instalación sin proxy no hace nada y no hay que definirla.
- **Sin logs nuevos:** cambiar el host es una reescritura fija y la línea `http.request` ya registra cada petición.

## Risks / Trade-offs

- [Si el router cambia de IP] → las redirecciones vuelven a la IP privada hasta actualizar `KEENDNS_PROXY_IP`; no rompe nada más.
- [Cualquier equipo con la IP del proxy se trata como el dominio] → solo es el router; no da permisos, solo cambia cómo se construyen las URLs.

## Migration Plan

Añadir `KEENDNS_PROXY_IP=192.168.4.1` al `.env` del servidor y `make deploy`. Rollback: vaciar la variable.
