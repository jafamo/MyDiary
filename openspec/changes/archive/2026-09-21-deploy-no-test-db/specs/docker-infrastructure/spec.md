## REMOVED Requirements

### Requirement: `make deploy` ejecuta los tests antes de reiniciar
**Reason**: `make test` fuerza `APP_ENV=test`, que requiere la base de datos `telegram_notes_test`; esa BD no existe en el servidor de producción y no se creó allí, así que `make deploy` fallaba siempre. Se prefiere que `deploy` no dependa de tener Postgres de test en el servidor.
**Migration**: Ejecutar `make test` manualmente en el entorno de desarrollo antes de mergear/pushear a `main`, como ya indica el flujo estándar del repo (`CLAUDE.md`).
