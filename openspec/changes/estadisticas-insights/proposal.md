## Why

La vista Estadísticas solo muestra promedios (audios/día, duración media) y desgloses estáticos del rango seleccionado, sin contexto de volumen total, constancia de uso ni tendencia frente al periodo anterior. Añadir esas cuatro señales aprovecha datos que el propio `EstadisticasController` ya calcula (o casi), y da una lectura más completa del hábito de grabar notas de voz sin abrir vistas nuevas.

## What Changes

- Añadir un stat-tile de **total de audios** en el rango seleccionado (dato ya disponible como `$totalAudios` en el controlador).
- Añadir un stat-tile de **racha de días consecutivos con al menos un audio**: la racha actual (hasta el último día del rango) y la mejor racha del rango.
- Añadir un stat-tile de **día récord**: la fecha con más audios del rango y su cantidad.
- Añadir una **comparación con el periodo anterior equivalente** para audios/día (p. ej. "↑12% vs. periodo anterior"), calculada contra un rango previo de la misma duración inmediatamente anterior al actual.
- Estas cuatro señales se recalculan igual que el resto del dashboard al cambiar el rango de fechas o el filtro de estado, siguiendo las mismas reglas ya vigentes (ver requirement "Vista Estadísticas con filtro de rango y gráfico").

## Capabilities

### New Capabilities

(ninguna — todo se añade a la capability existente)

### Modified Capabilities

- `web-views`: el requirement "Vista Estadísticas con filtro de rango y gráfico" se amplía para incluir los cuatro nuevos indicadores (total de audios, racha, día récord, comparación con periodo anterior) y su comportamiento al combinarse con los filtros de rango/estado ya existentes.

## Impact

- `src/Controller/EstadisticasController.php`: nuevos cálculos (racha, día récord, rango anterior) y datos adicionales pasados a la plantilla.
- `src/Repository/AudioRecordingRepository.php`: posible query adicional para el rango anterior (reutilizando `countByDateInRange`) si no basta con lo ya expuesto.
- `templates/estadisticas/index.html.twig`: nuevos stat-tiles.
- `public/css/app.css`: estilos menores si los nuevos tiles necesitan variantes (p. ej. indicador de tendencia ↑/↓).
- Tests: `tests/Controller/EstadisticasControllerTest.php` (o repositorio equivalente) para cubrir racha, día récord y comparación con periodo anterior, incluyendo casos límite (rango sin audios, primer rango de la app sin periodo anterior con datos).
