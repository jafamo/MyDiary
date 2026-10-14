## Why

Al entrar por `https://diary.jfarinos.keenetic.pro`, la web redirige a `http://192.168.4.200/login`, una IP privada que fuera de casa no lleva a ningún sitio. El proxy KeenDNS del router reenvía a nginx con `Host: 192.168.4.200`, por HTTP y sin cabeceras `X-Forwarded-*`, y Symfony construye las redirecciones absolutas del login con ese host. El usuario no quiere cambiar la configuración del router (opción D de las propuestas).

## What Changes

- Nuevo listener `PublicUrlProxyListener` en `kernel.request`, antes del router y del firewall: si la petición llega desde la IP del proxy (`KEENDNS_PROXY_IP`), le pone el esquema, el host y el puerto de `APP_PUBLIC_URL`. Así las redirecciones (login, tras el login, logout) y las URLs absolutas salen con `https://diary.jfarinos.keenetic.pro`, y la cookie de sesión se marca `Secure`.
- Variable nueva `KEENDNS_PROXY_IP` (por defecto vacía: el listener no hace nada). En producción, `192.168.4.1`.
- Las peticiones de otras IPs (red local por IP, acceso directo al puerto 9008) no cambian.

## Capabilities

### New Capabilities

Ninguna.

### Modified Capabilities

- `docker-infrastructure`: nuevo requisito de acceso público a través del proxy del router.

## Impact

- **Código nuevo:** `src/EventListener/PublicUrlProxyListener.php` y sus tests.
- **Configuración:** `config/services.yaml` (valor por defecto de `KEENDNS_PROXY_IP` y bind), `.env.example`, `.env.test`.
- **Documentación:** `Especificaciones.md` (sección 7), `CHANGELOG.md`.
- **Despliegue:** añadir `KEENDNS_PROXY_IP=192.168.4.1` al `.env` del servidor y `make deploy`. Sin migraciones ni cambios en nginx ni en el router.
