## MODIFIED Requirements

### Requirement: Servicios Docker Compose con prefijo de nombre
El sistema SHALL definir en `docker-compose.yml` los servicios `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`, cada uno con `container_name` prefijado por `diary-`. `diary-messenger-worker` SHALL ejecutar `php bin/console messenger:consume async scheduler_default -vv` (no un comando placeholder), consumiendo tanto el transporte asíncrono de la aplicación como el del Scheduler.

#### Scenario: Levantar el stack
- **WHEN** se ejecuta `docker compose up -d` (o `make up`) con un `.env` válido
- **THEN** los cinco contenedores arrancan con nombre `diary-nginx`, `diary-php`, `diary-postgres`, `diary-redis` y `diary-messenger-worker`

#### Scenario: Worker de Messenger activo
- **WHEN** se inspecciona el proceso principal de `diary-messenger-worker`
- **THEN** corresponde a `php bin/console messenger:consume async scheduler_default -vv`, no a `tail -f /dev/null`

#### Scenario: Worker consume también el Scheduler
- **WHEN** llega la hora configurada del disparo diario (21:00 Europe/Madrid)
- **THEN** `diary-messenger-worker` es el proceso que recibe y ejecuta el mensaje del Scheduler, sin necesidad de un contenedor adicional
