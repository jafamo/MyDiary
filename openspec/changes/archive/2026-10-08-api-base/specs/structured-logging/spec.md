## ADDED Requirements

### Requirement: Identificador del token de API en el log por petición
El log por petición HTTP (`http.request`) SHALL incluir el campo de primer nivel `api_token_id` (numérico) cuando la petición se ha autenticado con un token de API, y MUST NOT incluirlo en el resto de peticiones. Ningún log MUST contener el token en claro ni su hash.

#### Scenario: Petición autenticada con token
- **WHEN** se pide `GET /api/v1/me` con un token válido cuyo id es 7
- **THEN** la línea `http.request` de esa petición incluye `"api_token_id": 7`

#### Scenario: Petición web con sesión
- **WHEN** un usuario con sesión web pide `/historial`
- **THEN** la línea `http.request` de esa petición no incluye `api_token_id`

### Requirement: Logs del login de la API
La aplicación SHALL registrar cada intento de login de la API con un campo `event`: `api.login_succeeded` (nivel `info`, con `api_token_id` y `api_device_name`), `api.login_failed` (nivel `warning`, con `api_username`) y `api.login_throttled` (nivel `warning`, con `api_username`). Estos logs MUST NOT incluir la contraseña ni el token.

#### Scenario: Login correcto
- **WHEN** un login de la API tiene éxito para el dispositivo "iPhone"
- **THEN** se escribe una línea `info` con `"event": "api.login_succeeded"` y `"api_device_name": "iPhone"`

#### Scenario: Login con contraseña incorrecta
- **WHEN** un login de la API falla por contraseña incorrecta del usuario `jfarinos`
- **THEN** se escribe una línea `warning` con `"event": "api.login_failed"` y `"api_username": "jfarinos"`, sin la contraseña enviada
