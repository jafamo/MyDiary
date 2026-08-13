## MODIFIED Requirements

### Requirement: Vista Estadísticas con filtro de rango y gráfico
El sistema SHALL permitir filtrar las estadísticas por un rango de fechas (15 días, 1 mes, 3 meses, 1 año, o un rango personalizado) y, combinable con lo anterior, por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro). Al aplicar un filtro de estado, el sistema SHALL recalcular sobre ese estado: la serie de audios por día, la media de audios/día, la duración media, el total de audios, la racha de días consecutivos con audio, el día récord y la comparación con el periodo anterior. El desglose de estados de transcripción y el ranking de temas más frecuentes SHALL seguir mostrando siempre todos los estados, sin verse afectados por el filtro de estado.

El sistema SHALL mostrar, además de los indicadores existentes, cuatro señales adicionales sobre el rango seleccionado:
- **Total de audios**: la suma de audios del rango.
- **Racha de días con audio**: la racha actual (tramo final de días consecutivos con al menos un audio, contando hacia atrás desde el último día del rango) y la mejor racha dentro del rango.
- **Día récord**: la fecha con más audios del rango y su cantidad; en caso de empate, se toma la fecha más antigua.
- **Comparación con el periodo anterior**: variación porcentual de la media de audios/día frente a un periodo inmediatamente anterior de igual duración, calculado con el mismo filtro de estado activo.

#### Scenario: Cambiar el rango de fechas
- **WHEN** el usuario selecciona el filtro "3 meses"
- **THEN** el gráfico, los tiles y el desglose de estados se recalculan sobre los últimos 3 meses, y la URL refleja el rango seleccionado

#### Scenario: Filtro funciona sin JavaScript
- **WHEN** JavaScript está deshabilitado en el navegador
- **THEN** seleccionar un filtro de rango sigue funcionando como navegación normal (recarga de página con el rango aplicado)

#### Scenario: Ver los datos del gráfico como tabla
- **WHEN** el usuario activa "Ver como tabla" en el gráfico de audios por día
- **THEN** se muestra una tabla accesible con fecha y valor de cada punto, con los mismos datos que el gráfico

#### Scenario: Filtrar por estado combinado con el rango
- **WHEN** el usuario tiene seleccionado el rango "3 meses" y aplica el filtro de estado "Error"
- **THEN** el gráfico de audios por día, la media de audios/día, la duración media, el total de audios, la racha, el día récord y la comparación con el periodo anterior se recalculan usando solo audios `ERROR` de esos 3 meses, mientras que el desglose de estados y el ranking de temas siguen mostrando todos los estados del mismo rango

#### Scenario: Filtro de estado inválido recae en "sin filtro"
- **WHEN** la URL contiene un valor de `status` que no corresponde a ningún estado válido
- **THEN** el sistema ignora el filtro y muestra las estadísticas sin filtrar por estado, sin error

#### Scenario: Ver el total de audios del rango
- **WHEN** el usuario consulta Estadísticas con cualquier rango seleccionado
- **THEN** se muestra un tile con el número total de audios de ese rango (y ese estado, si hay filtro aplicado)

#### Scenario: Racha activa hasta el final del rango
- **WHEN** los últimos 4 días del rango seleccionado tienen al menos un audio cada uno, y el día anterior a esos 4 no tiene ninguno
- **THEN** la racha actual mostrada es 4

#### Scenario: Sin racha activa al final del rango
- **WHEN** el último día del rango seleccionado no tiene ningún audio
- **THEN** la racha actual mostrada es 0, aunque haya habido rachas más largas antes dentro del mismo rango

#### Scenario: Día récord del rango
- **WHEN** el rango seleccionado tiene un día con más audios que cualquier otro día del mismo rango
- **THEN** se muestra ese día (fecha) junto con su cantidad de audios como "día récord"

#### Scenario: Comparación con periodo anterior disponible
- **WHEN** el periodo inmediatamente anterior (misma duración que el rango seleccionado) tiene al menos un audio
- **THEN** se muestra la variación porcentual de la media de audios/día del rango actual frente a ese periodo anterior

#### Scenario: Comparación con periodo anterior sin datos
- **WHEN** el periodo inmediatamente anterior no tiene ningún audio (p. ej. es el primer rango con actividad de la aplicación)
- **THEN** el tile de comparación se muestra indicando que no hay datos del periodo anterior, sin calcular ni mostrar un porcentaje
