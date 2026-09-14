## Context

`DailySummaryService::notifySummaryGenerated` envía a Telegram únicamente `$summaryText`. No hay contexto de fecha en el mensaje.

## Goals / Non-Goals

**Goals:**
- Anteponer una cabecera con fecha (en español) e icono al texto del resumen enviado por Telegram.

**Non-Goals:**
- No se toca el mensaje de fallo (`MESSAGE_FAILED`).
- No se introduce formato Markdown/HTML de Telegram (negrita, etc.); la cabecera es texto plano, igual que el resto del mensaje.
- No se añade dependencia de `intl`/`IntlDateFormatter` para el nombre del mes.

## Decisions

- **Nombres de mes en español mediante una tabla estática (`array` `MONTHS_ES`) en `DailySummaryService`**, en vez de `IntlDateFormatter` o `setlocale`. Evita depender de que la extensión `intl` esté instalada y de la configuración de locale del sistema/contenedor, que no está garantizada. Alternativa descartada: `strftime`, deprecado en PHP 8.1+.
- **La cabecera usa la fecha del `DailySummary` (`$date`, parámetro ya existente de `notifySummaryGenerated`), no `new \DateTime()`**, para que sea coherente con generación diferida o regeneración manual de una fecha pasada.
- **Formato del texto**: `"{$header}\n\n{$summaryText}"`, concatenación simple sin plantilla adicional.

## Risks / Trade-offs

- [Riesgo] Duplicar la tabla de meses en español si en el futuro se necesita en otro sitio del código → Mitigación: si aparece una segunda necesidad, extraer a un helper compartido en ese momento (no ahora, YAGNI).
