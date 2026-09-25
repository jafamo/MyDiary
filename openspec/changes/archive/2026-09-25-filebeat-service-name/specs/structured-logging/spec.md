## ADDED Requirements

### Requirement: Nombre de servicio en todos los eventos de Filebeat
El Filebeat de MyDiary SHALL añadir a todos los eventos que envía a Elasticsearch el campo ECS `service.name` con valor `mydiary`, independientemente del componente de origen, manteniendo `log_service` para identificar el componente.

#### Scenario: Log de la aplicación PHP
- **WHEN** Filebeat envía una línea de `/logs/php/*.log` o `/logs/messenger-worker/*.log`
- **THEN** el evento tiene `service.name: mydiary` y `log_service: php`

#### Scenario: Log de acceso de nginx
- **WHEN** Filebeat envía una línea de `/logs/nginx/access.log`
- **THEN** el evento tiene `service.name: mydiary`, `log_service: nginx` y `log_type: access`

#### Scenario: Log de Postgres o Redis
- **WHEN** Filebeat envía una línea de `/logs/postgres/*.log` o `/logs/redis/*.log`
- **THEN** el evento tiene `service.name: mydiary` y el `log_service` correspondiente
