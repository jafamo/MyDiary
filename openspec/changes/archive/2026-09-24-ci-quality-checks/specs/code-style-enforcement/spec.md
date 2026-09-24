## ADDED Requirements

### Requirement: Verificación de estilo en CI
El sistema SHALL ejecutar en GitHub Actions `php-cs-fixer fix --dry-run --diff` sobre todo el repositorio, con la configuración `.php-cs-fixer.dist.php`, en cada `push` a `main`/`develop` y en cada `pull_request`. El job SHALL fallar si hay alguna violación, sin modificar el código.

#### Scenario: Código conforme
- **WHEN** se hace push de un commit cuyo código PHP cumple PSR-12
- **THEN** el job de estilo termina con éxito

#### Scenario: Violación que se saltó el hook local
- **WHEN** se hace push de un commit con un fichero `.php` que no cumple PSR-12 (p. ej. commiteado con `git commit --no-verify`)
- **THEN** el job de estilo falla y el log muestra el diff de las violaciones
