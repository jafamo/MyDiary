---
name: desarrollo
description: Llevar un cambio funcional de MyDiary de principio a fin según el «Flujo de cambios (OBLIGATORIO)» de AGENTS.md (propuesta OpenSpec, rama Git Flow, implementación con tests, CHANGELOG, archivado, revisión con el usuario y finish hacia develop). Usar cuando el usuario pida implementar una funcionalidad, corregir un bug o hacer cualquier cambio en el código de la aplicación, por pequeño que sea.
---

Sigue «Flujo de cambios (OBLIGATORIO)» y «Control de versiones: Git Flow» de `AGENTS.md`; si algo aquí lo contradice, manda `AGENTS.md`. Los pasos 1-7 son los suyos, en el mismo orden. No empezar un paso sin haber cerrado el anterior.

**Antes de empezar**
- Estar en `develop`, al día con `origin/develop` (`git fetch` + comparar). Si hay cambios sin commitear ajenos al cambio, avisar y no mezclarlos con él.
- Leer las partes de `Especificaciones.md` y `openspec/specs/` que toca el cambio.
- Si hay decisiones de diseño (dónde vive una constante o un servicio, nombres de campos de log, catálogos, configuración frente a código), proponer 2-3 alternativas con pros y contras y **esperar a que el usuario elija**. En la parte visual, mockup antes. Respetar las «Restricciones de arquitectura».
- Elegir un nombre kebab-case que servirá para el cambio OpenSpec y para la rama.

1. **Crear y validar la propuesta OpenSpec.** Skill `openspec-propose` con ese `<nombre>` y `openspec validate <nombre>`. Enseñar el resumen al usuario. No se commitea todavía: el hook de `.claude/settings.json` bloquea commits en `develop`.
2. **Crear la rama con Git Flow.** `git flow feature start <nombre>` para funcionalidad, `git flow bugfix start <nombre>` para un bug sobre `develop` (los ficheros de la propuesta pasan a la rama). Commitear la propuesta en la rama. Un fallo urgente en producción va por `hotfix`, fuera de este skill.
3. **Implementar con tests.** Skill `openspec-apply-change`, con tests para cada tarea y cumpliendo las convenciones de código (logs planos con prefijo, constantes compartidas, tests sin depender del `.env` local). Commits pequeños en la rama. Antes de seguir, `make cs-check`, `make phpstan` y `make test` en verde; si el stack local no está levantado, decirlo y no dar el cambio por verificado. No tocar `phpstan-baseline.neon` para silenciar errores nuevos.
4. **Añadir la entrada en el CHANGELOG.** Bajo `## [Sin publicar]`: la rama en `Ramas integradas en develop` y la entrada en `Añadido` / `Cambiado` / `Corregido`, más `Migraciones` / `Despliegue` si aplican (p. ej. `make migrate`). Escribir para quien lee la release: qué cambia para el usuario y por qué.
5. **Sincronizar specs y archivar el cambio.** Skill `openspec-archive-change`. Si una decisión cambió respecto a `Especificaciones.md`, actualizarlo. Commitear.
6. **Presentar el resumen de revisión y esperar confirmación.** Con todo commiteado en la rama, enseñar al usuario:
   - **Pedido frente a hecho:** cada escenario de la spec del cambio y dónde queda cubierto (código y test); señalar lo que falte.
   - **Decisiones tomadas sin consultar**, con su motivo.
   - **Ficheros tocados y commits** de la rama (`git diff --stat develop...` y `git log develop..`).
   - **Tests añadidos** y qué cubren, y **lo que no se ha probado** (p. ej. envíos reales a Telegram o pasos que solo se ven en producción).
   - **Checklist de olvidos habituales**, cada punto como hecho / no aplica / falta: CHANGELOG, `Especificaciones.md`, spec sincronizada, documentación de la API (OpenAPI + `doc/MyDiary.postman_collection.json` + `doc/api.http`), migraciones, variables en `.env.example` y `.env.test`, logs con campos planos con prefijo, pasos de despliegue.
   - **Resultado** de `make cs-check`, `make phpstan` y `make test`.

   **No seguir sin confirmación explícita del usuario.** Si pide cambios, hacerlos en la misma rama (con sus tests y comprobaciones) y volver a presentar el resumen.
7. **Hacer finish de la rama hacia `develop`.** Solo tras la confirmación del paso 6 y con el árbol limpio: `GIT_MERGE_AUTOEDIT=no git flow feature finish <nombre>` (o `bugfix finish`). Push **solo** de `develop`: `git push origin develop`. Nunca `main` ni tags; eso es cosa de `/release`.

Al terminar, informar de la rama integrada, el resumen del cambio, el resultado de las comprobaciones y si el despliegue requiere pasos extra (migraciones, variables de entorno, comandos en `diary-prod` para que los ejecute el usuario).
