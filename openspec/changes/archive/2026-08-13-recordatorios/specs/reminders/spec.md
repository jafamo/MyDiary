## ADDED Requirements

### Requirement: Crear, editar y eliminar recordatorios desde la web
El sistema SHALL permitir crear, editar y eliminar recordatorios (`Reminder`) desde la vista Recordatorios, cada uno con una fecha y un texto obligatorios. No hay recurrencia: cada recordatorio es una entrada independiente con una única fecha.

#### Scenario: Crear un recordatorio
- **WHEN** el usuario envía el formulario de creación con fecha y texto válidos
- **THEN** se crea un nuevo `Reminder` y aparece en el calendario y en el listado del día correspondiente

#### Scenario: Editar un recordatorio existente
- **WHEN** el usuario modifica el texto o la fecha de un recordatorio existente y guarda
- **THEN** el `Reminder` se actualiza y el calendario refleja la fecha nueva si cambió

#### Scenario: Eliminar un recordatorio
- **WHEN** el usuario confirma el borrado de un recordatorio
- **THEN** el `Reminder` se elimina y deja de aparecer en el calendario y en el listado del día

#### Scenario: Validación de campos obligatorios
- **WHEN** el usuario envía el formulario sin fecha o sin texto
- **THEN** el sistema rechaza el envío y no crea ni modifica ningún `Reminder`

### Requirement: Vista Recordatorios con calendario mensual
El sistema SHALL mostrar un calendario mensual navegable (mismo patrón que Historial) en `/recordatorios`, marcando los días que tienen al menos un recordatorio. Puede haber varios recordatorios el mismo día.

#### Scenario: Navegar entre meses
- **WHEN** el usuario pulsa "mes siguiente" o "mes anterior"
- **THEN** el calendario muestra ese mes y sigue marcando los días con recordatorios

#### Scenario: Seleccionar un día con recordatorios
- **WHEN** el usuario selecciona un día marcado en el calendario
- **THEN** se listan todos los recordatorios de ese día, cada uno editable y eliminable

#### Scenario: Día sin recordatorios
- **WHEN** el usuario selecciona un día sin recordatorios
- **THEN** se muestra el formulario de creación con esa fecha preseleccionada y ningún recordatorio listado

### Requirement: Listado paginado de próximos recordatorios
El sistema SHALL mostrar en `/recordatorios`, además del calendario mensual, un listado paginado de todos los recordatorios futuros (fecha igual o posterior a hoy), ordenados por fecha ascendente, sin limitarse al mes visible en el calendario. Cada elemento del listado es editable y eliminable igual que en el listado del día seleccionado.

#### Scenario: Ver recordatorios futuros de otros meses
- **WHEN** existen recordatorios con fecha en un mes distinto al mostrado en el calendario
- **THEN** esos recordatorios aparecen en el listado paginado, ordenados por fecha ascendente

#### Scenario: Paginar el listado
- **WHEN** hay más recordatorios futuros que el tamaño de página
- **THEN** el usuario puede navegar a la página siguiente/anterior para ver el resto, sin perder el orden ascendente por fecha

#### Scenario: Sin recordatorios futuros
- **WHEN** no existe ningún recordatorio con fecha igual o posterior a hoy
- **THEN** el listado paginado se muestra vacío, sin controles de paginación

### Requirement: Historial paginado de recordatorios pasados
El sistema SHALL mostrar en `/recordatorios` un listado paginado, independiente del de próximos, con todos los recordatorios cuya fecha ya pasó (anterior a hoy), ordenados por fecha descendente (más reciente primero). No hay borrado automático: un recordatorio pasa a este historial únicamente porque su fecha quedó atrás, sin ningún campo de estado nuevo. Cada elemento del historial sigue siendo editable y eliminable.

#### Scenario: Un recordatorio pasa a historial al quedar atrás su fecha
- **WHEN** la fecha de un recordatorio ya existente queda en el pasado (transcurre el día)
- **THEN** deja de aparecer en el listado de "próximos" y aparece en el historial, sin necesidad de ninguna acción manual

