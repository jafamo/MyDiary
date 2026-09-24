## 1. Makefile

- [x] 1.1 Añadir `SONAR_HOST_URL ?= https://sonarqube.jfarinos.keenetic.pro` y el target `sonar` (comprobación de `SONAR_TOKEN` + `docker run --rm` de `sonarsource/sonar-scanner-cli` montando el repo con el uid/gid del host) y añadirlo a `.PHONY`.
- [x] 1.2 Verificar que `make sonar` sin token falla con mensaje claro.

## 2. Documentación

- [x] 2.1 Mencionar `make sonar` en el README junto a los demás comandos.
