# AGENTS.md

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

## Versiones, tags y releases (regla fija)

Aplicar siempre que se haga una release o un hotfix, sin que el usuario tenga que pedirlo:

- **Versionado:** SemVer **sin prefijo `v`** (`0.13.0`, no `v0.13.0`). `release` sube minor (o major); `hotfix` sube patch.
- **CHANGELOG antes del finish:** dentro de la rama `release/X.Y.Z` o `hotfix/X.Y.Z`, mover el contenido de `## [Sin publicar]` a `## [X.Y.Z] - AAAA-MM-DD` (dejando `## [Sin publicar]` vacío arriba), con las secciones habituales: `Ramas integradas en develop`, `Añadido` / `Cambiado` / `Corregido`, y `Migraciones` / `Despliegue` si aplican. Commit `Prepare release X.Y.Z`.
- **Tags siempre anotadas, mensaje fijo `Release X.Y.Z`:**
  - `git flow release finish -m "Release X.Y.Z" X.Y.Z`
  - `git flow hotfix finish -m "Release X.Y.Z" X.Y.Z`
  - Nunca tags ligeras (sin `-m` git flow puede abrir editor o dejarla sin mensaje).
- **Publicar:** `git push origin main develop --tags` y después crear la GitHub Release con el cuerpo de la sección del CHANGELOG:
  `gh release create X.Y.Z --title X.Y.Z --notes "$(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md)"`
- **Verificar:** `git for-each-ref refs/tags/X.Y.Z --format='%(objecttype) %(subject)'` debe dar `tag Release X.Y.Z`, y `gh release view X.Y.Z` debe existir.
- **No reescribir tags ya publicadas** (sin `git tag -f` ni force-push de tags) salvo que el usuario lo pida expresamente.
- Histórico conocido: `0.7.1` es una tag ligera y solo existe GitHub Release desde `0.12.1`; se deja así.
