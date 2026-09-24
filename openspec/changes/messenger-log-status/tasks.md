## 1. Processor

- [x] 1.1 Crear `src/Logger/MessengerStatusProcessor.php` con `#[AsMonologProcessor(channel: 'messenger')]` que añada `extra.messenger_status` según la tabla de la spec y deje intactos los registros no reconocidos
- [x] 1.2 Tests unitarios `tests/Logger/MessengerStatusProcessorTest.php`: cada estado (con plantilla y con mensaje interpolado), log de messenger sin estado y comprobación de que el JSON de `FlattenedContextJsonFormatter` expone `messenger_status` en la raíz

## 2. Verificación y documentación

- [x] 2.1 `make test`, `make cs-check`, `make phpstan` en verde
- [x] 2.2 Documentar el campo `messenger_status` de los logs de Messenger en `Especificaciones.md` (sección de logs/Kibana) y añadir la entrada en `CHANGELOG.md` bajo `## [Sin publicar]`
