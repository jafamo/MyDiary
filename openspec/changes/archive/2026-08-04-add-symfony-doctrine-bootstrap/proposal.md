## Why

La infraestructura Docker (`diary-php`, `diary-postgres`, ...) ya está operativa, pero el contenedor `diary-php` está vacío: no hay `composer.json` ni código Symfony. Es el paso 2 del orden de construcción recomendado (`Especificaciones.md` sección 9) y bloquea todo lo demás — sin el skeleton de Symfony y Doctrine no se puede implementar el webhook de Telegram, el worker de Messenger, el scheduler ni las vistas.

## What Changes

- Instalar el skeleton de Symfony (última LTS, PHP >= 8.4) dentro de `diary-php` vía `composer create-project symfony/skeleton`.
- Configurar Doctrine ORM/Migrations contra `diary-postgres`, usando `DATABASE_URL` ya definida en `.env`/`.env.example`.
- Crear las 5 entidades Doctrine del modelo de datos (`Especificaciones.md` sección 6): `AudioRecording`, `Transcription`, `DailySummary`, `Topic`, `User`, con sus relaciones (`Transcription` 1:1 `AudioRecording` con cascade delete; `DailySummary` N:M `Topic` vía tabla pivote).
- Generar las migraciones Doctrine correspondientes y aplicarlas contra `diary-postgres`.
- Configurar Symfony Security con provider Doctrine (entidad `User` en BD). Gestión de usuarios **solo por consola**: `bin/console app:user:create <username>` y `bin/console app:user:change-password <username>` (permite fijar una contraseña nueva sin conocer la actual). Sin registro ni recuperación de contraseña vía web.
- Verificar que `diary-nginx` sirve la Symfony `public/index.php` (la página de bienvenida de Symfony responde en `http://localhost:9008`, sustituyendo el 404 actual).
- Reconstruir `diary-php`/`diary-messenger-worker` para que `messenger-worker` deje de ser un placeholder (`tail -f /dev/null`) y quede listo para `messenger:consume` (aunque aún no haya mensajes reales que consumir — eso es el siguiente change).
- Instalar `friendsofphp/php-cs-fixer` configurado con el ruleset **PSR-12** y añadir un **git hook `pre-commit`** que ejecute el fixer en modo `--dry-run` sobre los ficheros PHP staged, bloqueando el commit si no cumplen PSR-12.

## Capabilities

### New Capabilities
- `symfony-application-bootstrap`: skeleton de Symfony instalado, configuración de entorno (`.env` de Symfony, seguridad `in_memory`), y estructura base de `src/` según `Especificaciones.md` sección 5.
- `data-model`: entidades Doctrine (`AudioRecording`, `Transcription`, `DailySummary`, `Topic`), sus relaciones y migraciones.

- `code-style-enforcement`: validación automática de PSR-12 vía PHP-CS-Fixer y git hook `pre-commit` que bloquea commits con código no conforme.

### Modified Capabilities
- `docker-infrastructure`: el servicio `diary-nginx` deja de servir un `public/` inexistente (404) y pasa a servir la aplicación Symfony real; `diary-messenger-worker` deja de usar el comando placeholder `tail -f /dev/null`.

## Impact

- Ficheros nuevos: todo el skeleton de Symfony (`composer.json`, `bin/console`, `config/`, `public/index.php`, `src/Entity/*.php`, `migrations/*.php`, etc.), dentro del bind mount ya existente `./:/var/www/html`; `.php-cs-fixer.dist.php` (reglas PSR-12); script de hook en `.githooks/pre-commit` (o `.git/hooks/pre-commit` instalado vía script de setup).
- Ficheros modificados: `docker-compose.yml` (comando real de `diary-messenger-worker`), posiblemente `.env.example` (nuevas variables que exija el skeleton de Symfony, p. ej. `APP_SECRET`).
- No incluye todavía: webhook de Telegram, integración con Messenger/Redis real, transcripción, resumen diario, ni vistas Twig — eso son los siguientes pasos (3 a 5 de la sección 9).
