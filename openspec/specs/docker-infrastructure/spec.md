## Purpose

Entorno de contenedores Docker Compose del proyecto (servicios, red, volúmenes, logs) y los comandos Make para operarlo. Sirve de base de infraestructura sobre la que corre la aplicación Symfony (`diary-php`/`diary-messenger-worker`), PostgreSQL (`diary-postgres`) y el transporte de Symfony Messenger (`diary-redis`), todo detrás de `diary-nginx`.

## Requirements

### Requirement: Servicios Docker Compose con prefijo de nombre
El sistema SHALL definir en `docker-compose.yml` los servicios `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`, cada uno con `container_name` prefijado por `diary-`.

#### Scenario: Levantar el stack
- **WHEN** se ejecuta `docker compose up -d` (o `make up`) con un `.env` válido
- **THEN** los cinco contenedores arrancan con nombre `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`

### Requirement: Acceso único a Symfony por el puerto 9008
El sistema SHALL publicar únicamente el puerto del servicio `diary-nginx` hacia el host, mapeado al 9008 por defecto (configurable vía `.env`). Ningún otro servicio SHALL publicar puertos al host por defecto.

#### Scenario: Acceso a la aplicación
- **WHEN** el stack está levantado
- **THEN** `http://localhost:9008` llega al contenedor `diary-nginx`, que actúa de proxy hacia `diary-php`

#### Scenario: Servicios internos no expuestos
- **WHEN** se inspecciona `docker compose ps`
- **THEN** `diary-php`, `diary-postgres` y `diary-redis` no tienen puertos publicados al host

### Requirement: Versión de imagen configurable por servicio
El sistema SHALL permitir fijar la versión de imagen de cada servicio mediante variables de entorno en `.env` (`PHP_VERSION`, `POSTGRES_VERSION`, `REDIS_VERSION`, `NGINX_VERSION`), sin necesidad de editar `docker-compose.yml`.

#### Scenario: Cambiar versión de PostgreSQL
- **WHEN** el usuario cambia `POSTGRES_VERSION` en `.env` y relanza `make build && make up`
- **THEN** `diary-postgres` arranca con la imagen `postgres:<nueva versión>`

### Requirement: Configuración de puertos y montajes centralizada en `.env`
El sistema SHALL definir en `.env.example` (versionado) todas las variables necesarias para puertos publicados y rutas de bind mount de logs y datos persistentes. `.env` real SHALL estar excluido de control de versiones.

#### Scenario: Arranque desde cero
- **WHEN** un usuario nuevo clona el repositorio y ejecuta `cp .env.example .env`
- **THEN** obtiene un `.env` funcional con valores por defecto sin necesidad de modificar `docker-compose.yml`

### Requirement: Logs de cada servicio en directorio propio
El sistema SHALL montar el directorio de logs de cada servicio (`diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis`, `diary-messenger-worker`) como bind mount a un subdirectorio propio bajo la ruta configurada por `LOGS_PATH` en `.env`.

#### Scenario: Inspeccionar logs sin entrar al contenedor
- **WHEN** el stack lleva un tiempo corriendo
- **THEN** existen ficheros de log legibles en `${LOGS_PATH}/nginx`, `${LOGS_PATH}/php`, `${LOGS_PATH}/postgres`, `${LOGS_PATH}/redis` y `${LOGS_PATH}/messenger-worker` en el host

### Requirement: Datos persistentes en bind mounts configurables
El sistema SHALL persistir los datos de PostgreSQL, el audio descargado de Telegram y los exports de transcripción en bind mounts bajo la ruta configurada por `DATA_PATH` en `.env`, compartidos entre `diary-php` y `diary-messenger-worker` cuando corresponda (audio y transcripciones).

#### Scenario: Persistencia tras reiniciar el stack
- **WHEN** se ejecuta `docker compose down` y luego `docker compose up -d`
- **THEN** los datos de `${DATA_PATH}/postgres`, `${DATA_PATH}/audio` y `${DATA_PATH}/transcriptions` siguen presentes

#### Scenario: Acceso compartido a ficheros de audio
- **WHEN** `diary-php` descarga un audio de Telegram en `${DATA_PATH}/audio`
- **THEN** `diary-messenger-worker` puede leer ese mismo fichero desde su propio montaje

### Requirement: Makefile con comandos operativos básicos
El sistema SHALL proveer un `Makefile` en la raíz con, al menos, los targets `up`, `down`, `build`, `restart`, `logs` y `sh`, que envuelvan los comandos `docker compose` correspondientes usando el `.env` del proyecto.

#### Scenario: Uso del Makefile
- **WHEN** el usuario ejecuta `make up`
- **THEN** se ejecuta `docker compose --env-file .env up -d` (o equivalente) y el stack queda levantado

#### Scenario: Abrir shell en el contenedor de PHP
- **WHEN** el usuario ejecuta `make sh`
- **THEN** se abre una shell interactiva dentro del contenedor `diary-php`
