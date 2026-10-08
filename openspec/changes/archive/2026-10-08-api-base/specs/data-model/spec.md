## ADDED Requirements

### Requirement: Entidad `ApiToken`
El sistema SHALL definir una entidad Doctrine `ApiToken` (tabla `api_token`) con `user` (relación N:1 con `User`, `onDelete: CASCADE`), `token_hash` (64 caracteres, unique), `name` (nombre del dispositivo, hasta 100 caracteres), `created_at` y `last_used_at` (nullable). La tabla MUST NOT guardar el token en claro.

#### Scenario: Hash único
- **WHEN** se inspecciona la migración generada para `api_token`
- **THEN** existe una constraint `UNIQUE` sobre `token_hash`

#### Scenario: Borrado en cascada con el usuario
- **WHEN** se elimina un `User` que tiene tokens
- **THEN** sus filas de `api_token` se eliminan por la constraint `onDelete: CASCADE`
