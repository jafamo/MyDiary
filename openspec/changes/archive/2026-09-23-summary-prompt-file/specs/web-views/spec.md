## ADDED Requirements

### Requirement: Texto del resumen diario con párrafos
El sistema SHALL mostrar el `summaryText` de un `DailySummary` respetando sus saltos de línea y separación en párrafos en todas las vistas web donde aparece (Diario, Resúmenes y Búsqueda). El texto SHALL seguir mostrándose escapado como texto plano, sin interpretar HTML ni Markdown.

#### Scenario: Resumen de varios párrafos en Diario
- **WHEN** el `DailySummary` del día tiene un `summaryText` con dos párrafos separados por una línea en blanco
- **THEN** la vista Diario muestra ambos párrafos visualmente separados, no unidos en un único bloque

#### Scenario: El texto no se interpreta como HTML
- **WHEN** el `summaryText` contiene caracteres como `<` o `&`
- **THEN** la vista los muestra literalmente, escapados, sin interpretarlos como marcado

### Requirement: Leyenda de emojis bajo el resumen diario
El sistema SHALL mostrar, bajo el texto de cada `DailySummary` en las vistas web donde aparece (Diario, Resúmenes y Búsqueda), su leyenda de emojis guardada (`emoji_legend`), cada emoji con su significado. Si la leyenda es `null` o está vacía, SHALL omitirse.

#### Scenario: Leyenda visible en Diario
- **WHEN** el resumen del día tiene la leyenda `💼 Trabajo`, `✅ Pendientes`
- **THEN** la vista Diario muestra bajo el texto `💼 Trabajo · ✅ Pendientes`

#### Scenario: Resumen sin leyenda
- **WHEN** el resumen tiene `emoji_legend` `null` (p. ej. resúmenes antiguos)
- **THEN** la vista no muestra ninguna leyenda
