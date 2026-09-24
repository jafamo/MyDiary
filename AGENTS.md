# AGENTS.md

Instrucciones para trabajar en este repositorio (Telegram Voice Notes — PHP/Symfony).

## Fuente de verdad

`Especificaciones.md` es el documento de referencia del dominio, flujos, modelo de datos e infraestructura. Léelo antes de implementar cualquier funcionalidad nueva y mantenlo actualizado si una decisión cambia durante el desarrollo.

## Flujo de cambios (OBLIGATORIO)

Todo cambio funcional, por pequeño que sea, sigue:

1. Crear y validar una propuesta OpenSpec.
2. Crear una rama feature/bugfix con Git Flow.
3. Implementar con tests.
4. Añadir entrada en el CHANGELOG (`## [Sin publicar]`).
5. Sincronizar specs y archivar el cambio.
6. Hacer finish de la rama hacia `develop`.

Nunca implementar directamente sobre `develop`.

**Opciones de diseño antes de implementar.** Si el cambio implica decisiones de diseño (dónde vive una constante o un servicio, nombres de campos de log, catálogos de datos, configuración frente a código), antes de tocar código proponer 2-3 alternativas con sus pros y contras, indicando dónde viviría cada pieza y cómo encaja con Kibana y con estas convenciones, y esperar a que el usuario elija. En la parte visual, lo equivalente es un mockup previo.

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
- Tests: `make test` ejecuta el suite de PHPUnit dentro de `diary-php` contra la base de datos de test (`telegram_notes_test`, separada de `telegram_notes`). Estilo de código: `make cs-check` (verificar) / `make cs-fix` (corregir), PSR-12, aplicado también en el hook `pre-commit` (`.githooks/pre-commit`, activar con `git config core.hooksPath .githooks`). Análisis estático: `make phpstan` (nivel 5, errores previos en `phpstan-baseline.neon`; regenerarlo solo de forma consciente).
- CI (`.github/workflows/ci.yml`, en cada push a `main`/`develop` y en cada PR): `lint` (php-cs-fixer + PHPStan) → `tests` (PHPUnit con cobertura contra Postgres efímero) → `sonar` (SonarQube con esa cobertura).

## Entorno

- El stack Docker (SonarQube, Ollama, Open WebUI, nginx, app) corre en el **servidor de producción**, no en esta máquina. Dar comandos para ejecutar en el host en lugar de buscar contenedores en local.
- **Servidor de producción:** `zeus`, alias SSH `diary-prod` (definido en `~/.ssh/config` del usuario; la IP no se versiona). Proyecto en `/projects/dockers/MyDiary`. Contenedores de la app: `diary-php`, `diary-messenger-worker` (async + scheduler), `diary-nginx`, `diary-postgres`, `diary-redis`, `diary-filebeat`. En el mismo host, fuera de este compose: `elasticsearch` (`localhost:9200`, logs en `filebeat-*`), `kibana` (`:5601`), `ollama`, `open-webui`, `sonarqube`.
- **Logs en el servidor:** `logs/<servicio>/app-prod-AAAA-MM-DD.log` (JSON, fecha UTC) para `messenger-worker` y `php`; `logs/nginx/{access,error}.log`. Los ficheros guardan más historial que Elasticsearch.
- **Acceso de diagnóstico:** solo lectura, con los scripts de `bin/ops/` (`prod-status`, `prod-logs`, `prod-es`, `prod-sql`) y el skill `/diagnostico`. Requiere la clave en el ssh-agent de systemd (`ssh-add -t 8h ~/.ssh/id_ed25519` en una terminal del usuario). Cualquier cambio en producción se entrega como comandos para que los ejecute el usuario.
- El servidor de producción no tiene base de datos de test: nunca añadir `make test` ni pasos de tests al despliegue.
- Los tests no deben depender de valores del `.env` local (p. ej. `TELEGRAM_AUTHORIZED_CHAT_ID`, rutas de almacenamiento de audio). Fijarlos explícitamente en la configuración de test para que pasen en GitHub CI.

