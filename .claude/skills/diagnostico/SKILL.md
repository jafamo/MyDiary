---
name: diagnostico
description: Diagnosticar un incidente de producción o de CI de MyDiary (algo que no llega, falla o va lento) reuniendo evidencias reales del servidor con los scripts de bin/ops/, hasta dar la causa raíz y, si se pide, un bugfix verificado en CI. Usar cuando el usuario pida investigar por qué algo no funciona en producción o en GitHub Actions.
---

Diagnóstico de incidentes de producción/CI. Sigue `AGENTS.md` (sección **Entorno** para hosts y rutas, **Flujo de cambios** y **Git Flow** para el arreglo).

## Reglas

- **Producción es solo lectura.** Usa únicamente `bin/ops/*` (o SSH con comandos de lectura: `docker ps/logs/inspect`, `cat`, `grep`, `ls`, `git log/status`). Nunca reinicies, despliegues, edites ficheros, borres caché ni cambies configuración en el servidor: eso se entrega como comandos para que los ejecute el usuario.
- **Nunca muestres secretos** (`.env`, tokens, contraseñas). Los scripts ya leen las credenciales en el servidor.
- **Evidencia antes que hipótesis.** No escribas código hasta tener la causa respaldada por logs, BD o CI. Si la evidencia no basta, dilo y pide lo que falte.
- **Fechas:** los logs y la BD van en UTC; el negocio (cron, días) en `Europe/Madrid` (`App\LocalTimezone`). Convierte siempre al citar horas.
- Si el usuario solo pide el diagnóstico, para en el informe (paso 4) y pregunta antes de abrir un bugfix.

## Herramientas

| Script | Para qué |
|---|---|
| `bin/ops/prod-status` | Commit desplegado, contenedores `diary-*` (arranque, reinicios) y errores de hoy |
| `bin/ops/prod-logs <servicio> [AAAA-MM-DD] [--grep P] [--tail N]` | Logs JSON de `messenger-worker`/`php` por día (UTC), `nginx`, `nginx-error`, `redis` |
| `bin/ops/prod-logs --docker <contenedor> [--since 24h]` | stdout de un contenedor `diary-*` |
| `bin/ops/prod-es '<ruta>' ['<json>']` | Elasticsearch (`filebeat-*`): `_search`, `_count`, `_cat`, `_mapping` |
| `bin/ops/prod-sql '<SELECT…>'` | Postgres en transacción de solo lectura |
| `gh run list` / `gh run view <id> --log-failed` | CI (GitHub Actions) |

Si un script falla con «No se pudo conectar», la clave no está en el agente: pide al usuario que ejecute en su terminal el `ssh-add` que imprime el script.

## Procedimiento

1. **Acotar.** Qué falla, desde cuándo, qué debería pasar y a qué hora (en Madrid y en UTC). Localiza en el código el camino afectado (comando, schedule en `src/Schedule.php`, handler, servicio) y qué logs/`event` emite cada paso.
2. **Evidencias.** Empieza por `prod-status`. Luego, en el rango de tiempo del fallo: logs del worker/php (los ficheros tienen más historial que Elasticsearch), errores y `messenger_status` en `prod-es`, estado en BD con `prod-sql`, y `gh run` si es CI. Cita las líneas relevantes.
3. **Hipótesis** ordenadas por probabilidad, cada una con la evidencia a favor/en contra. Descarta con datos, no por intuición.
4. **Informe de causa raíz** (siempre):
   - Causa, con la evidencia que la prueba (log, fila, commit).
   - Impacto: desde cuándo y qué días/peticiones afectó.
   - Comandos exactos para el host si hace falta actuar en producción (el usuario los ejecuta).
   - Cómo verificar en producción que queda resuelto (qué log/fila/mensaje esperar y cuándo).
   - Huecos de observabilidad detectados (p. ej. un camino que termina sin log).
5. **Arreglo en código** (solo si la causa está en el código y el usuario lo quiere):
   - `git flow bugfix start <nombre>` desde `develop`.
   - Test que falle y reproduzca el fallo; después el arreglo; `make test`, `make cs-check`, `make phpstan` en verde.
   - Entrada en `## [Sin publicar]` del `CHANGELOG.md` (`Corregido`).
   - Push de la rama y `gh run watch` hasta verde. Si CI falla y en local pasa, busca dependencias del `.env` local.
   - No hagas finish ni release sin que el usuario lo pida.
