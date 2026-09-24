## Purpose

Gestión manual de `Topic`: permite corregir los temas creados automáticamente al generar los `DailySummary`, fusionando duplicados y renombrándolos, para que el ranking de Estadísticas y las etiquetas de Resúmenes no se ensucien con el tiempo.

## Requirements

### Requirement: Vista de gestión de temas
El sistema SHALL exponer una vista "Temas" (`/topics`) que presenta todos los `Topic` existentes en una tabla con una fila por `Topic` y las columnas: selección para fusionar, `name`, número de `DailySummary` asociados, último uso (fecha del `DailySummary` más reciente asociado, vacía si no tiene ninguno) y acción de renombrar. Por defecto las filas se ordenan por número de `DailySummary` asociados descendente y, a igualdad, por `name` ascendente. La tabla SHALL permitir filtrar por nombre, reordenar por columna y paginar sin recargar la página (25 filas por página por defecto, con opción de 50 y 100), y la selección de temas para fusionar SHALL conservarse aunque el filtro oculte filas seleccionadas o estén en otra página.

#### Scenario: Listado en tabla ordenado por frecuencia
- **WHEN** el usuario visita `/topics`
- **THEN** se muestra una tabla con todos los `Topic`, ordenados de mayor a menor número de `DailySummary` asociados, con su nombre, su número de resúmenes y su fecha de último uso

#### Scenario: Tema sin resúmenes asociados
- **WHEN** existe un `Topic` sin ningún `DailySummary` asociado
- **THEN** aparece en la tabla con 0 resúmenes y la columna de último uso vacía

#### Scenario: Sin temas todavía
- **WHEN** el usuario visita `/topics` y no existe ningún `Topic`
- **THEN** la vista se muestra sin error, indicando que no hay temas todavía

#### Scenario: Búsqueda por nombre
- **WHEN** el usuario escribe "trab" en el campo de búsqueda
- **THEN** solo quedan visibles las filas cuyo `name` contiene "trab" sin distinguir mayúsculas/minúsculas ni acentos, sin recargar la página

#### Scenario: La selección sobrevive al filtro
- **WHEN** el usuario marca "curro", después filtra por "trabajo" (ocultando "curro"), marca "trabajo" y envía la fusión
- **THEN** la confirmación de fusión incluye tanto "curro" como "trabajo"

#### Scenario: Paginación
- **WHEN** existen 60 `Topic` y el usuario visita `/topics`
- **THEN** se muestran las 25 primeras filas, con controles para ir a las páginas 2 y 3 y la indicación "1–25 de 60"

#### Scenario: Paginación sobre el resultado filtrado
- **WHEN** el usuario está en la página 3 y escribe un término de búsqueda
- **THEN** la tabla vuelve a la página 1 y la paginación se recalcula sobre las filas que coinciden

#### Scenario: Cambio de tamaño de página
- **WHEN** el usuario elige 100 filas por página
- **THEN** se muestran hasta 100 filas y la paginación se recalcula, volviendo a la página 1

#### Scenario: La selección sobrevive al cambio de página
- **WHEN** el usuario marca "curro" en la página 1, pasa a la página 2, marca "trabajo" y envía la fusión
- **THEN** la confirmación de fusión incluye tanto "curro" como "trabajo"

#### Scenario: Ordenación por columna
- **WHEN** el usuario pulsa la cabecera de la columna "Nombre" (o "Resúmenes", o "Último uso")
- **THEN** las filas se reordenan por esa columna (sobre todas las filas filtradas, no solo la página visible), se vuelve a la página 1, y una segunda pulsación invierte el sentido

#### Scenario: Destino restringido a los seleccionados
- **WHEN** el usuario tiene marcados "curro" y "trabajo"
- **THEN** la barra de fusión indica 2 temas seleccionados y el selector de destino ofrece solo "curro" y "trabajo"

#### Scenario: Aviso de seleccionados fuera de la vista
- **WHEN** el usuario tiene marcados 3 temas y el filtro o la página actual oculta 2 de ellos
- **THEN** la barra de fusión indica 3 temas seleccionados y avisa de que 2 están fuera de la vista

#### Scenario: Funcionamiento sin JavaScript
- **WHEN** la página se usa con JavaScript desactivado
- **THEN** la tabla se muestra completa y sin paginar en el orden por defecto y la fusión y el renombrado siguen funcionando, con el selector de destino listando todos los `Topic`

### Requirement: Renombrar un tema
El sistema SHALL permitir renombrar un `Topic` existente desde la vista de gestión, validando que el nuevo `name` no coincida (de forma case-insensitive) con el de otro `Topic` ya existente.

#### Scenario: Renombrado válido
- **WHEN** el usuario renombra el `Topic` "curro" a "trabajo" y no existe ya un `Topic` "trabajo"
- **THEN** el `Topic` pasa a llamarse "trabajo" y sigue asociado a los mismos `DailySummary` que antes

#### Scenario: Renombrado a un nombre duplicado
- **WHEN** el usuario intenta renombrar un `Topic` a un `name` que ya usa otro `Topic` (comparando sin distinguir mayúsculas/minúsculas)
- **THEN** el sistema rechaza el renombrado y muestra un error indicando que ya existe un tema con ese nombre

### Requirement: Fusionar temas duplicados
El sistema SHALL permitir fusionar dos o más `Topic` en un `Topic` superviviente elegido por el usuario: todos los `DailySummary` asociados a los `Topic` fusionados quedan asociados al superviviente (sin duplicar la asociación si un `DailySummary` ya estaba vinculado a ambos), y los `Topic` fusionados se eliminan.

#### Scenario: Fusión de dos temas sin solapamiento
- **WHEN** el usuario fusiona el `Topic` "curro" (asociado a los `DailySummary` A y B) dentro del `Topic` "trabajo" (asociado al `DailySummary` C)
- **THEN** "trabajo" queda asociado a A, B y C; el `Topic` "curro" deja de existir

#### Scenario: Fusión con `DailySummary` compartido
- **WHEN** el usuario fusiona el `Topic` "curro" dentro del `Topic` "trabajo" y el `DailySummary` D ya estaba asociado a ambos
- **THEN** tras la fusión, D queda asociado a "trabajo" una sola vez (sin fila duplicada ni error de constraint)

#### Scenario: Confirmación antes de fusionar
- **WHEN** el usuario inicia una fusión de temas
- **THEN** el sistema muestra los nombres de los `Topic` origen, el `Topic` destino y el número de `DailySummary` afectados, y solo ejecuta la fusión tras confirmación explícita
