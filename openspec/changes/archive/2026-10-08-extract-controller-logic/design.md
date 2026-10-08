## Context

`DiarioController` ya delega en `DiarioDashboardService`, pero el resto de vistas calcula dentro del controlador: `EstadisticasController` (316 líneas, siete métodos privados), `HistorialController` y `RecordatoriosController` (la misma rejilla de calendario copiada) y `SearchController` (embedding + fusión). La fase 3 del roadmap expondrá esos mismos datos por `/api/v1/*`.

Restricciones de `AGENTS.md` que aplican: servicios de aplicación normales, sin capas ni CQRS, interfaces solo donde ya hay razón, y no introducir patrones de forma anticipada.

## Goals / Non-Goals

**Goals:**

- Controladores web reducidos a leer la petición, llamar a un servicio y renderizar.
- Servicios que devuelven datos (arrays, entidades, fechas), nunca HTML ni JSON codificado.
- Tests unitarios de la lógica extraída, sin depender del reloj.
- Mismo HTML que hoy: los tests funcionales pasan sin tocarse.

**Non-Goals:**

- Cualquier endpoint, DTO de salida o serialización de API (fases 2 y 3).
- Cambiar consultas de repositorios, plantillas o nombres de variables de Twig.
- Tocar `DiarioController`, `SummariesController`, `TopicController` o los controladores de escritura.
- Unificar las rachas de `DiarioDashboardService` (globales) con las de Estadísticas (dentro del rango): son cálculos distintos.

## Decisions

### D1 — Los servicios devuelven arrays tipados

Arrays con array-shape en PHPDoc, igual que `DiarioDashboardService`. Las claves son las que ya consumen las plantillas, de modo que el controlador pasa el array (o lo expande) sin transformarlo.

Alternativas descartadas: DTOs `readonly` (8-10 clases nuevas y cambios en las plantillas, demasiado riesgo para un refactor sin cambio funcional) y un esquema mixto (criterio poco uniforme). Si la fase 3 necesita un contrato más estricto, se introduce allí.

### D2 — La geometría del gráfico de tokens es presentación

`EstadisticasService::aiUsage()` devuelve solo `tiles` y `days`. Las coordenadas del SVG (viewBox 640×220) las calcula `App\Twig\TokensChartBuilder::build(array $days)`, y el controlador web añade el resultado como `ai_usage.chart` para que la plantilla no cambie.

Alternativas descartadas: dejarlo dentro del servicio (la API devolvería píxeles) o en el controlador (60 líneas de cálculo sin test propio).

### D3 — `MonthGrid` como helper compartido

Clase final en `src/Service/`, sin dependencias, construida a partir de «ahora», año y mes. Expone el primer y último día del mes, los límites de la rejilla, el mes anterior y el siguiente, y un método que genera las semanas llamando a un callback por día para que cada servicio añada sus marcas (`has_entries` / `has_summary` en Historial, `has_reminders` en Recordatorios). Las claves comunes de cada celda (`date`, `day`, `muted`) las pone el helper.

No es un servicio inyectado (no tiene estado ni dependencias), igual que `DateRange`.

Alternativa descartada: mover el bucle tal cual a cada servicio; mantiene la duplicación y la heredaría la API.

### D4 — Un servicio por vista, en `src/Service/`

| Servicio | Métodos públicos (orientativo) |
|---|---|
| `EstadisticasService` | `resolveRange(string $range, ?string $from, ?string $to, $today)`, `overview($from, $to, ?$status)`, `aiUsage($from, $to)` |
| `HistorialService` | `month($now, int $year, int $month)`, `entriesOn(?string $date, ?$status)` |
| `RecordatoriosService` | `month($now, int $year, int $month)`, `remindersOn(?string $date)`, `upcoming($today, int $page)`, `history($today, int $page)`, `summary($today, $monthGrid)` |
| `SearchService` | `search(string $query)` → `results` + `reminders` |

Recordatorios se parte en varios métodos porque la API tendrá `recordatorios` y `recordatorios/proximos` por separado.

Los parámetros de fecha que vienen de la query (`from`, `to`, `date`) entran al servicio como string y se validan allí: es lógica que la API repetiría. Los tamaños de página y los límites de resultados (`UPCOMING_PAGE_SIZE`, `MAX_RESULTS`, etc.) pasan a ser constantes del servicio correspondiente; son específicos de cada vista, no valores de toda la aplicación.

### D5 — «Hoy» se pasa como parámetro

El controlador sigue llamando a `DateRange::nowInMadrid()` y pasa la fecha al servicio. Los tests unitarios fijan la fecha sin necesidad de `symfony/clock` ni de una interfaz nueva.

### D6 — Logging de la búsqueda

El `warning` de fallo de embedding se mueve a `SearchService` con el mismo mensaje, el mismo `event` (`search.embedding_generation_failed`) y los mismos campos (`error_code`, `error_message`). No se añaden campos, así que no hay impacto en Kibana.

### D7 — Tests

- `MonthGrid`, `TokensChartBuilder` y los cálculos puros de `EstadisticasService` (rachas, día récord, rango): `TestCase` sin kernel, con dobles de los repositorios cuando haga falta.
- Lo que depende de consultas (paginación de recordatorios, fusión de búsqueda): mismo estilo que `DiarioDashboardServiceTest` (`KernelTestCase` contra la BD de test) o repositorios simulados, según resulte más claro en cada caso.
- Ningún test depende de valores del `.env` local.

## Risks / Trade-offs

- [Un cambio sutil en el orden o el tipo de una clave altera el HTML] → los tests funcionales de `tests/Controller/` no se tocan y deben seguir en verde; son el criterio de hecho.
- [`range` inválido: hoy la plantilla recibe el valor original aunque el rango efectivo recaiga en 30 días] → se conserva tal cual; `resolveRange` solo devuelve las fechas.
- [Recordatorios consulta el recuento del mes dos veces (rejilla y total)] → se mantiene el comportamiento; optimizarlo queda fuera de un refactor sin cambio funcional.
- [Arrays con claves sin validar por PHPStan nivel 5] → array-shapes en PHPDoc y tests unitarios que comprueban las claves.

## Migration Plan

Sin migraciones ni pasos de despliegue. Rollback: revertir el merge de la rama.
