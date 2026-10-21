## MODIFIED Requirements

### Requirement: Navegación responsive
El sistema SHALL mostrar en anchos de ≥760px una barra de navegación lateral vertical con todos los destinos (Diario, Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas, Categorías). Al pie SHALL ir un botón con el avatar y el nombre del usuario que abre un menú desplegable hacia arriba con la sección «Tema» (ver capability `ui-themes`) y «Cerrar sesión».

En anchos menores SHALL mostrar:
- Una barra superior con la marca, el botón de cambio de tema (ver capability `ui-themes`) y el icono de campana.
- Una barra de navegación inferior fija con exactamente cinco ítems: Diario, Historial, Buscar (`/busqueda`), Avisos (`/recordatorios`, con el badge de recordatorios próximos) y «Más».
- «Más» abre un panel con los destinos restantes (Resúmenes, Estadísticas, Categorías) y, al pie, el nombre del usuario y «Cerrar sesión». El panel se cierra con Escape o al pulsar fuera.

En ambos anchos, el sistema SHALL mostrar el icono de campana con el badge de recordatorios próximos (ver capability `reminders`), visible en cualquier página. Todos los controles de navegación SHALL tener un área táctil de al menos 44×44 px.

#### Scenario: Navegación en escritorio
- **WHEN** la ventana tiene 760px de ancho o más
- **THEN** se muestra la barra lateral vertical con los siete destinos y no la barra inferior

#### Scenario: Navegación en móvil
- **WHEN** la ventana tiene menos de 760px de ancho
- **THEN** se muestra la barra superior con el botón de tema y la campana, y la barra inferior con cinco ítems, y no la barra lateral

#### Scenario: Destinos secundarios en «Más»
- **WHEN** en móvil el usuario pulsa «Más» en la barra inferior
- **THEN** se abre un panel con enlaces a Resúmenes, Estadísticas y Categorías, y con «Cerrar sesión» al pie

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

#### Scenario: Destino Categorías
- **WHEN** el usuario pulsa el ítem «Categorías» de la barra lateral o del panel «Más»
- **THEN** el sistema navega a `/topics` y marca ese ítem como activo (`aria-current="page"`)
