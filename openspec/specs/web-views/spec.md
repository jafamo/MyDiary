## Purpose

Las pantallas web de la aplicación (login/logout, Diario, Historial, Estadísticas) y la navegación responsive común a todas ellas.

## Requirements

### Requirement: Login y logout
El sistema SHALL exponer un formulario de login (`/login`) autenticando contra la entidad `User` en BD, y una ruta de logout (`/logout`), sin registro ni recuperación de contraseña. Las rutas protegidas SHALL redirigir a `/login` si no hay sesión activa.

#### Scenario: Acceso sin sesión
- **WHEN** un visitante sin sesión activa solicita `/` (Diario)
- **THEN** el sistema le redirige a `/login`

#### Scenario: Login correcto
- **WHEN** el usuario envía el formulario de login con las credenciales correctas de `jfarinos`
- **THEN** el sistema crea la sesión y redirige a `/` (Diario)

#### Scenario: Logout
- **WHEN** un usuario con sesión activa visita `/logout`
- **THEN** la sesión se cierra y el sistema redirige a `/login`

### Requirement: Navegación responsive
El sistema SHALL mostrar una barra de navegación lateral vertical (Diario, Historial, Resúmenes, Recordatorios, Estadísticas, usuario, logout) en anchos de ≥760px, y en anchos menores SHALL mostrar una barra superior con desplegable de usuario (sesión + logout) más una barra de navegación inferior fija con los mismos cinco destinos (Diario, Historial, Resúmenes, Recordatorios, Estadísticas). En ambos anchos, el sistema SHALL mostrar además un icono de campana (con el badge de recordatorios próximos, ver capability `reminders`) junto al resto de la navegación global, visible en cualquier página de la aplicación.

#### Scenario: Navegación en escritorio
- **WHEN** la ventana tiene 760px de ancho o más
- **THEN** se muestra la barra lateral vertical y no la barra inferior

#### Scenario: Navegación en móvil
- **WHEN** la ventana tiene menos de 760px de ancho
- **THEN** se muestra la barra superior con el desplegable de usuario y la barra de navegación inferior, y no la barra lateral

#### Scenario: Acceder a Resúmenes desde la navegación
- **WHEN** el usuario pulsa el ítem "Resúmenes" de la barra lateral o de la barra inferior
- **THEN** el sistema navega a `/resumenes` y marca ese ítem como activo (`aria-current="page"`)

#### Scenario: Acceder a Recordatorios desde la navegación
- **WHEN** el usuario pulsa el ítem "Recordatorios" de la barra lateral o de la barra inferior
- **THEN** el sistema navega a `/recordatorios` y marca ese ítem como activo (`aria-current="page"`)

### Requirement: Vista Diario con mini-dashboard
El sistema SHALL mostrar, como página principal tras el login, el día actual con: un mini-dashboard (racha de días consecutivos con audio, total de audios de la semana en curso con tendencia respecto a la anterior, tema más mencionado del mes en curso), el log cronológico de `AudioRecording`/`Transcription` del día (con su estado y, si es `ERROR`, el `error_message` descriptivo), y el panel de `DailySummary` cuando existe para ese día. El log SHALL poder filtrarse por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro); el mini-dashboard no se ve afectado por este filtro.

#### Scenario: Día sin resumen todavía
- **WHEN** se visita el Diario antes de que se haya generado el `DailySummary` del día (antes de las 21:00 o si falló)
- **THEN** se muestra el log de audios del día sin el panel de resumen

#### Scenario: Racha con corte
- **WHEN** el último día con audio registrado es anterior a ayer
- **THEN** la racha actual mostrada es 0

#### Scenario: Filtrar el log por estado
- **WHEN** el usuario selecciona el filtro "Error" en Diario
- **THEN** el log muestra solo los `AudioRecording` del día en estado `ERROR`, y el mini-dashboard sigue mostrando los mismos valores que sin filtro

### Requirement: Botón de generación de resumen bajo demanda en Diario
El sistema SHALL mostrar en la vista Diario un botón "Generar resumen" que dispara la generación (o regeneración) del `DailySummary` del día actual sin esperar al disparo programado de las 21:00, y tras completarse SHALL volver a mostrar Diario con el resultado actualizado.

#### Scenario: Generar el resumen antes de las 21:00
- **WHEN** el usuario pulsa "Generar resumen" en Diario antes de que exista un `DailySummary` para hoy
- **THEN** el sistema genera el resumen y, al recargar la vista, se muestra el panel de resumen del día

#### Scenario: Regenerar un resumen existente
- **WHEN** el usuario pulsa "Generar resumen" en Diario y ya existe un `DailySummary` para hoy
- **THEN** el sistema regenera el resumen con las transcripciones actuales, reemplazando el contenido anterior

