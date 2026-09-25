## Context

Filebeat (`diary-filebeat`) lee los logs de nginx, PHP/Messenger, Postgres y Redis y los envía al Elasticsearch compartido del host. Cada input añade `log_service` a primer nivel. No hay ningún campo que identifique la aplicación.

## Goals / Non-Goals

**Goals:** poder filtrar en Kibana todos los logs de MyDiary con `service.name : "mydiary"`.

**Non-Goals:** reindexar los eventos existentes; cambiar `log_service`; cambiar el formato de los logs PHP o nginx.

## Decisions

- **Campo ECS `service.name`** en lugar de un campo plano con prefijo (`log_app`) o de reutilizar `log_service`. Es el campo estándar de ECS, la plantilla de Filebeat ya lo mapea como `keyword`, y ni nginx ni los logs PHP usan una clave `service`, así que no hay conflicto de tipos. Es una excepción consciente a la convención de campos planos con prefijo, que existe para evitar choques con nginx; aquí no los hay.
- **Processor `add_fields` global** (nivel raíz de `filebeat.yml`, con `target: service`) en lugar de repetirlo en cada input: cubre también inputs futuros y genera un objeto `service.name` real, no una clave con punto.

## Risks / Trade-offs

- Si en el futuro un log JSON (PHP o nginx) incluyera una clave `service` a primer nivel con otro tipo, chocaría con este objeto → no usar `service` como clave de contexto en los logs.
- Los eventos anteriores al despliegue no tienen el campo → los filtros por `service.name` solo cubren desde el despliegue.
