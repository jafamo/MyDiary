## Why

El resumen diario del 2026-09-25 se generó en japonés aunque las transcripciones estaban en castellano. `qwen2.5:14b` cambia de idioma de forma esporádica: la única instrucción de idioma está en mitad del prompt de estilo (`config/prompts/daily_summary.md`), el contrato de salida del final no la repite, y el código guarda cualquier texto que devuelva el modelo.

## What Changes

- El contrato de salida fijo del prompt (`OUTPUT_CONTRACT`) exige explícitamente que `summary`, `topics` y `meaning` estén en castellano.
- `OllamaSummaryGenerator` rechaza una respuesta que contenga letras de un alfabeto no latino (japonés, chino, coreano, cirílico...) lanzando `SummaryGenerationException` con código `WRONG_LANGUAGE`.
- Esa excepción entra en los reintentos ya existentes de `DailySummaryService` (3 intentos en total): se vuelve a pedir el resumen hasta recibirlo en castellano; si los 3 fallan, se registra el error y se notifica el fallo por Telegram sin guardar nada.
- Cada intento fallido de generación deja un log `warning` (`daily_summary.generation_attempt_failed`) con su `error_code` y número de intento.

## Capabilities

### New Capabilities

### Modified Capabilities
- `daily-summary-generation`: el resumen se valida como castellano (alfabeto latino) y los intentos fallidos se registran.

## Impact

- Código: `src/Service/Ollama/OllamaSummaryGenerator.php`, `src/Service/DailySummaryService.php`.
- Tests: `tests/Service/Ollama/OllamaSummaryGeneratorTest.php`, test de `DailySummaryService`.
- Sin migraciones ni cambios de infraestructura.
