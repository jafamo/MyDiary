## ADDED Requirements

### Requirement: GitHub Release automática al publicar una tag de versión
El sistema SHALL incluir un workflow de GitHub Actions (`.github/workflows/release.yml`) que se dispare con el `push` de tags que cumplan SemVer sin prefijo `v` (`X.Y.Z`, con X, Y y Z numéricos) y cree una GitHub Release con título `X.Y.Z`. El cuerpo de la release SHALL ser el contenido de `CHANGELOG.md` comprendido entre la cabecera `## [X.Y.Z]` y la siguiente cabecera `## [`, sin incluir ninguna de las dos cabeceras. El workflow SHALL autenticarse con el `GITHUB_TOKEN` integrado, sin secrets adicionales.

#### Scenario: Push de una tag de release
- **WHEN** se hace `git push origin --tags` con una tag nueva `0.13.0`, y `CHANGELOG.md` en esa tag contiene una sección `## [0.13.0] - AAAA-MM-DD`
- **THEN** se crea la GitHub Release `0.13.0` con título `0.13.0` y como cuerpo el contenido de esa sección

#### Scenario: Tag que no es de versión
- **WHEN** se hace push de una tag que no cumple `X.Y.Z` (p. ej. `v0.13.0` o `test-tag`)
- **THEN** el workflow de release no se ejecuta

### Requirement: Creación de la release idempotente y con validación del CHANGELOG
El workflow SHALL terminar con éxito sin modificar nada si la GitHub Release `X.Y.Z` ya existe. El workflow SHALL fallar sin crear la release si `CHANGELOG.md` no contiene la sección `## [X.Y.Z]` o si su contenido está vacío.

#### Scenario: La release ya existe
- **WHEN** se vuelve a ejecutar el workflow para una tag `X.Y.Z` cuya GitHub Release ya se creó (manualmente o en una ejecución anterior)
- **THEN** el workflow termina con éxito y la release existente no se modifica

#### Scenario: Falta la sección en el CHANGELOG
- **WHEN** se hace push de una tag `X.Y.Z` y `CHANGELOG.md` no tiene la cabecera `## [X.Y.Z]`, o la sección está vacía
- **THEN** el workflow falla con un mensaje que indica que falta la sección `X.Y.Z` en el CHANGELOG, y no se crea ninguna release
