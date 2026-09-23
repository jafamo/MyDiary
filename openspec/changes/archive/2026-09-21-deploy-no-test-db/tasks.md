## 1. Makefile

- [x] 1.1 Quitar la línea `$(MAKE) test` del target `deploy`, dejándolo como `git pull origin main` → `cache-clear` → `restart diary-php diary-messenger-worker`.

## 2. Verificación

- [x] 2.1 Revisar que el `Makefile` queda idéntico al estado previo a `deploy-run-tests` en el target `deploy`. (Confirmado con `git diff`: el único cambio es eliminar la línea `$(MAKE) test`.)
