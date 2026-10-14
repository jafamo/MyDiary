## ADDED Requirements

### Requirement: Reproductor de audio en el log de Diario e Historial
Cada entrada del log de Diario e Historial SHALL incluir un reproductor de audio nativo (`<audio controls preload="none">`), en cualquier estado del audio, que carga el fichero desde la ruta con sesión `GET /audio/{id}/fichero`. Esa ruta SHALL servir el fichero con el mismo comportamiento que `GET /api/v1/audios/{id}/fichero` (tipo de contenido, soporte de `Range`, caché privada y `404` si falta el audio o su fichero), pero autenticada con la sesión web. El reproductor MUST NOT descargar el audio hasta que el usuario lo reproduzca.

#### Scenario: Reproductor en una entrada
- **WHEN** un usuario con sesión ve el Diario con un audio del día en cualquier estado
- **THEN** la entrada incluye un `<audio>` con `preload="none"` cuyo origen es `/audio/{id}/fichero`

#### Scenario: Descarga con sesión
- **WHEN** un usuario con sesión pide `/audio/{id}/fichero` de un audio guardado en disco
- **THEN** la respuesta es `200` con el contenido del fichero y su `Content-Type` de audio

#### Scenario: Descarga sin sesión
- **WHEN** un visitante sin sesión pide `/audio/{id}/fichero`
- **THEN** el sistema le redirige a `/login`

#### Scenario: Fichero ausente
- **WHEN** un usuario con sesión pide el fichero de un audio cuyo fichero no está en disco
- **THEN** la respuesta es `404`
