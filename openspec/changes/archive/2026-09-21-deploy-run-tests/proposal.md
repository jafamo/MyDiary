## Why

`make deploy` hace `git pull` y reinicia `diary-php`/`diary-messenger-worker` sin ninguna comprobación previa. Si el código traído rompe algo, el fallo se descubre en producción. El suite de tests ya corre de forma aislada contra `telegram_notes_test` (separada de `telegram_notes`, ver `config/packages/doctrine.yaml`), así que puede usarse como puerta de calidad antes de reiniciar.

## What Changes

- El target `deploy` del `Makefile` pasa a depender de `test`: si algún test falla, `make` se detiene (por el comportamiento estándar de Make ante un target que devuelve código de error) y no llega a ejecutar `cache-clear` ni `restart`.
- Sin cambios en el propio suite de tests ni en la base de datos de producción.

## Capabilities

### New Capabilities
(ninguna)

### Modified Capabilities
- `docker-infrastructure`: añade el requisito de que `make deploy` ejecute los tests como paso previo obligatorio al reinicio de los contenedores de aplicación.

## Impact

- **Fichero afectado**: `Makefile` (target `deploy`).
- **Sin impacto en runtime de producción**: los tests usan la BD de test, no tocan `telegram_notes` ni el volumen de audio.
- **Comportamiento nuevo**: un deploy con tests en rojo ya no reinicia los contenedores; hay que arreglar el test (o el código) antes de que el deploy complete.
