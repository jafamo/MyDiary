## Context

Proyecto personal de un solo usuario (`CLAUDE.md`), en fase previa a tener código Symfony. Se necesita el entorno Docker Compose base antes de escanear con `composer create-project`. Requisitos ya fijados por el usuario:

- Fichero `.env` con puertos y puntos de montaje de cada servicio.
- Versión de cada imagen parametrizable desde `.env`.
- Symfony accesible en el puerto **9008** del host.
- Nombres de contenedor con prefijo `diary-` (`diary-php`, `diary-nginx`, ...).
- Logs de cada servicio en un directorio por servicio.
- Debe integrarse con las decisiones ya tomadas en `Especificaciones.md`: Redis como transporte de Messenger, PostgreSQL 16, servicios externos de IA en `192.168.4.200` (no se dockerizan).
- Se necesita un `Makefile` con los comandos habituales.

## Goals / Non-Goals

**Goals:**
- `docker-compose.yml` funcional con `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis`, `diary-messenger-worker`.
- Todo configurable (puertos, versiones, rutas de montaje) desde `.env`, con `.env.example` versionado como plantilla.
- Logs y datos persistentes en bind mounts locales, fáciles de inspeccionar sin entrar al contenedor.
- Único puerto publicado al host: 9008 (nginx). El resto de servicios solo son accesibles en la red interna de Compose.
- `Makefile` con targets básicos ampliables.

**Non-Goals:**
- No se instala Symfony todavía (eso es el siguiente paso, sección 9.2 de `Especificaciones.md`). El `Dockerfile` de `diary-php` deja el contenedor listo para correr `composer create-project`, pero no lo ejecuta.
- No se dockerizan Ollama ni Open WebUI — ya están desplegados en `192.168.4.200` y se consumen por URL.
- No se configura HTTPS/TLS en `diary-nginx` — fuera de alcance para entorno de desarrollo local.
- No se implementa CI/CD ni despliegue en el mini PC destino todavía.

## Decisions

### 1. Nomenclatura de servicios y contenedores
`container_name` explícito en cada servicio con prefijo `diary-` (`diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis`, `diary-messenger-worker`), tal como pide el usuario. Los nombres de servicio de Compose (clave del YAML) coinciden con el `container_name` para simplificar referencias (`depends_on`, DSN internos como `postgres:5432` → aquí será `diary-postgres:5432`).

### 2. Versionado de imágenes vía `.env`
Cada servicio con imagen oficial usa una variable `*_VERSION`:
```
PHP_VERSION=8.4
POSTGRES_VERSION=16
REDIS_VERSION=7
NGINX_VERSION=1.27
```
`diary-php` y `diary-messenger-worker` construyen desde `docker/php/Dockerfile`, que recibe `PHP_VERSION` como `ARG` (`FROM php:${PHP_VERSION}-fpm`). `diary-postgres`, `diary-redis` y `diary-nginx` usan `image: postgres:${POSTGRES_VERSION}`, etc. directamente.

### 3. Puerto único publicado: 9008 → diary-nginx
Solo `diary-nginx` publica puerto al host (`${NGINX_HTTP_PORT:-9008}:80`). `diary-php`, `diary-postgres`, `diary-redis` no publican puertos — se acceden solo desde la red interna de Compose. Esto evita colisiones con Ollama/Open WebUI (11434, 9006) y con Portainer (9000/9090) ya usados en la infraestructura del usuario. Puertos de depuración (p. ej. exponer Postgres para un cliente SQL local) quedan como variables opcionales en `.env` (`POSTGRES_HOST_PORT`) pero comentadas/deshabilitadas por defecto — el usuario las activa si las necesita.

### 4. Bind mounts configurables por `.env`, no named volumes
Todas las rutas de persistencia (logs y datos) son variables de `.env` con un valor por defecto relativo al repo:
```
LOGS_PATH=./logs
DATA_PATH=./data
```
Y cada mount se compone a partir de esas raíces:
- Logs: `${LOGS_PATH}/nginx`, `${LOGS_PATH}/php`, `${LOGS_PATH}/postgres`, `${LOGS_PATH}/redis`, `${LOGS_PATH}/messenger-worker`
- Datos: `${DATA_PATH}/postgres` (→ `/var/lib/postgresql/data`), `${DATA_PATH}/audio` (→ `AudioRecording.file_path`, compartido entre `diary-php` y `diary-messenger-worker`), `${DATA_PATH}/transcriptions` (→ `Transcription.file_path`, ídem)

