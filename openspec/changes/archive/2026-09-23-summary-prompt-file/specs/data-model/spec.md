## MODIFIED Requirements

### Requirement: Entidad `DailySummary`
El sistema SHALL definir una entidad Doctrine `DailySummary` con `date` (unique), `summary_text`, `generated_at`, `emoji_legend` (JSON, nullable: lista de `{emoji, meaning}`), y relación N:M con `Topic` a través de tabla pivote `daily_summary_topic`.

#### Scenario: Un resumen por día
- **WHEN** se intenta persistir dos `DailySummary` con la misma `date`
- **THEN** la base de datos rechaza la segunda inserción por la constraint `UNIQUE` sobre `date`

#### Scenario: Resumen sin leyenda
- **WHEN** existe un `DailySummary` creado antes de introducir la leyenda
- **THEN** su `emoji_legend` es `null` y el resto de sus datos no cambia
