## 1. Modelo

- [x] 1.1 `AudioRecording::deviceName` (`device_name`, `varchar(100)`, nullable) y migración

## 2. Subida

- [x] 2.1 `receiveUpload` y `upload` reciben el nombre del dispositivo: lo guardan al crear y al relanzar (`retrying`), no con `duplicate`
- [x] 2.2 `Api\AudioController::upload` lo toma del token de la petición
- [x] 2.3 Tests: servicio (creado, relanzado, duplicado) y funcional de la subida

## 3. Aviso

- [x] 3.1 `TelegramClient::sendMessage` con respuesta opcional a un mensaje (`reply_parameters`, `allow_sending_without_reply`)
- [x] 3.2 `TranscriptionFailureListener`: título, origen, hora local y duración; respuesta al mensaje original en los de Telegram
- [x] 3.3 Tests del cliente y del listener (app con dispositivo, app sin dispositivo, Telegram)

## 4. Documentación y verificación

- [x] 4.1 `Especificaciones.md` y `CHANGELOG.md`
- [x] 4.2 `make cs-check`, `make phpstan` y `make test` en verde
