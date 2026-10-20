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
El sistema SHALL mostrar en anchos de ≥760px una barra de navegación lateral vertical con todos los destinos (Diario, Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas, Temas). Al pie SHALL ir un botón con el avatar y el nombre del usuario que abre un menú desplegable hacia arriba con la sección «Tema» (ver capability `ui-themes`) y «Cerrar sesión».

En anchos menores SHALL mostrar:
- Una barra superior con la marca, el botón de cambio de tema (ver capability `ui-themes`) y el icono de campana.
- Una barra de navegación inferior fija con exactamente cinco ítems: Diario, Historial, Buscar (`/busqueda`), Avisos (`/recordatorios`, con el badge de recordatorios próximos) y «Más».
- «Más» abre un panel con los destinos restantes (Resúmenes, Estadísticas, Temas) y, al pie, el nombre del usuario y «Cerrar sesión». El panel se cierra con Escape o al pulsar fuera.

En ambos anchos, el sistema SHALL mostrar el icono de campana con el badge de recordatorios próximos (ver capability `reminders`), visible en cualquier página. Todos los controles de navegación SHALL tener un área táctil de al menos 44×44 px.

#### Scenario: Navegación en escritorio
- **WHEN** la ventana tiene 760px de ancho o más
- **THEN** se muestra la barra lateral vertical con los siete destinos y no la barra inferior

#### Scenario: Navegación en móvil
- **WHEN** la ventana tiene menos de 760px de ancho
- **THEN** se muestra la barra superior con el botón de tema y la campana, y la barra inferior con cinco ítems, y no la barra lateral

#### Scenario: Destinos secundarios en «Más»
- **WHEN** en móvil el usuario pulsa «Más» en la barra inferior
- **THEN** se abre un panel con enlaces a Resúmenes, Estadísticas y Temas, y con «Cerrar sesión» al pie

#### Scenario: Ítem activo dentro de «Más»
- **WHEN** el usuario está en `/estadisticas` en móvil
- **THEN** el ítem «Más» de la barra inferior se marca como activo y, dentro del panel, «Estadísticas» lleva `aria-current="page"`

#### Scenario: Acceder a Resúmenes desde la navegación
- **WHEN** el usuario pulsa el ítem "Resúmenes" de la barra lateral o del panel «Más»
- **THEN** el sistema navega a `/resumenes` y marca ese ítem como activo (`aria-current="page"`)

#### Scenario: Acceder a Recordatorios desde la navegación
- **WHEN** el usuario pulsa el ítem "Recordatorios" de la barra lateral o "Avisos" de la barra inferior
- **THEN** el sistema navega a `/recordatorios` y marca ese ítem como activo (`aria-current="page"`)

#### Scenario: Menú de usuario en escritorio
- **WHEN** en escritorio el usuario pulsa el botón con su nombre al pie de la barra lateral
- **THEN** se abre un menú con la sección «Tema» (Moderno, Cuaderno) y «Cerrar sesión», y el botón expone `aria-expanded="true"`

### Requirement: Vista Diario con mini-dashboard
El sistema SHALL mostrar, como página principal tras el login, el día actual con este orden:

1. **Actividad**:
   - Racha de días consecutivos con audio y mejor racha histórica.
   - Gráfico de barras con el número de audios de cada uno de los últimos 14 días (hoy incluido y destacado).
   - Total de audios de la semana en curso con tendencia respecto a la anterior.
   - Tema más mencionado del mes en curso.
2. **Resumen del día**: el panel de `DailySummary` cuando existe para ese día.
3. **Log del día**: filtro por estado y log cronológico de `AudioRecording`/`Transcription` (con su estado y, si es `ERROR`, el `error_message` descriptivo).

En anchos ≥760px, el log y el resumen SHALL mostrarse en dos columnas: log a la izquierda; resumen y próximos recordatorios a la derecha.

Con el tema `cuaderno`:
- Diario SHALL mostrar además una tira con los siete días de la semana en curso.
- Cada día distinto de hoy SHALL enlazar a `/historial?date=AAAA-MM-DD` y los días con algún audio SHALL ir marcados.
- El día actual SHALL enlazar a Diario e ir destacado con `aria-current="date"`.
- El log SHALL presentarse como línea de tiempo, con el estado de cada entrada indicado por el marcador además de por texto.

Cada entrada `TRANSCRIBED` del log SHALL mostrar un pie de métricas con el tiempo de proceso, la velocidad (duración del audio dividida entre el tiempo de proceso, con un decimal y sufijo `×`) y el modelo de la transcripción. El panel de `DailySummary` SHALL mostrar un pie de métricas con tokens de entrada, salida y total, el tiempo de generación y el modelo. El log SHALL poder filtrarse por estado (`PENDING`/`TRANSCRIBED`/`ERROR`, o sin filtro); la actividad no se ve afectada por este filtro. El gráfico de 14 días SHALL tener una alternativa textual (`role="img"` con `aria-label`).

#### Scenario: Día sin resumen todavía
- **WHEN** se visita el Diario antes de que se haya generado el `DailySummary` del día (antes de las 21:00 o si falló)
- **THEN** se muestra la actividad y el log de audios del día sin el panel de resumen

#### Scenario: Orden de las secciones
- **WHEN** se visita el Diario con resumen generado
- **THEN** la sección de actividad aparece antes que el panel de resumen, y este antes que el log de audios

#### Scenario: Racha con corte
- **WHEN** el último día con audio registrado es anterior a ayer
- **THEN** la racha actual mostrada es 0

#### Scenario: Serie de 14 días con huecos
- **WHEN** en los últimos 14 días hay audios solo en 3 días
- **THEN** el gráfico muestra 14 barras, 11 de ellas con valor 0, y la última corresponde a hoy

