## MODIFIED Requirements

### Requirement: Navegación responsive
El sistema SHALL mostrar una barra de navegación lateral vertical (Diario, Historial, Resúmenes, Estadísticas, usuario, logout) en anchos de ≥760px, y en anchos menores SHALL mostrar una barra superior con desplegable de usuario (sesión + logout) más una barra de navegación inferior fija con los mismos cuatro destinos (Diario, Historial, Resúmenes, Estadísticas).

#### Scenario: Navegación en escritorio
- **WHEN** la ventana tiene 760px de ancho o más
- **THEN** se muestra la barra lateral vertical y no la barra inferior

#### Scenario: Navegación en móvil
- **WHEN** la ventana tiene menos de 760px de ancho
- **THEN** se muestra la barra superior con el desplegable de usuario y la barra de navegación inferior, y no la barra lateral

#### Scenario: Acceder a Resúmenes desde la navegación
- **WHEN** el usuario pulsa el ítem "Resúmenes" de la barra lateral o de la barra inferior
- **THEN** el sistema navega a `/resumenes` y marca ese ítem como activo (`aria-current="page"`)
