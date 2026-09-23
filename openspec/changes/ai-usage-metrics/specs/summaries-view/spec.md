## MODIFIED Requirements

### Requirement: Vista Resúmenes con últimos 5 por defecto
El sistema SHALL exponer una vista "Resúmenes" (`/resumenes`) que, sin filtro de rango aplicado, muestra los 5 `DailySummary` más recientes ordenados por fecha descendente, cada uno con su fecha, `summary_text`, los `Topic` asociados, el número total de `AudioRecording` recibidos ese día (cualquier estado) y un pie de métricas con tokens de entrada, tokens de salida, tokens totales, tiempo de generación y modelo.

#### Scenario: Número de audios del día
- **WHEN** un `DailySummary` mostrado corresponde a un día con 3 `AudioRecording` recibidos
- **THEN** el resumen muestra "3 audios" junto a su fecha

#### Scenario: Acceso sin filtro
- **WHEN** el usuario visita `/resumenes` sin parámetros de rango
- **THEN** se muestran como máximo los 5 `DailySummary` más recientes, ordenados de más reciente a más antiguo

#### Scenario: Menos de 5 resúmenes generados
- **WHEN** existen menos de 5 `DailySummary` en toda la aplicación
- **THEN** la vista muestra todos los existentes, sin error ni hueco vacío

#### Scenario: Métricas del resumen
- **WHEN** un `DailySummary` mostrado tiene `prompt_tokens = 2980`, `completion_tokens = 432`, `generation_ms = 38000` y `model = "qwen2.5:7b"`
- **THEN** su pie de métricas muestra entrada 2.980, salida 432, total 3.412, 38 s y `qwen2.5:7b`
