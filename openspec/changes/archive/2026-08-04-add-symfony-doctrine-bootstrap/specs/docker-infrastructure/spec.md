## MODIFIED Requirements

### Requirement: Servicios Docker Compose con prefijo de nombre
El sistema SHALL definir en `docker-compose.yml` los servicios `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`, cada uno con `container_name` prefijado por `diary-`. `diary-messenger-worker` SHALL ejecutar `php bin/console messenger:consume async -vv` (no un comando placeholder).

#### Scenario: Levantar el stack
- **WHEN** se ejecuta `docker compose up -d` (o `make up`) con un `.env` válido
- **THEN** los cinco contenedores arrancan con nombre `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`

#### Scenario: Worker de Messenger activo
- **WHEN** se inspecciona el proceso principal de `diary-messenger-worker`
- **THEN** corresponde a `php bin/console messenger:consume async -vv`, no a `tail -f /dev/null`

### Requirement: Acceso único a Symfony por el puerto 9008
El sistema SHALL publicar únicamente el puerto del servicio `diary-nginx` hacia el host, mapeado al 9008 por defecto (configurable vía `.env`). Ningún otro servicio SHALL publicar puertos al host por defecto. `diary-nginx` SHALL servir la aplicación Symfony instalada en `public/`, no una respuesta 404 por ausencia de código.

#### Scenario: Acceso a la aplicación
- **WHEN** el stack está levantado
- **THEN** `http://localhost:9008` llega al contenedor `diary-nginx`, que actúa de proxy hacia `diary-php`, y devuelve una respuesta de la aplicación Symfony (no un 404 de nginx por `public/` inexistente)

#### Scenario: Servicios internos no expuestos
- **WHEN** se inspecciona `docker compose ps`
- **THEN** `diary-php`, `diary-postgres` y `diary-redis` no tienen puertos publicados al host
