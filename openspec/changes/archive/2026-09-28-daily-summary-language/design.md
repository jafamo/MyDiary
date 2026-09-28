## Context

`DailySummaryService::generateWithRetries()` ya reintenta la llamada al generador ante `SummaryGenerationException` (3 intentos, 5 s entre ellos) y, si todos fallan, registra `daily_summary.generation_failed` y avisa por Telegram. `OllamaSummaryGenerator` construye el prompt de sistema con el fichero de estilo + `OUTPUT_CONTRACT` y valida solo la forma del JSON.

## Goals / Non-Goals

**Goals:** no guardar nunca un resumen en otro alfabeto; reintentar hasta 3 veces y fallar con error si no llega en castellano; reducir la probabilidad de que ocurra.

**Non-Goals:** detectar otros idiomas con alfabeto latino (inglés, portugués...); añadir una librería de detección de idioma.

## Decisions

- **Reutilizar los reintentos del servicio** en vez de un bucle propio en el generador: un bucle interno se anidaría con el del servicio (hasta 9 llamadas) y duplicaría la política de reintentos. `WRONG_LANGUAGE` es un fallo de generación más.
- **Detección por alfabeto**: la respuesta es inválida si `summary`, algún `topic` o algún `meaning` de la leyenda contiene una letra que no es del alfabeto latino (`/(?!\p{Latin})\p{L}/u`). Los emojis, números y signos no son letras, y `ñ`/`á` son latinas. Cubre el fallo real (Qwen cambia a japonés/chino) sin dependencias.
- **Instrucción de idioma en `OUTPUT_CONTRACT`** (código) y no solo en el fichero de estilo: va al final del prompt, que el modelo respeta más, y editar el estilo no puede quitarla.
- **Log por intento fallido** (`warning`, `event: daily_summary.generation_attempt_failed`, `error_code`, `error_message`, `attempt_number`, `max_attempts`): hoy los intentos intermedios no dejan rastro; campos planos sin chocar con nginx.

## Risks / Trade-offs

- Un resumen en inglés u otro idioma latino no se detecta → mitigado por la instrucción explícita.
- Una transcripción que cite una palabra en otro alfabeto (p. ej. un nombre en japonés) haría fallar la validación si el modelo la copia → poco probable en este diario; en ese caso se vería el `WRONG_LANGUAGE` en los logs.