#### Scenario: Filtrar el log por estado
- **WHEN** el usuario selecciona el filtro "Error" en Diario
- **THEN** el log muestra solo los `AudioRecording` del día en estado `ERROR`, y la actividad sigue mostrando los mismos valores que sin filtro

#### Scenario: Tira semanal en Cuaderno
- **WHEN** el usuario con tema `cuaderno` visita Diario un miércoles
- **THEN** se muestra la tira de lunes a domingo de esa semana, el miércoles lleva `aria-current="date"` y el lunes enlaza a `/historial?date=` con la fecha de ese lunes

#### Scenario: Métricas de una transcripción en el log
- **WHEN** el log muestra un audio de 102 segundos cuya transcripción tiene `processing_ms = 14000` y `model = "whisper-1"`
- **THEN** su pie de métricas muestra `Procesado en 14 s`, `7,3× tiempo real` y `whisper-1`

#### Scenario: Entradas sin transcripción no muestran métricas
- **WHEN** el log muestra un audio en estado `PENDING` o `ERROR`
- **THEN** esa entrada no muestra pie de métricas

### Requirement: Botón de generación de resumen bajo demanda en Diario
El sistema SHALL mostrar en la vista Diario un botón que pide la generación (o regeneración) del `DailySummary` del día actual sin esperar al disparo programado de las 21:00.
- **Posición y texto**: si ya hay resumen, el botón SHALL ir al pie de la tarjeta del resumen con el texto «Regenerar resumen» (Moderno) o «Regenerar» (Cuaderno). Si no lo hay, SHALL ocupar el lugar del resumen con el texto «Generar resumen».
- **Respuesta**: la petición SHALL volver de inmediato a Diario.
- **En curso**: mientras la generación de hoy esté en `QUEUED` o `RUNNING`, Diario SHALL mostrar el aviso «Generando el resumen…» con la hora de la petición y el botón desactivado, y SHALL consultar el estado en `GET /diario/resumen/estado` cada pocos segundos para recargar la vista al terminar.
- **Fallo**: si la última generación de hoy falló, SHALL mostrar «No se pudo generar el resumen» con su hora y el botón con el texto «Reintentar».
- **Sin transcripciones**: si hoy no hay transcripciones, SHALL avisar de ello en lugar de no hacer nada.

#### Scenario: Pedir el resumen antes de las 21:00
- **WHEN** el usuario pulsa "Generar resumen" en Diario y hay transcripciones de hoy
- **THEN** la respuesta redirige a Diario sin esperar a la generación, y Diario muestra «Generando el resumen…» con el botón desactivado

#### Scenario: Botón al pie del resumen
- **WHEN** Diario muestra el panel del resumen del día
- **THEN** el botón de regenerar es el último elemento de la tarjeta del resumen

#### Scenario: El resumen termina mientras la página está abierta
- **WHEN** Diario muestra una generación en curso y esta pasa a `DONE`
- **THEN** la página se recarga sola y muestra el panel del resumen del día

#### Scenario: La generación falla
- **WHEN** la última generación de hoy está en `FAILED`
- **THEN** Diario muestra «No se pudo generar el resumen» con la hora y el botón «Reintentar»

#### Scenario: Sin transcripciones hoy
- **WHEN** el usuario pulsa "Generar resumen" y hoy no hay transcripciones
- **THEN** Diario muestra un aviso indicando que no hay transcripciones de hoy y no se encola nada

#### Scenario: Estado sin sesión
- **WHEN** un visitante sin sesión pide `GET /diario/resumen/estado`
- **THEN** el sistema le redirige a `/login`

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

### Requirement: Reproductor de audio en el log de Diario e Historial
Cada entrada del log de Diario e Historial SHALL incluir un reproductor de audio, en cualquier estado del audio, que carga el fichero desde la ruta con sesión `GET /audio/{id}/fichero`.

El reproductor SHALL basarse en un elemento `<audio preload="none">` y mostrar controles propios del tema:
- Un botón play/pausa de al menos 44×44 px en Moderno (36 px dentro de la píldora de Cuaderno, con área táctil ampliada), con `aria-label` que incluye la hora del audio.
- La barra de progreso.
- El tiempo transcurrido sobre la duración.

Sin JavaScript SHALL mostrarse el `<audio controls>` nativo.

La ruta `GET /audio/{id}/fichero` SHALL servir el fichero con el mismo comportamiento que `GET /api/v1/audios/{id}/fichero` (tipo de contenido, soporte de `Range`, caché privada y `404` si falta el audio o su fichero), pero autenticada con la sesión web. El reproductor MUST NOT descargar el audio hasta que el usuario lo reproduzca.

#### Scenario: Reproductor en una entrada
- **WHEN** un usuario con sesión ve el Diario con un audio del día en cualquier estado
- **THEN** la entrada incluye un `<audio>` con `preload="none"` cuyo origen es `/audio/{id}/fichero` y un botón de reproducción con `aria-label` que nombra la hora del audio

#### Scenario: Descarga con sesión
- **WHEN** un usuario con sesión pide `/audio/{id}/fichero` de un audio guardado en disco
- **THEN** la respuesta es `200` con el contenido del fichero y su `Content-Type` de audio

#### Scenario: Descarga sin sesión
- **WHEN** un visitante sin sesión pide `/audio/{id}/fichero`
- **THEN** el sistema le redirige a `/login`

#### Scenario: Fichero ausente
- **WHEN** un usuario con sesión pide el fichero de un audio cuyo fichero no está en disco
- **THEN** la respuesta es `404`

