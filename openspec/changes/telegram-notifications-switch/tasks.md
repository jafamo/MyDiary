## 1. Implementación

- [ ] 1.1 Test en `TelegramClientTest`: con el interruptor a `false`, `sendMessage` no hace peticiones HTTP y registra `telegram.message_skipped`; `getFile`/`downloadFile` siguen funcionando
- [ ] 1.2 `TelegramClient`: argumento `bool $notificationsEnabled` y `LoggerInterface`; omitir el envío y loguear cuando está desactivado
- [ ] 1.3 `config/services.yaml`: valor por defecto `env(TELEGRAM_NOTIFICATIONS_ENABLED): 'true'` y enlace `%env(bool:TELEGRAM_NOTIFICATIONS_ENABLED)%`

## 2. Configuración y documentación

- [ ] 2.1 `.env.dev` con `false`, `.env.test` con `true` y `.env.example` documentada
- [ ] 2.2 `Especificaciones.md`: documentar la variable
- [ ] 2.3 `make cs-check`, `make phpstan` y `make test` en verde
