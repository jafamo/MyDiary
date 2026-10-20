## MODIFIED Requirements

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
