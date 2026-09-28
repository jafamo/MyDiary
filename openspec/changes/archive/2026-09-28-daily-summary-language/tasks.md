## 1. Generador

- [x] 1.1 Test: respuesta con letras no latinas (summary, topics o legend) → `WRONG_LANGUAGE`; castellano con emojis/tildes/ñ → aceptado
- [x] 1.2 Test: el prompt de sistema exige castellano
- [x] 1.3 Añadir instrucción de idioma a `OUTPUT_CONTRACT` y validación por alfabeto en `OllamaSummaryGenerator`

## 2. Servicio

- [x] 2.1 Test: japonés y luego castellano → se guarda el segundo; 3 en japonés → fallo notificado sin guardar; log por intento fallido
- [x] 2.2 Log `daily_summary.generation_attempt_failed` en `generateWithRetries()`

## 3. Cierre

- [x] 3.1 `make test`, `make cs-check`, `make phpstan` en verde
- [x] 3.2 Actualizar `Especificaciones.md` y `CHANGELOG.md` (`Corregido`)
