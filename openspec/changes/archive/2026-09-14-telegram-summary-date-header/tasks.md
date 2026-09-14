## 1. Implementación

- [x] 1.1 Añadir tabla estática de meses en español y método/constante de cabecera en `DailySummaryService`
- [x] 1.2 Modificar `notifySummaryGenerated` para anteponer la cabecera (`📔 Resumen día: <día> de <mes> de <año>`) al texto del resumen, separada por una línea en blanco
- [x] 1.3 Actualizar `tests/Service/DailySummaryServiceTest.php` para reflejar el nuevo texto exacto enviado a Telegram en los tests que lo comprueban

## 2. Verificación

- [x] 2.1 Ejecutar `make test` (filtrado a `DailySummaryServiceTest`) y confirmar que pasa
- [x] 2.2 Ejecutar `make cs-fix` / `make cs-check` y confirmar que no hay incidencias de estilo