## Convenciones de código

### Logging (Kibana)

Los campos de contexto de log deben ser planos y con prefijo (p. ej. `messenger_status`, no `status`) para no chocar con los tipos de campo de nginx que ya existen en Kibana.

### Constantes

Los valores de toda la aplicación (zona horaria, etc.) van en un sitio compartido genérico, nunca reutilizados de constantes específicas de una clase.

## Control de versiones: Git Flow (regla fija)

Este repositorio usa **Git Flow** (`git flow init` ya ejecutado, prefijos por defecto). Nunca commitear directo a `main` ni a `develop`:

- `main` — solo releases. `develop` — rama de integración, base de todo trabajo nuevo.
- Funcionalidad nueva → `git flow feature start <nombre>` (rama `feature/<nombre>` desde `develop`), y `git flow feature finish <nombre>` al terminar (mergea a `develop`).
- Corrección de bug sobre `develop` → `git flow bugfix start <nombre>`.
- Preparar una release → `git flow release start <version>`, `git flow release finish <version>` (mergea a `main` y `develop`, y taggea).
- Fix urgente sobre producción → `git flow hotfix start <nombre>` (rama desde `main`).
- **Push:** tras terminar una feature/bugfix, hacer push **solo de `develop`** salvo que el usuario pida expresamente una release. Push de `main` y de tags solo durante una release o un hotfix (`git flow release finish` / `git flow hotfix finish`), según la sección siguiente.

## Versiones, tags y releases (regla fija)

Aplicar siempre que se haga una release o un hotfix, sin que el usuario tenga que pedirlo:

- **Versionado:** SemVer **sin prefijo `v`** (`0.13.0`, no `v0.13.0`). `release` sube minor (o major); `hotfix` sube patch.
- **CHANGELOG antes del finish:** dentro de la rama `release/X.Y.Z` o `hotfix/X.Y.Z`, mover el contenido de `## [Sin publicar]` a `## [X.Y.Z] - AAAA-MM-DD` (dejando `## [Sin publicar]` vacío arriba), con las secciones habituales: `Ramas integradas en develop`, `Añadido` / `Cambiado` / `Corregido`, y `Migraciones` / `Despliegue` si aplican. Commit `Prepare release X.Y.Z`.
- **Tags siempre anotadas, mensaje fijo `Release X.Y.Z`:**
  - `GIT_MERGE_AUTOEDIT=no git flow release finish -m "Release" X.Y.Z`
  - `GIT_MERGE_AUTOEDIT=no git flow hotfix finish -m "Release" X.Y.Z`
  - Ojo: el git-flow instalado (CJS Edition 2.2.1) **añade la versión al final** del mensaje de `-m`; por eso se pasa solo `"Release"`. Con `-m "Release X.Y.Z"` sale `Release X.Y.Z X.Y.Z`.
  - Nunca tags ligeras (sin `-m` git flow puede abrir editor o dejarla sin mensaje).
- **Publicar:** `git push origin main develop --tags`. Al llegar la tag, `.github/workflows/release.yml` crea la GitHub Release con el cuerpo de la sección `## [X.Y.Z]` del CHANGELOG (no hace nada si ya existe; falla si falta la sección). Si el workflow falla, crearla a mano:
  `gh release create X.Y.Z --title X.Y.Z --notes "$(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md)"`
- **Verificar:** `git for-each-ref refs/tags/X.Y.Z --format='%(objecttype) %(subject)'` debe dar `tag Release X.Y.Z`, y `gh release view X.Y.Z` debe existir.
- **No reescribir tags ya publicadas** (sin `git tag -f` ni force-push de tags) salvo que el usuario lo pida expresamente.
- Histórico conocido: `0.7.1` es una tag ligera, varias tags tienen el mensaje `Release X.Y.Z X.Y.Z` (p. ej. `0.12.1`, `0.12.2`) y solo existe GitHub Release desde `0.12.1`; se deja así.
