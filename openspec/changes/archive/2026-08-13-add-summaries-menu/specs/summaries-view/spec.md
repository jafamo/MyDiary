## ADDED Requirements

### Requirement: Vista Resúmenes con últimos 5 por defecto
El sistema SHALL exponer una vista "Resúmenes" (`/resumenes`) que, sin filtro de rango aplicado, muestra los 5 `DailySummary` más recientes ordenados por fecha descendente, cada uno con su fecha, `summary_text`, los `Topic` asociados y el número total de `AudioRecording` recibidos ese día (cualquier estado).

#### Scenario: Número de audios del día
- **WHEN** un `DailySummary` mostrado corresponde a un día con 3 `AudioRecording` recibidos
- **THEN** el resumen muestra "3 audios" junto a su fecha

#### Scenario: Acceso sin filtro
- **WHEN** el usuario visita `/resumenes` sin parámetros de rango
- **THEN** se muestran como máximo los 5 `DailySummary` más recientes, ordenados de más reciente a más antiguo

#### Scenario: Menos de 5 resúmenes generados
- **WHEN** existen menos de 5 `DailySummary` en toda la aplicación
- **THEN** la vista muestra todos los existentes, sin error ni hueco vacío

### Requirement: Filtro por rango de fechas con paginación
El sistema SHALL permitir filtrar la vista Resúmenes por un rango de fechas (`from`/`to`) y, cuando el filtro es válido, SHALL sustituir el listado de "últimos 5" por todos los `DailySummary` dentro de ese rango (ambos extremos incluidos), ordenados por fecha descendente y paginados. El filtro SHALL funcionar sin JavaScript (recarga de página con los parámetros en la URL).

#### Scenario: Aplicar un rango de fechas
- **WHEN** el usuario aplica el filtro con `from=2026-07-01` y `to=2026-07-31`
- **THEN** la vista muestra los `DailySummary` con fecha dentro de ese rango, paginados, en vez de los últimos 5

#### Scenario: Navegar a la siguiente página
- **WHEN** el usuario, con un rango de fechas aplicado, navega a la página 2
- **THEN** se muestra el siguiente bloque de `DailySummary` del mismo rango, conservando `from`/`to` en la URL

#### Scenario: Rango inválido recae en el listado por defecto
- **WHEN** la URL contiene `from`/`to` no parseables como fecha, o `from` posterior a `to`
- **THEN** el sistema ignora el filtro y muestra el listado por defecto de los últimos 5 resúmenes, sin error

#### Scenario: Rango sin resúmenes
- **WHEN** el rango de fechas aplicado no contiene ningún `DailySummary`
- **THEN** la vista muestra un listado vacío con un mensaje indicándolo, sin controles de paginación

#### Scenario: Página fuera de rango
- **WHEN** el parámetro `page` solicitado supera el número total de páginas disponibles para el rango filtrado
- **THEN** el sistema muestra la última página válida en vez de un listado vacío

### Requirement: Etiquetas de tema por resumen
Cada `DailySummary` mostrado en la vista Resúmenes SHALL listar sus `Topic` asociados como etiquetas visibles junto al resumen.

#### Scenario: Resumen con temas
- **WHEN** un `DailySummary` tiene `Topic` asociados
- **THEN** sus nombres se muestran como etiquetas junto al resumen

#### Scenario: Resumen sin temas
- **WHEN** un `DailySummary` no tiene ningún `Topic` asociado
- **THEN** el resumen se muestra sin etiquetas, sin error

### Requirement: Enlace a los audios del día
Cada `DailySummary` mostrado en la vista Resúmenes SHALL mostrar el número total de `AudioRecording` de ese día como un enlace que lleve a Historial (`/historial?date=<fecha del resumen>`), donde se muestran todos los `AudioRecording` de ese día.

#### Scenario: Ir a los audios de un resumen
- **WHEN** el usuario pulsa el enlace del número de audios de un `DailySummary` con fecha `2026-07-15`
- **THEN** el sistema navega a `/historial?date=2026-07-15`, mostrando el calendario de ese mes con el día seleccionado y su log de audios
