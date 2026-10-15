## 1. API

- [x] 1.1 Repositorios: día con audio y día con resumen más cercanos antes y después de una fecha
- [x] 1.2 `HistorialService::adjacentDays` y `previous_date`/`next_date` en `GET /api/v1/historial/{fecha}`, con OpenAPI
- [x] 1.3 Tests funcionales: audio y resumen a los lados, sin actividad, sin contar el propio día, saltos de meses e independencia del filtro

## 2. Documentación y verificación

- [x] 2.1 `make openapi`, `Especificaciones.md` y `CHANGELOG.md`
- [x] 2.2 `make cs-check`, `make phpstan` y `make test` en verde
