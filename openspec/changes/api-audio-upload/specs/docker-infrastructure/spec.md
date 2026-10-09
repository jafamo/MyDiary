## ADDED Requirements

### Requirement: Límites de subida para audios
La configuración de nginx SHALL admitir cuerpos de petición de hasta 30 MB (`client_max_body_size 30m`) y la imagen PHP SHALL fijar `upload_max_filesize` en `25M` y `post_max_size` en `30M`, de modo que un audio de 25 MB llegue a la aplicación.

#### Scenario: Límites de PHP en el contenedor
- **WHEN** se consulta `upload_max_filesize` y `post_max_size` dentro de `diary-php`
- **THEN** los valores son `25M` y `30M`

#### Scenario: Límite de nginx
- **WHEN** se inspecciona `docker/nginx/default.conf`
- **THEN** el bloque `server` declara `client_max_body_size 30m`

### Requirement: `ffprobe` disponible en la imagen PHP
La imagen PHP SHALL incluir `ffprobe` (paquete `ffmpeg`), disponible tanto en `diary-php` como en `diary-messenger-worker`.

#### Scenario: Binario presente
- **WHEN** se ejecuta `ffprobe -version` dentro de `diary-php`
- **THEN** el comando termina con código 0
