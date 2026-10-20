## ADDED Requirements

### Requirement: Catálogo de temas de la interfaz web
El sistema SHALL ofrecer exactamente dos temas visuales para la interfaz web, definidos en un único sitio compartido (enum `UiTheme`):
- `moderno` (por defecto): tarjetas, tipografía Plus Jakarta Sans con JetBrains Mono para cifras y horas, paleta cian.
- `cuaderno`: línea de tiempo, tipografía Newsreader para títulos, resumen y transcripciones con Roboto para la interfaz, paleta papel/marrón tinta con violeta.

Cada tema SHALL definir sus tokens de color, tipografía, radios y sombras. El texto de cuerpo y las etiquetas SHALL tener un contraste mínimo de 4.5:1 sobre su fondo. Los dos temas son claros: la interfaz MUST NOT cambiar de colores según la preferencia `prefers-color-scheme` del sistema.

#### Scenario: Sistema en modo oscuro
- **WHEN** el usuario con tema `moderno` visita Diario desde un dispositivo con el modo oscuro del sistema activado
- **THEN** la página se muestra con los colores claros del tema Moderno

#### Scenario: Tema por defecto
- **WHEN** un usuario que nunca ha elegido tema inicia sesión y visita Diario
- **THEN** el elemento `<html>` de la respuesta lleva `data-theme="moderno"`

#### Scenario: Valor de tema desconocido
- **WHEN** se intenta guardar como preferencia un tema que no está en el catálogo (p. ej. `oscuro`)
- **THEN** el sistema rechaza el valor y la preferencia guardada no cambia

### Requirement: Preferencia de tema persistida por usuario
El sistema SHALL guardar el tema elegido en el usuario autenticado y SHALL renderizar todas las páginas con sesión con `data-theme="<tema>"` en `<html>` desde la respuesta del servidor, sin depender de JavaScript para aplicarlo. La página de login (sin sesión) SHALL usar el tema por defecto.

#### Scenario: El tema se mantiene entre visitas
- **WHEN** el usuario elige `cuaderno`, cierra sesión y vuelve a iniciarla desde otro navegador
- **THEN** Diario se renderiza con `data-theme="cuaderno"`

#### Scenario: El tema se aplica en todas las vistas
- **WHEN** el usuario con tema `cuaderno` visita Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas o Temas
- **THEN** cada respuesta lleva `data-theme="cuaderno"` en `<html>`

### Requirement: Selector de tema en el menú de usuario
En escritorio (≥760px), el sistema SHALL mostrar dentro del menú desplegable del nombre de usuario (pie de la barra lateral) una sección «Tema» con una opción por tema. Cada opción SHALL mostrar solo el nombre del tema («Moderno», «Cuaderno»), sin prefijo, con su icono (cuadrícula para Moderno, libreta abierta para Cuaderno), y SHALL marcar la opción activa con un check y `aria-checked="true"`.

En móvil (<760px), la barra superior SHALL mostrar un botón con el icono y el nombre del otro tema (p. ej. «Cuaderno» si el activo es Moderno) y un `aria-label` «Cambiar al tema …».

Elegir una opción o pulsar el botón SHALL enviar `POST /preferencias/tema` con token CSRF. Sin JavaScript, la respuesta SHALL redirigir a la página de origen si es una ruta interna (o a Diario si no lo es). Con JavaScript, el tema SHALL aplicarse sin recargar la página.

#### Scenario: Cambiar de tema desde el menú
- **WHEN** el usuario abre el menú de su nombre y elige «Cuaderno» estando en `/estadisticas`
- **THEN** la preferencia pasa a `cuaderno` y el usuario sigue en `/estadisticas` con el tema Cuaderno aplicado

#### Scenario: Opción activa marcada
- **WHEN** el usuario con tema `moderno` abre el menú de usuario
- **THEN** la opción «Moderno» tiene `aria-checked="true"` y check visible, y «Cuaderno» tiene `aria-checked="false"`

#### Scenario: Botón de tema en móvil
- **WHEN** en móvil el usuario con tema `moderno` pulsa el botón «Cuaderno» de la barra superior
- **THEN** la preferencia pasa a `cuaderno`, se aplica el tema y el botón pasa a mostrar «Moderno»

#### Scenario: Petición sin token CSRF válido
- **WHEN** se envía `POST /preferencias/tema` sin token CSRF válido
- **THEN** el sistema no cambia la preferencia y responde con error

#### Scenario: Cambio de tema sin sesión
- **WHEN** un visitante sin sesión envía `POST /preferencias/tema`
- **THEN** el sistema le redirige a `/login`

#### Scenario: Redirección a origen externo
- **WHEN** la petición de cambio de tema llega con un `Referer` de otro dominio
- **THEN** el sistema redirige a Diario y no al dominio externo

### Requirement: Registro del cambio de tema
Cada cambio de tema SHALL registrar un log `info` en el canal `app` con `event = ui.theme_changed` y el campo plano `ui_theme` con el tema elegido (misma convención que el resto de logs de la aplicación).

#### Scenario: Log del cambio
- **WHEN** el usuario cambia su tema a `cuaderno`
- **THEN** se escribe un log con `event = "ui.theme_changed"` y `ui_theme = "cuaderno"`
