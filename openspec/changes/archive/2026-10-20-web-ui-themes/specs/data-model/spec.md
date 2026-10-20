## MODIFIED Requirements

### Requirement: Entidad `User`
El sistema SHALL definir una entidad Doctrine `User` (implementando `PasswordAuthenticatedUserInterface`/`UserInterface` de Symfony Security) con `username` (unique), `password_hash`, `roles` (json, p. ej. `["ROLE_USER"]`) y `ui_theme` (string mapeado al enum `UiTheme`, `NOT NULL`, por defecto `moderno`).

#### Scenario: Nombre de usuario único
- **WHEN** se intenta persistir dos `User` con el mismo `username`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `username`

#### Scenario: Tema por defecto en usuarios existentes
- **WHEN** se aplica la migración que añade `ui_theme` sobre una base con usuarios ya creados
- **THEN** todos los usuarios existentes quedan con `ui_theme = 'moderno'`
