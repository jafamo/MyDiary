## 1. Helpers compartidos

- [x] 1.1 `src/Service/MonthGrid.php`: primer/último día del mes, límites de la rejilla, mes anterior y siguiente, y generación de semanas con callback por día (claves comunes `date`, `day`, `muted`)
- [x] 1.2 `tests/Service/MonthGridTest.php`: mes que empieza en lunes, mes con relleno al principio y al final, cambio de año, días de relleno marcados
- [x] 1.3 `src/Twig/TokensChartBuilder.php`: geometría del gráfico de tokens (`build(array $days)`), movida desde `EstadisticasController::tokensChart()` / `niceMax()`
- [x] 1.4 `tests/Twig/TokensChartBuilderTest.php`: sin datos (máximo 1000), máximo «redondo», posiciones de barras y etiquetas

## 2. Estadísticas

- [x] 2.1 `src/Service/EstadisticasService.php`: `resolveRange()`, `overview()` (series, totales, medias, rachas, día récord, comparativa, estados, temas) y `aiUsage()` (tiles + días, sin gráfico)
- [x] 2.2 `EstadisticasController`: leer petición, llamar al servicio y a `TokensChartBuilder`, codificar `series_json` / `reminders_series_json` y renderizar con las mismas variables
- [x] 2.3 `tests/Service/EstadisticasServiceTest.php`: presets, rango personalizado válido e inválido, rachas, día récord con empate, comparativa sin periodo anterior, sumas de consumo IA con valores `null`

## 3. Historial

- [x] 3.1 `src/Service/HistorialService.php`: rejilla del mes con `has_entries` / `has_summary` / `count` sobre `MonthGrid`, y entradas del día seleccionado (fecha inválida → sin selección)
- [x] 3.2 `HistorialController`: delegar en el servicio manteniendo las variables de la plantilla
- [x] 3.3 `tests/Service/HistorialServiceTest.php`: marcas de la rejilla, día seleccionado con filtro de estado, fecha inválida

## 4. Recordatorios

- [x] 4.1 `src/Service/RecordatoriosService.php`: rejilla del mes con `has_reminders` / `count`, recordatorios del día, páginas de próximos e históricos (constantes de tamaño de página en el servicio), total del mes, próximo recordatorio y días que faltan
- [x] 4.2 `RecordatoriosController::index()`: delegar en el servicio manteniendo las variables de la plantilla; `create` / `edit` / `delete` no cambian
- [x] 4.3 `tests/Service/RecordatoriosServiceTest.php`: marcas de la rejilla, paginación con página fuera de rango, próximo recordatorio y días que faltan, sin recordatorios

## 5. Búsqueda

- [x] 5.1 `src/Service/SearchService.php`: embedding de la consulta, fusión por distancia con límite, recordatorios por texto y aviso `search.embedding_generation_failed` con los mismos campos
- [x] 5.2 `SearchController`: consulta vacía sin llamar al servicio; en otro caso, delegar y renderizar
- [x] 5.3 `tests/Service/SearchServiceTest.php`: fusión ordenada y limitada, fallo del embedding con recordatorios, aviso de log

## 6. Documentación y cierre

- [x] 6.1 `ROADMAP.md`: marcar la fase 1 con el nombre del change
- [x] 6.2 Entrada en `CHANGELOG.md` bajo `## [Sin publicar]` (rama integrada + Cambiado)
- [x] 6.3 Comprobar que `git diff develop -- tests/Controller templates` está vacío
- [x] 6.4 `make cs-check`, `make phpstan` y `make test` en verde
