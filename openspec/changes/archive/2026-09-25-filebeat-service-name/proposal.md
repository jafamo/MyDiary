## Why

Elasticsearch y Kibana en `zeus` son compartidos con otros servicios del host (índice `filebeat-*`), y los eventos que envía el Filebeat de MyDiary no llevan ningún campo que identifique la aplicación: solo `log_service` por componente (`nginx`, `php`, `postgres`, `redis`). No se pueden aislar en Kibana los logs de MyDiary del resto.

## What Changes

- Filebeat añade a **todos** sus eventos el campo ECS `service.name` con valor `mydiary`, mediante un processor `add_fields` global en `docker/filebeat/filebeat.yml`.
- `log_service` se mantiene tal cual para distinguir cada componente dentro de MyDiary.

## Capabilities

### New Capabilities

### Modified Capabilities
- `structured-logging`: nuevo requisito — todos los eventos enviados por el Filebeat de MyDiary llevan `service.name: mydiary`.

## Impact

- Configuración: `docker/filebeat/filebeat.yml`.
- Despliegue: recrear `diary-filebeat` en producción. Solo afecta a los eventos nuevos; los ya indexados no llevan el campo.
- Sin cambios de código PHP, migraciones ni tests PHPUnit.
