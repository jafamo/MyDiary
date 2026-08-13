## 1. Repositorio

- [x] 1.1 Añadir/reutilizar en `AudioRecordingRepository` la consulta del periodo anterior (`countByDateInRange` sobre `[$from - $totalDays días, $from - 1 día]`, con el mismo `$status`)

## 2. Controlador

- [x] 2.1 Calcular `$totalAudios` como stat expuesto a la vista (ya existe internamente; pasarlo al `render`)
- [x] 2.2 Implementar cálculo de racha actual y mejor racha a partir de `$series` (método privado en `EstadisticasController`)
- [x] 2.3 Implementar cálculo de día récord (fecha + cantidad, desempate por fecha más antigua) a partir de `$series`
- [x] 2.4 Calcular el rango anterior equivalente y su media de audios/día; calcular variación porcentual frente al rango actual, manejando el caso de periodo anterior sin audios (sin división por cero)
- [x] 2.5 Pasar los nuevos datos (`total_audios`, `current_streak`, `best_streak`, `record_day`, `previous_period_comparison`) a `estadisticas/index.html.twig`

## 3. Vista

- [x] 3.1 Añadir stat-tile de "Total de audios" en `templates/estadisticas/index.html.twig`
- [x] 3.2 Añadir stat-tile de "Racha" mostrando racha actual (y mejor racha si difiere)
- [x] 3.3 Añadir stat-tile de "Día récord" con fecha y cantidad, o estado vacío si el rango no tiene audios
- [x] 3.4 Añadir stat-tile de "vs. periodo anterior" con indicador ↑/↓ y porcentaje, o mensaje de "sin datos" cuando no aplica
- [x] 3.5 Ajustar `public/css/app.css` si los nuevos tiles necesitan estilos propios (p. ej. color para tendencia positiva/negativa)

## 4. Tests

- [x] 4.1 Test de racha actual con corte (últimos N días consecutivos, día previo sin audio)
- [x] 4.2 Test de racha actual en cero cuando el último día del rango no tiene audios
- [x] 4.3 Test de día récord, incluyendo caso de empate (se espera la fecha más antigua)
- [x] 4.4 Test de comparación con periodo anterior cuando hay datos previos (porcentaje correcto)
- [x] 4.5 Test de comparación con periodo anterior cuando el periodo previo no tiene audios (sin división por cero, tile en estado "sin datos")
- [x] 4.6 Test de que los cuatro indicadores respetan el filtro de estado activo, igual que el resto del dashboard

## 5. Cierre

- [x] 5.1 `make cs-fix` y `make test` en verde
- [x] 5.2 Revisar visualmente la vista Estadísticas con distintos rangos (incluye rango sin audios y primer rango con actividad de la app)
