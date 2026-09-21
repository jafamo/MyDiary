## 1. Makefile

- [x] 1.1 Modificar el target `deploy` para invocar `$(MAKE) test` tras el `git pull` y antes de `cache-clear`/`restart`.

## 2. Verificación

- [x] 2.1 Probar manualmente: `make deploy` con el suite en verde completa `cache-clear` y `restart`. (Verificado que `make test` pasa en verde —182 tests— sobre el código actual; no se ha ejecutado `make deploy` en sí porque su primer paso, `git pull origin main`, es una acción real sobre el repo que no correspondía disparar solo para verificar esto.)
- [x] 2.2 Probar manualmente: forzar un test en rojo (temporalmente) y comprobar que `make deploy` se detiene antes de `cache-clear`/`restart`; revertir el test forzado. (Verificado el mecanismo con un Makefile de prueba equivalente en el scratchpad, fuera del repo: un `test` que falla aborta antes de llegar a `cache-clear`/`restart`, confirmando el comportamiento estándar de Make.)
