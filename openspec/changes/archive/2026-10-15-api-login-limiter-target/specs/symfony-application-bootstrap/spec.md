## ADDED Requirements

### Requirement: Contenedor sin deprecaciones de compilación
El sistema SHALL compilar el contenedor de servicios sin registrar ninguna deprecación (fichero `<ContainerClass>Deprecations.log` vacío o inexistente). En particular, los servicios con alias con nombre, como los limitadores de `rate_limiter`, SHALL inyectarse con el atributo `#[Target]` y no solo por el nombre del parámetro.

#### Scenario: Compilación limpia
- **WHEN** se compila el contenedor del entorno de test
- **THEN** no queda ninguna deprecación registrada y `bin/console debug:container --deprecations` no lista ninguna

#### Scenario: Limitador del login de la API inyectado por target
- **WHEN** se construye `AuthController`
- **THEN** recibe el limitador `api_login` indicado con `#[Target]`, y el login sigue respondiendo `429` tras 5 intentos fallidos en 15 minutos
