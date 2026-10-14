## ADDED Requirements

### Requirement: Acceso público a través del proxy del router
Cuando `KEENDNS_PROXY_IP` tiene valor y una petición llega a Symfony desde esa IP, el sistema SHALL tratarla como si se hubiera recibido en `APP_PUBLIC_URL`: mismo esquema, host y puerto, antes de enrutarla y de aplicar la seguridad. Las redirecciones y URLs absolutas de esas peticiones SHALL usar `APP_PUBLIC_URL`. Las peticiones desde cualquier otra IP, o con `KEENDNS_PROXY_IP` vacía, MUST NOT modificarse.

#### Scenario: Login por el dominio público
- **WHEN** una petición sin sesión a `/` llega desde la IP del proxy con `Host: 192.168.4.200` por HTTP y `APP_PUBLIC_URL` es `https://diary.example.test`
- **THEN** la respuesta redirige a `https://diary.example.test/login`

#### Scenario: Acceso desde la red local por IP
- **WHEN** una petición sin sesión a `/` llega desde otra IP con `Host: 192.168.4.200`
- **THEN** la respuesta redirige a `http://192.168.4.200/login`, como hasta ahora

#### Scenario: Proxy sin configurar
- **WHEN** `KEENDNS_PROXY_IP` está vacía
- **THEN** ninguna petición se modifica
