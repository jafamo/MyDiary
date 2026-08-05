## 1. Estructura de directorios y ficheros base

- [x] 1.1 Crear `docker/php/`, `docker/nginx/` en la raíz del repo
- [x] 1.2 Añadir `.gitignore` (o actualizar el existente) con `.env`, `logs/`, `data/`

## 2. Imagen PHP (`diary-php` / `diary-messenger-worker`)

- [x] 2.1 Crear `docker/php/Dockerfile`: `FROM php:${PHP_VERSION}-fpm`, instalar extensiones `pdo_pgsql`, `intl`, `opcache`, `zip`, `redis` (pecl), y Composer
- [x] 2.2 Verificar `docker build` de la imagen sin errores (aún sin código Symfony dentro)

## 3. Nginx (`diary-nginx`)

- [x] 3.1 Crear `docker/nginx/default.conf`: server block proxy_pass hacia `diary-php:9000` (fastcgi), `root` apuntando a `/var/www/html/public`
- [x] 3.2 Configurar acceso/error log de nginx hacia rutas dentro del contenedor que se montarán en `${LOGS_PATH}/nginx`

## 4. `.env.example` y variables

- [x] 4.1 Definir variables de versión: `PHP_VERSION`, `POSTGRES_VERSION`, `REDIS_VERSION`, `NGINX_VERSION`
- [x] 4.2 Definir variable de puerto publicado: `NGINX_HTTP_PORT=9008`
- [x] 4.3 Definir variables de rutas base: `LOGS_PATH=./logs`, `DATA_PATH=./data`
- [x] 4.4 Definir credenciales/DSN de Postgres para desarrollo local (`POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`)
- [x] 4.5 Copiar `.env.example` a `.env` localmente para pruebas (no versionar `.env`)

## 5. `docker-compose.yml`

- [x] 5.1 Definir servicio `diary-postgres` (imagen `postgres:${POSTGRES_VERSION}`, `container_name: diary-postgres`, bind mounts de datos y logs, variables de entorno de credenciales)
- [x] 5.2 Definir servicio `diary-redis` (imagen `redis:${REDIS_VERSION}`, `container_name: diary-redis`, bind mount de logs)
- [x] 5.3 Definir servicio `diary-php` (build desde `docker/php/Dockerfile`, `container_name: diary-php`, bind mounts de código, `${DATA_PATH}/audio`, `${DATA_PATH}/transcriptions`, `${LOGS_PATH}/php`)
- [x] 5.4 Definir servicio `diary-messenger-worker` (misma build que `diary-php`, `container_name: diary-messenger-worker`, mismos bind mounts de datos, `command` de arranque distinto — placeholder hasta que exista `bin/console`, `depends_on: diary-redis`, `${LOGS_PATH}/messenger-worker`)
- [x] 5.5 Definir servicio `diary-nginx` (imagen `nginx:${NGINX_VERSION}`, `container_name: diary-nginx`, monta `docker/nginx/default.conf`, publica `${NGINX_HTTP_PORT:-9008}:80`, `depends_on: diary-php`, `${LOGS_PATH}/nginx`)
- [x] 5.6 Confirmar que solo `diary-nginx` publica puertos al host; el resto sin `ports:`
- [x] 5.7 Añadir red interna común de Compose para los cinco servicios

## 6. Makefile

- [x] 6.1 Target `up`: `docker compose --env-file .env up -d`
- [x] 6.2 Target `down`: `docker compose --env-file .env down`
- [x] 6.3 Target `build`: `docker compose --env-file .env build`
- [x] 6.4 Target `restart`: `down` + `up`
- [x] 6.5 Target `logs` (acepta `SERVICE=`): `docker compose --env-file .env logs -f $(SERVICE)`
- [x] 6.6 Target `ps`: `docker compose --env-file .env ps`
- [x] 6.7 Target `sh`: shell interactiva dentro de `diary-php` (`docker compose exec diary-php sh` o `bash` según disponibilidad en la imagen)

## 7. Verificación

- [x] 7.1 `make build && make up` levanta los cinco contenedores con nombre `diary-*` sin errores
- [x] 7.2 `docker compose ps` confirma que solo `diary-nginx` tiene puerto publicado (9008)
- [x] 7.3 `curl -I http://localhost:9008` devuelve respuesta de nginx (502/404 esperado, aún sin app Symfony) — confirmado 404
- [x] 7.4 Tras `make up`, existen ficheros/directorios en `${LOGS_PATH}/nginx`, `${LOGS_PATH}/php`, `${LOGS_PATH}/postgres`, `${LOGS_PATH}/redis`, `${LOGS_PATH}/messenger-worker`
- [x] 7.5 `make down && make up` conserva los datos en `${DATA_PATH}/postgres` (verificado con tabla de prueba `smoke_test`, insertada antes de `down` y presente después de `up`)
- [x] 7.6 Cambiar `POSTGRES_VERSION` en `.env` arranca con la nueva versión de imagen (verificado con `postgres:15`: se descargó y ejecutó la imagen correcta; PostgreSQL rechazó el arranque por incompatibilidad de versión mayor con el data dir ya inicializado en 16 — comportamiento nativo de Postgres, no un fallo de la parametrización). Revertido a `POSTGRES_VERSION=16`.
