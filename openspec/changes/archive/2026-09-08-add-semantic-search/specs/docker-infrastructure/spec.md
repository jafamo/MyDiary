## MODIFIED Requirements

### Requirement: Versión de imagen configurable por servicio
El sistema SHALL permitir fijar la versión de imagen de cada servicio mediante variables de entorno en `.env` (`PHP_VERSION`, `POSTGRES_VERSION`, `REDIS_VERSION`, `NGINX_VERSION`), sin necesidad de editar `docker-compose.yml`. `diary-postgres` SHALL usar la imagen `pgvector/pgvector:pg${POSTGRES_VERSION}` (en vez de la imagen oficial `postgres`), que incluye la extensión `pgvector` necesaria para la búsqueda semántica, manteniendo el resto de configuración (usuario, base de datos, volúmenes) sin cambios.

#### Scenario: Cambiar versión de PostgreSQL
- **WHEN** el usuario cambia `POSTGRES_VERSION` en `.env` y relanza `make build && make up`
- **THEN** `diary-postgres` arranca con la imagen `pgvector/pgvector:pg<nueva versión>`

#### Scenario: Extensión pgvector disponible tras el cambio de imagen
- **WHEN** `diary-postgres` arranca con la imagen `pgvector/pgvector:pg${POSTGRES_VERSION}`
- **THEN** la extensión `vector` está disponible para activarse con `CREATE EXTENSION IF NOT EXISTS vector;`