Se elige bind mount sobre volumen nombrado de Docker porque el usuario pidió explícitamente "puntos de montaje" configurables y porque simplifica inspeccionar/hacer backup de logs y datos sin `docker cp`. Trade-off: en Linux, permisos de UID/GID del proceso dentro del contenedor deben alinearse con el host (ver Riesgos).

### 5. `diary-messenger-worker` como servicio separado, misma imagen que `diary-php`
Ya decidido en `Especificaciones.md` sección 7. Se construye con el mismo `Dockerfile` (`docker/php/Dockerfile`) pero `command: php bin/console messenger:consume async -vv` en vez de `php-fpm`. Comparte los mismos bind mounts de `${DATA_PATH}/audio` y `${DATA_PATH}/transcriptions` que `diary-php` (ambos deben ver los mismos ficheros, ver sección 7 de `Especificaciones.md`).

### 6. `.env` real ignorado por git, `.env.example` como plantilla versionada
`.env.example` contiene todas las claves con valores por defecto sensatos (los del punto 3 y 4). `.gitignore` añade `.env`, `${LOGS_PATH}` (`logs/`) y `${DATA_PATH}` (`data/`) para no versionar logs ni datos reales.

### 7. Makefile fino, delegando en `docker compose`
Targets iniciales: `up`, `down`, `build`, `restart`, `logs` (acepta `SERVICE=`), `sh` (shell dentro de `diary-php`), `ps`. Cada target es una línea que envuelve `docker compose --env-file .env ...` — sin lógica propia, para que sea trivial ampliarlo en cambios futuros (p. ej. `composer`, `console`, `test` cuando exista Symfony).

## Risks / Trade-offs

- **[Riesgo] Permisos de bind mount en Linux**: confirmado en la práctica durante este cambio. `diary-redis` no arrancaba (`FATAL CONFIG FILE ERROR: Can't open the log file: Permission denied`) porque Compose crea el directorio de bind mount como `root:root 755` y el proceso `redis-server` corre como usuario no root `redis` (uid 999) dentro del contenedor oficial, que no puede escribir ahí. Se resolvió con `docker run --rm -v "$(pwd)/logs/redis:/target" redis:7 chown -R 999:999 /target` antes de levantar el servicio. El mismo problema puede aparecer en `diary-php`/`diary-messenger-worker` (`www-data`, uid 33) en cuanto escriban logs de aplicación. → Mitigación aplicada: dejar constancia aquí; pendiente de documentar en el `Makefile`/README un paso `make up` que cree y ajuste permisos de `${LOGS_PATH}/*` y `${DATA_PATH}/*` automáticamente (o fijar `user:` por servicio) en un cambio futuro si vuelve a darse.
- **[Riesgo] `.env.example` desincronizado con `docker-compose.yml`**: si se añade una variable nueva al compose y no al ejemplo, el `docker compose up` de otra persona/máquina fallará silenciosamente con valores vacíos. → Mitigación: mantener ambos ficheros en el mismo commit siempre; no hay validación automática todavía (fuera de alcance de este cambio).
- **[Trade-off] Sin healthchecks todavía**: no se añaden `healthcheck` a los servicios en este cambio (mantenerlo mínimo). `depends_on` solo controla orden de arranque, no disponibilidad real (p. ej. Postgres aceptando conexiones). Se puede añadir en un cambio posterior si causa problemas reales en el arranque de `diary-php`.

## Migration Plan

No aplica (no hay entorno previo desplegado). Pasos de puesta en marcha:
1. `cp .env.example .env` (y ajustar si hace falta).
2. `make build && make up`.
3. Verificar `diary-nginx` responde en `http://localhost:9008` (devolverá 502/404 hasta que exista código Symfony — se confirma solo que el proxy y el contenedor `diary-php` están vivos).

## Open Questions

- Ninguna bloqueante para este cambio. Pendiente para el siguiente paso (bootstrap de Symfony): decidir si `composer create-project` se ejecuta dentro del contenedor `diary-php` ya construido, o se genera el skeleton antes de construir la imagen.
