## Decisions

- **Actividad = audios o resumen**, como `days` en `GET /api/v1/historial?month=`: si el calendario marca un día, las flechas también llegan a él.
- **Dos consultas por lado**: el audio más cercano (`MAX`/`MIN` de `receivedAt` fuera de los límites del día en `Europe/Madrid`, pasado a día local) y el resumen más cercano (`MAX`/`MIN` de `date`); gana el más próximo. Usan los índices existentes de `received_at` y `date`.
- **Sin filtro `status`**: el filtro cambia lo que se ve del día, no qué días existen.
