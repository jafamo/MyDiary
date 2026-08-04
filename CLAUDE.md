# CLAUDE.md

Instrucciones para trabajar en este repositorio (Telegram Voice Notes — PHP/Symfony).

## Fuente de verdad

`Especificaciones.md` es el documento de referencia del dominio, flujos, modelo de datos e infraestructura. Léelo antes de implementar cualquier funcionalidad nueva y mantenlo actualizado si una decisión cambia durante el desarrollo.

## Restricciones de arquitectura (no reintroducir)

Estas decisiones se tomaron explícitamente para evitar sobre-ingeniería en un proyecto personal de un solo usuario. No proponer ni introducir lo contrario sin que el usuario lo pida:

- **Sin EasyAdmin.** Las vistas (Diario, Historial, Estadísticas) son dashboards custom con controladores Symfony + `FormType`, no CRUDs genéricos.
- **Sin hexagonal estricta / sin capas Domain-Application-Infrastructure separadas.** Las entidades Doctrine SON el modelo de dominio.
- **Sin CQRS ni bus de comandos/queries general.** Servicios de aplicación normales con métodos claros.
- **Interfaces (puertos) solo puntuales**, donde ya existe razón real: `TranscriberInterface`, `SummaryGeneratorInterface`. No generalizar a otras partes del código sin justificación equivalente.
- **Symfony Messenger solo para la cadena Telegram → transcripción**, no como bus general.
- **Gestión de usuarios solo por consola.** Entidad `User` en BD (Symfony Security), pero sin registro ni recuperación de contraseña vía web: los usuarios se crean y las contraseñas se cambian con comandos `bin/console app:user:*` (acceso al servidor = ya autenticado como admin). Sin flujo de "olvidé mi contraseña" por email/token.
- Regla general: introducir un patrón solo cuando el problema que resuelve ya existe, no de forma anticipada.

## Flujo de trabajo

- Antes de implementar una funcionalidad, si el proyecto tiene OpenSpec inicializado (carpeta `openspec/`), pasar por un change proposal (`openspec change`) en lugar de tocar código directamente.
- Tests: `make test` ejecuta el suite de PHPUnit dentro de `diary-php` contra la base de datos de test (`telegram_notes_test`, separada de `telegram_notes`). Estilo de código: `make cs-check` (verificar) / `make cs-fix` (corregir), PSR-12, aplicado también en el hook `pre-commit` (`.githooks/pre-commit`, activar con `git config core.hooksPath .githooks`).

## Control de versiones: Git Flow (regla fija)

Este repositorio usa **Git Flow** (`git flow init` ya ejecutado, prefijos por defecto). Nunca commitear directo a `main` ni a `develop`:

- `main` — solo releases. `develop` — rama de integración, base de todo trabajo nuevo.
- Funcionalidad nueva → `git flow feature start <nombre>` (rama `feature/<nombre>` desde `develop`), y `git flow feature finish <nombre>` al terminar (mergea a `develop`).
- Corrección de bug sobre `develop` → `git flow bugfix start <nombre>`.
- Preparar una release → `git flow release start <version>`, `git flow release finish <version>` (mergea a `main` y `develop`, y taggea).
- Fix urgente sobre producción → `git flow hotfix start <nombre>` (rama desde `main`).
- Tras cada `finish`, hacer `git push origin main develop --tags` (o las ramas correspondientes) para reflejar el merge en GitHub.
