---
name: release
description: Crear una release con Git Flow, CHANGELOG, tag anotada y GitHub Release siguiendo la política de AGENTS.md. Usar cuando el usuario pida hacer, sacar o publicar una release o nueva versión.
---

Sigue la sección «Versiones, tags y releases» de `AGENTS.md`; si algo aquí la contradice, manda `AGENTS.md`.

1. **Versión.** Si no se ha indicado `X.Y.Z`, proponer la siguiente minor a partir de la última tag (`git describe --tags --abbrev=0`) y pedir confirmación. SemVer sin prefijo `v`.
2. **Comprobaciones previas.** Estar en `develop`, sin cambios pendientes y al día con `origin/develop` (`git fetch` + comparar). `## [Sin publicar]` del `CHANGELOG.md` no debe estar vacío. Ejecutar `make test` y parar si falla.
3. **Abrir la release:** `git flow release start X.Y.Z`.
4. **CHANGELOG (dentro de `release/X.Y.Z`).** Mover el contenido de `## [Sin publicar]` a `## [X.Y.Z] - AAAA-MM-DD` (fecha de hoy), dejando `## [Sin publicar]` vacío arriba. Mantener las secciones habituales (`Ramas integradas en develop`, `Añadido` / `Cambiado` / `Corregido`, `Migraciones` / `Despliegue` si aplican). Commit: `Prepare release X.Y.Z`.
5. **Cerrar:** `GIT_MERGE_AUTOEDIT=no git flow release finish -m "Release" X.Y.Z`. Pasar solo `"Release"`: esta edición de git-flow añade la versión al final del mensaje. Nunca tags ligeras.
6. **Publicar:** `git push origin main develop --tags`.
7. **Verificar:**
   - `git for-each-ref refs/tags/X.Y.Z --format='%(objecttype) %(subject)'` debe dar `tag Release X.Y.Z`.
   - Esperar al workflow `release.yml` y comprobar `gh release view X.Y.Z`. Si falla, crearla a mano:
     `gh release create X.Y.Z --title X.Y.Z --notes "$(awk '/^## \[X.Y.Z\]/{f=1;next} /^## \[/{f=0} f' CHANGELOG.md)"`
8. **Informar** de la tag, el enlace a la GitHub Release y un resumen de los cambios, incluyendo migraciones o pasos de despliegue si los hay.

No reescribir tags ya publicadas (`git tag -f`, force-push) salvo petición expresa.
