## 1. Implementación

- [ ] 1.1 `PublicUrlProxyListener` y valor por defecto/bind de `KEENDNS_PROXY_IP` en `config/services.yaml`; variable en `.env.example` y `.env.test`
- [ ] 1.2 Tests unitarios del listener (IP del proxy, otra IP, variable vacía, subpetición, puerto explícito) y funcional de la redirección al login

## 2. Documentación y verificación

- [ ] 2.1 `Especificaciones.md` (sección 7) y `CHANGELOG.md`
- [ ] 2.2 `make cs-check`, `make phpstan` y `make test` en verde
