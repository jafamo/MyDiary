## Why

El proyecto no tiene ninguna infraestructura ejecutable todavía: ni `composer.json`, ni contenedores, ni forma de levantar PHP/PostgreSQL/Redis localmente. Antes de escribir la base de Symfony (paso 2 del orden de construcción en `Especificaciones.md`), hace falta el entorno Docker Compose sobre el que correrá — ya con las decisiones de infraestructura confirmadas (Redis como transporte de Messenger, Whisper local vía Open WebUI en `192.168.4.200:9006`, Ollama en `192.168.4.200:11434`).

## What Changes

- Añadir `docker-compose.yml` en la raíz con los servicios: `diary-nginx`, `diary-php` (imagen custom vía `Dockerfile`), `diary-postgres`, `diary-redis`, `diary-messenger-worker` (misma imagen que `diary-php`, comando `messenger:consume`).
- Añadir `docker/php/Dockerfile`: imagen PHP 8.4-fpm con extensiones necesarias para Symfony/Doctrine (`pdo_pgsql`, `intl`, `opcache`, `zip`, etc.).
- Añadir configuración de `diary-nginx` (`docker/nginx/default.conf`) como proxy hacia `diary-php`, publicando el puerto **9008** del host hacia el 80 del contenedor — único puerto expuesto del stack.
- Añadir `.env.example` versionado con: puertos de cada servicio, rutas de bind mount (logs y datos), y versión de imagen de cada servicio (`PHP_VERSION`, `POSTGRES_VERSION`, `REDIS_VERSION`, `NGINX_VERSION`). `.env` real queda ignorado por git.
- Todos los nombres de contenedor llevan el prefijo `diary-` (`diary-php`, `diary-nginx`, `diary-postgres`, `diary-redis`, `diary-messenger-worker`).
- Logs de cada servicio en bind mount a `./logs/<servicio>/` (p. ej. `./logs/nginx`, `./logs/php`, `./logs/postgres`, `./logs/redis`).
- Datos persistentes en bind mounts configurables por `.env`: PostgreSQL (`postgres_data`), audio descargado de Telegram (`audio_storage`), exports de transcripción (`transcription_exports`).
- Añadir `Makefile` en la raíz con targets iniciales para el ciclo de vida de los contenedores (`up`, `down`, `build`, `logs`, `sh`, etc.), ampliable en cambios futuros.
- Añadir entradas correspondientes a `.gitignore` (`.env`, `logs/`, `data/` o equivalentes).

## Capabilities

### New Capabilities
- `docker-infrastructure`: entorno de contenedores Docker Compose del proyecto (servicios, red, volúmenes, logs, versionado por `.env`) y los comandos Make para operarlo.

### Modified Capabilities
(ninguna — no existen specs previas en `openspec/specs/`)

## Impact

- Ficheros nuevos: `docker-compose.yml`, `docker/php/Dockerfile`, `docker/nginx/default.conf`, `.env.example`, `Makefile`, `.gitignore` (actualizado).
- No afecta código de aplicación todavía (no existe `src/` ni `composer.json`); es infraestructura pura sobre la que se construirá Symfony en el siguiente paso.
- Dependencia externa: acceso de red a `192.168.4.200:9006` (Open WebUI/Whisper) y `192.168.4.200:11434` (Ollama), ya confirmado y documentado en `Especificaciones.md`.
