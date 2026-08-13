## Context

`DailySummary` (`date` único, `summary_text`, `generated_at`, N:M con `Topic` vía `daily_summary_topic`) ya existe (`src/Entity/DailySummary.php`). Hoy solo es visible en Diario (panel del día actual) e Historial (al seleccionar un día en el calendario). No hay listado propio. El proyecto ya tiene tres vistas dashboard custom (Diario, Historial, Estadísticas) con controladores `__invoke` de una ruta, filtros por querystring (funcionan sin JS) y repositorios Doctrine con métodos concretos — este cambio sigue exactamente ese mismo patrón, sin introducir librerías nuevas (ver restricciones de arquitectura en `CLAUDE.md`: sin EasyAdmin, sin CQRS, sin capas adicionales).

## Goals / Non-Goals

**Goals:**
- Listado dedicado de `DailySummary` con sus `Topic`, accesible desde un nuevo ítem de menú "Resúmenes".
- Vista por defecto barata: últimos 5 resúmenes, sin necesidad de elegir rango.
- Filtro por rango de fechas con paginación cuando el usuario lo activa explícitamente.
- Acceso directo desde cada resumen a los audios del día correspondiente.

**Non-Goals:**
- No se modifica la generación de resúmenes (`daily-summary-generation`) ni el modelo de datos.
- No se añade búsqueda de texto libre dentro de los resúmenes (fuera de alcance, se puede proponer después si hace falta).
- No se implementa infinite scroll ni paginación vía JS/AJAX; paginación por querystring y recarga de página, igual que Estadísticas.

## Decisions

**Ruta y controlador dedicados (`/resumenes`, `SummariesController`)**
Igual que `HistorialController`/`EstadisticasController`: un controlador `__invoke`, una plantilla. No se reutiliza `HistorialController` porque su responsabilidad es el calendario mensual + log de un día, un modelo de interacción distinto al de un listado paginado de resúmenes.

**"Últimos 5" vs filtro de rango son dos modos de la misma vista, no dos rutas**
Si no hay parámros `from`/`to` en la querystring, el controlador pide los últimos 5 `DailySummary` (`ORDER BY date DESC LIMIT 5`, sin paginación visible). Si `from`/`to` están presentes y son válidos, el controlador cambia a modo "rango": todos los `DailySummary` en `[from, to]`, ordenados por fecha descendente, paginados con `page` (tamaño de página fijo, p. ej. 20). Alternativa descartada: dos rutas separadas (`/resumenes` y `/resumenes/buscar`) — añade navegación redundante sin beneficio, cuando el propio filtro ya decide el modo (mismo patrón que Estadísticas, donde `range=custom` cambia el cálculo sin cambiar de ruta).

**Paginación manual (LIMIT/OFFSET vía QueryBuilder), sin librería de paginación**
El proyecto no tiene KnpPaginatorBundle ni similar, y las otras vistas ya resuelven filtrado/agregación con QueryBuilder directo. Añadir una dependencia nueva solo para paginar una lista es sobre-ingeniería para un proyecto personal de un solo usuario. `DailySummaryRepository` gana `findPageInRange(from, to, page, pageSize): list<DailySummary>` y `countInRange(from, to): int` (este último ya existe y se reutiliza para calcular el número total de páginas).

**Enlace "ver audios del día" apunta a Historial (`/historial?date=YYYY-MM-DD`), no a Diario**
La petición original dice "ir al menú diario", pero la ruta `app_diario` (`/`) SIEMPRE muestra el día de hoy (`DiarioController::__invoke` fija `$today = DateRange::nowInMadrid()`) — no acepta un parámetro de fecha. La vista que ya sabe mostrar "todos los audios de un día concreto" es Historial (`?date=`), que es exactamente el comportamiento pedido ("que vaya... donde se encuentren todos los audios [de ese día]"). Se documenta aquí porque es una desviación literal del texto de la petición: se prioriza el comportamiento funcional pedido sobre el nombre de menú mencionado. Si el día del resumen es hoy, Historial con `?date=hoy` sigue funcionando (Historial no distingue hoy de otros días), así que no hace falta un caso especial que enlace a Diario cuando la fecha coincide con hoy.

**Reutilizar el mismo componente de "chips" de tema que Estadísticas**
Estadísticas ya renderiza `Topic.name` como lista/ranking. Para Resúmenes basta un chip simple por tema asociado a cada `DailySummary` (sin contador, a diferencia del ranking de Estadísticas), reutilizando las mismas clases CSS ya existentes para no introducir un componente nuevo.

## Risks / Trade-offs

- [Un usuario con muchos años de resúmenes puede generar rangos muy grandes y páginas pesadas] → tamaño de página fijo y razonable (20) más `LIMIT`/`OFFSET` acotan el coste por petición; no se pagina todo el histórico de una vez.
- [Rango de fechas inválido o `from > to` en la querystring] → mismo criterio que Estadísticas: si el rango es inválido, se ignora y se recae en el modo por defecto (últimos 5), sin error visible al usuario.
- [Página fuera de rango (`page` mayor que el total de páginas)] → se acota al último valor válido en servidor, evitando listados vacíos confusos.

## Open Questions

Ninguna pendiente; decisiones resueltas arriba con el patrón ya existente en Historial/Estadísticas.
