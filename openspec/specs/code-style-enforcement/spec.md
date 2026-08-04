## Purpose

Validación automática de que el código PHP del proyecto cumple el estándar PSR-12, aplicada tanto bajo demanda como de forma obligatoria antes de cada commit.

## Requirements

### Requirement: PHP-CS-Fixer configurado con ruleset PSR-12
El sistema SHALL incluir `friendsofphp/php-cs-fixer` como dependencia de desarrollo, configurado mediante `.php-cs-fixer.dist.php` con el ruleset `@PSR12`.

#### Scenario: Validar código conforme
- **WHEN** se ejecuta `make cs-check` sobre código que cumple PSR-12
- **THEN** el comando termina sin errores y sin proponer cambios

#### Scenario: Detectar código no conforme
- **WHEN** se ejecuta `make cs-check` sobre código que no cumple PSR-12 (p. ej. indentación con tabs, llaves mal ubicadas)
- **THEN** el comando reporta las violaciones encontradas y termina con código de salida distinto de 0

### Requirement: Git hook `pre-commit` bloquea commits no conformes
El sistema SHALL incluir un hook de git `pre-commit` versionado en el repositorio (`.githooks/pre-commit`) que ejecuta PHP-CS-Fixer en modo `--dry-run` sobre los ficheros PHP en staging, y aborta el commit si detecta violaciones de PSR-12.

#### Scenario: Commit bloqueado por código no conforme
- **WHEN** un desarrollador intenta hacer `git commit` con un fichero `.php` en staging que no cumple PSR-12
- **THEN** el commit se aborta y se muestra el diff de las violaciones encontradas

#### Scenario: Commit permitido con código conforme
- **WHEN** un desarrollador hace `git commit` con únicamente ficheros `.php` en staging que cumplen PSR-12 (o sin ficheros `.php` en staging)
- **THEN** el commit se completa con normalidad

### Requirement: Autofix disponible bajo demanda
El sistema SHALL proveer un comando (`make cs-fix`) que aplica automáticamente las correcciones de PHP-CS-Fixer sobre el código, sin bloquear al desarrollador a corregir manualmente cada violación.

#### Scenario: Autofix aplica correcciones
- **WHEN** se ejecuta `make cs-fix` sobre código con violaciones de PSR-12 corregibles automáticamente
- **THEN** el código queda modificado en disco cumpliendo PSR-12