#### Scenario: Paginar el historial
- **WHEN** hay más recordatorios pasados que el tamaño de página
- **THEN** el usuario puede navegar a la página siguiente/anterior, manteniendo el orden descendente por fecha

#### Scenario: Sin recordatorios pasados
- **WHEN** no existe ningún recordatorio con fecha anterior a hoy
- **THEN** el historial se muestra vacío, sin controles de paginación

### Requirement: Aviso diario de recordatorios por Telegram
El sistema SHALL enviar, una vez al día a las 08:00 (Europe/Madrid) vía `Symfony Scheduler`, un mensaje de Telegram al chat autorizado con los recordatorios de la fecha actual, si existen. Si no hay recordatorios ese día, no se envía ningún mensaje.

#### Scenario: Aviso enviado cuando hay recordatorios hoy
- **WHEN** el comando `app:notify-reminders` se ejecuta y existen recordatorios con fecha igual a hoy
- **THEN** se envía un único mensaje de Telegram al chat autorizado listando esos recordatorios

#### Scenario: Sin aviso cuando no hay recordatorios hoy
- **WHEN** el comando `app:notify-reminders` se ejecuta y no existe ningún recordatorio con fecha igual a hoy
- **THEN** no se envía ningún mensaje de Telegram

### Requirement: Indicador global de recordatorios próximos
El sistema SHALL mostrar, en todas las páginas de la aplicación (no solo Recordatorios), un icono de campana con un badge numérico que cuenta los recordatorios "próximos": aquellos cuya fecha está entre hoy y hoy + 5 días (ambos inclusive). Si no hay ningún recordatorio en esa ventana, la campana no muestra badge.

Al pulsar la campana, el sistema SHALL desplegar un panel (sin recargar la página) con el recordatorio o recordatorios más cercanos — es decir, todos los que compartan la fecha del más próximo dentro de la ventana — mostrando esa fecha y el texto de cada uno, junto con un enlace "Ver todos" hacia `/recordatorios`. El panel se cierra al pulsar fuera, con Escape, o al volver a pulsar la campana.

El sistema SHALL distinguir visualmente dos niveles de urgencia según el recordatorio próximo más cercano: **urgente** (fecha de hoy o mañana) y **próximo** (entre 2 y 5 días vista).

#### Scenario: Recordatorio dentro de la ventana de 5 días
- **WHEN** existe un recordatorio con fecha dentro de los próximos 5 días (incluyendo hoy)
- **THEN** la campana muestra un badge con el número de recordatorios en esa ventana, en cualquier página de la aplicación

#### Scenario: Recordatorio fuera de la ventana
- **WHEN** el recordatorio más próximo tiene fecha a más de 5 días vista
- **THEN** ese recordatorio no cuenta en el badge de la campana

#### Scenario: Urgencia visual para hoy o mañana
- **WHEN** el recordatorio más cercano dentro de la ventana es para hoy o mañana
- **THEN** la campana se muestra con el estilo "urgente", distinto del estilo por defecto

#### Scenario: Sin recordatorios próximos
- **WHEN** no existe ningún recordatorio con fecha entre hoy y hoy + 5 días
- **THEN** la campana se muestra sin badge numérico, y al pulsarla el panel indica que no hay recordatorios próximos

#### Scenario: Desplegar el panel de la campana
- **WHEN** el usuario pulsa el icono de la campana en cualquier página
- **THEN** se despliega un panel con la fecha y el texto del recordatorio o recordatorios más próximos (los que compartan esa fecha), y un enlace "Ver todos" hacia `/recordatorios`, sin navegar fuera de la página actual

#### Scenario: Varios recordatorios el mismo día como más próximo
- **WHEN** el recordatorio más próximo tiene el mismo día que otro u otros recordatorios
- **THEN** el panel de la campana lista todos los recordatorios de esa fecha, no solo uno
