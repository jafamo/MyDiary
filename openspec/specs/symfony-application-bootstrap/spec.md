## Purpose

Skeleton de Symfony instalado, configuración de entorno y seguridad base sobre la que corre el resto de la aplicación.

## Requirements

### Requirement: Skeleton de Symfony instalado en `diary-php`
El sistema SHALL contar con un proyecto Symfony (última LTS, PHP >= 8.4) instalado en la raíz del repositorio, servido por `diary-nginx`/`diary-php` a través del bind mount existente.

#### Scenario: Aplicación accesible
- **WHEN** el stack está levantado (`make up`) y se accede a `http://localhost:9008`
- **THEN** Symfony responde (ya no un 404 de nginx por `public/` inexistente)

### Requirement: Configuración de base de datos vía `DATABASE_URL`
El sistema SHALL conectar Doctrine con `diary-postgres` usando una `DATABASE_URL` construida a partir de las credenciales ya definidas en `.env` (`POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`) y el hostname interno `diary-postgres`.

#### Scenario: Conexión válida a la base de datos
- **WHEN** se ejecuta `bin/console doctrine:query:sql "SELECT 1"` dentro de `diary-php`
- **THEN** la consulta se ejecuta sin error de conexión

### Requirement: Seguridad con provider Doctrine (entidad `User` en BD)
El sistema SHALL configurar Symfony Security con un provider Doctrine que autentica contra la entidad `User` en base de datos, en vez del provider `in_memory`.

#### Scenario: Login válido
- **WHEN** un usuario existente en la tabla `user` inicia sesión con sus credenciales correctas
- **THEN** Symfony Security lo autentica correctamente

### Requirement: Gestión de usuarios solo por consola
El sistema SHALL proveer comandos de consola para crear usuarios y cambiar su contraseña, sin exponer registro ni recuperación de contraseña en la web.

#### Scenario: Crear usuario por consola
- **WHEN** se ejecuta `bin/console app:user:create <username>`
- **THEN** se crea un nuevo `User` en BD con contraseña hasheada, sin necesidad de pasar por ninguna pantalla web

#### Scenario: Cambiar contraseña sin conocer la actual
- **WHEN** se ejecuta `bin/console app:user:change-password <username>` y se introduce una contraseña nueva
- **THEN** el `password_hash` del usuario se actualiza sin que el comando pida ni valide la contraseña anterior

#### Scenario: Sin flujo de recuperación web
- **WHEN** se revisan las rutas de la aplicación
- **THEN** no existe ninguna ruta de registro ni de recuperación de contraseña por email/token
