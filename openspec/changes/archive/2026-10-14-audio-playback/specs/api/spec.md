## ADDED Requirements

### Requirement: Descarga del fichero de un audio
El sistema SHALL servir el fichero original de un audio en `GET /api/v1/audios/{id}/fichero`, autenticado con token, con `Content-Type` según su formato (`audio/ogg`, `audio/mp4`, `audio/mpeg` o `audio/wav`), `Accept-Ranges: bytes` y caché privada. Una petición con cabecera `Range` válida SHALL responder `206 Partial Content` con solo ese tramo y `Content-Range`. Si el audio no existe o su fichero no está en disco, la respuesta SHALL ser `404 not_found`. Cuando el audio existe pero falta su fichero, el sistema SHALL registrar un warning `audio.file_missing` con `audio_recording_id` y `audio_source`. La respuesta MUST NOT revelar la ruta del fichero en el servidor.

#### Scenario: Descarga completa
- **WHEN** se pide `GET /api/v1/audios/{id}/fichero` con un token válido para un audio `.ogg` guardado en disco
- **THEN** la respuesta es `200` con `Content-Type: audio/ogg`, `Accept-Ranges: bytes` y el contenido íntegro del fichero

#### Scenario: Descarga parcial con Range
- **WHEN** se pide el fichero con `Range: bytes=0-9`
- **THEN** la respuesta es `206` con los 10 primeros bytes y `Content-Range: bytes 0-9/<tamaño>`

#### Scenario: Audio inexistente
- **WHEN** se pide el fichero de un id que no existe
- **THEN** la respuesta es `404` con `code` `not_found`

#### Scenario: Fichero ausente en disco
- **WHEN** se pide el fichero de un audio cuyo fichero no está en disco
- **THEN** la respuesta es `404` con `code` `not_found` y se registra `audio.file_missing`

#### Scenario: Sin token
- **WHEN** se pide el fichero sin cabecera `Authorization`
- **THEN** la respuesta es `401` con `code` `unauthorized`
