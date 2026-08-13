## ADDED Requirements

### Requirement: Entidad `Reminder`
El sistema SHALL definir una entidad Doctrine `Reminder` con `date` (tipo `date`, sin constraint unique — puede haber varios recordatorios el mismo día), `text`, `created_at`, `updated_at`.

#### Scenario: Varios recordatorios el mismo día
- **WHEN** se persisten dos `Reminder` con la misma `date`
- **THEN** ambos se guardan correctamente, sin conflicto de constraint

## MODIFIED Requirements

### Requirement: Migraciones versionadas aplicadas
El sistema SHALL generar migraciones Doctrine versionadas para todas las entidades del modelo de dominio, incluyendo `Reminder`, y aplicarlas contra `diary-postgres`, en vez de usar `doctrine:schema:update` directo.

#### Scenario: Esquema aplicado
- **WHEN** se ejecuta `bin/console doctrine:migrations:status` dentro de `diary-php`
- **THEN** todas las migraciones generadas figuran como ejecutadas, incluyendo la de `reminder`