### Requirement: Vista Historial con calendario
El sistema SHALL mostrar un calendario navegable por mes (mes anterior/siguiente), marcando visualmente los días con `DailySummary` generado frente a los días con audios pero sin resumen, y SHALL mostrar el log de entradas del día seleccionado con las mismas acciones disponibles en el Diario (editar, eliminar, reintentar según el estado de cada entrada). El log del día seleccionado SHALL poder filtrarse por estado igual que en Diario; el calendario en sí (marcado de días) no se ve afectado por este filtro.

#### Scenario: Seleccionar un día con entradas
- **WHEN** el usuario selecciona un día del calendario que tiene `AudioRecording`
- **THEN** se muestra debajo el log de esas entradas, con las acciones de editar/eliminar/reintentar disponibles según el estado de cada una, igual que en el Diario

#### Scenario: Filtrar el log del día seleccionado por estado
- **WHEN** el usuario selecciona el filtro "Pendiente" tras elegir un día del calendario
- **THEN** el log de ese día muestra solo los `AudioRecording` en estado `PENDING`, conservando el día/mes seleccionado

### Requirement: Vista Estadísticas con filtro de rango y gráfico
El sistema SHALL permitir filtrar las estadísticas por un rango de fechas (15 días, 1 mes, 3 meses, 1 año, o un rango personalizado) y, combinable con lo anterior, por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro). Al aplicar un filtro de estado, el sistema SHALL recalcular sobre ese estado: la serie de audios por día, la media de audios/día, la duración media, el total de audios, la racha de días consecutivos con audio, el día récord y la comparación con el periodo anterior. El desglose de estados de transcripción y el ranking de temas más frecuentes SHALL seguir mostrando siempre todos los estados, sin verse afectados por el filtro de estado.

El sistema SHALL mostrar, además de los indicadores existentes, cuatro señales adicionales sobre el rango seleccionado:
- **Total de audios**: la suma de audios del rango.
- **Racha de días con audio**: la racha actual (tramo final de días consecutivos con al menos un audio, contando hacia atrás desde el último día del rango) y la mejor racha dentro del rango.
- **Día récord**: la fecha con más audios del rango y su cantidad; en caso de empate, se toma la fecha más antigua.
- **Comparación con el periodo anterior**: variación porcentual de la media de audios/día frente a un periodo inmediatamente anterior de igual duración, calculado con el mismo filtro de estado activo.

El sistema SHALL mostrar además, sin filtro de estado (los recordatorios no tienen estado de transcripción): un tile con el total de recordatorios cuya fecha cae dentro del rango seleccionado, y una segunda serie en el gráfico de audios por día con el número de recordatorios por día del mismo rango, visualmente diferenciada (color y trazo distintos de la serie de audios) y con leyenda. La tabla accesible del gráfico ("Ver como tabla") SHALL incluir esta segunda serie como columna adicional, y SHALL listar las fechas de más reciente a más antigua (orden inverso al eje cronológico del propio gráfico).

#### Scenario: Cambiar el rango de fechas
- **WHEN** el usuario selecciona el filtro "3 meses"
- **THEN** el gráfico, los tiles y el desglose de estados se recalculan sobre los últimos 3 meses, y la URL refleja el rango seleccionado

#### Scenario: Filtro funciona sin JavaScript
- **WHEN** JavaScript está deshabilitado en el navegador
- **THEN** seleccionar un filtro de rango sigue funcionando como navegación normal (recarga de página con el rango aplicado)

#### Scenario: Ver los datos del gráfico como tabla
- **WHEN** el usuario activa "Ver como tabla" en el gráfico de audios por día
- **THEN** se muestra una tabla accesible con fecha, audios y recordatorios de cada punto, ordenada de la fecha más reciente a la más antigua

#### Scenario: Filtrar por estado combinado con el rango
- **WHEN** el usuario tiene seleccionado el rango "3 meses" y aplica el filtro de estado "Error"
- **THEN** el gráfico de audios por día, la media de audios/día, la duración media, el total de audios, la racha, el día récord y la comparación con el periodo anterior se recalculan usando solo audios `ERROR` de esos 3 meses, mientras que el desglose de estados, el ranking de temas y la serie de recordatorios siguen mostrando todos los estados/recordatorios del mismo rango

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

#### Scenario: Ver el total de recordatorios del rango
- **WHEN** el usuario consulta Estadísticas con cualquier rango seleccionado
- **THEN** se muestra un tile con el número de recordatorios cuya fecha cae dentro de ese rango

#### Scenario: Ver la serie de recordatorios en el gráfico
- **WHEN** el usuario consulta el gráfico de audios por día con recordatorios en el rango seleccionado
- **THEN** se muestra una segunda línea con el número de recordatorios por día, visualmente distinguible de la línea de audios mediante la leyenda
