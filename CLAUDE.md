# CLAUDE.md

Instrucciones para trabajar en este repositorio (Telegram Voice Notes — PHP/Symfony).

## Fuente de verdad

`Especificaciones.md` es el documento de referencia del dominio, flujos, modelo de datos e infraestructura. Léelo antes de implementar cualquier funcionalidad nueva y mantenlo actualizado si una decisión cambia durante el desarrollo.

## Restricciones de arquitectura (no reintroducir)

Estas decisiones se tomaron explícitamente para evitar sobre-ingeniería en un proyecto personal de un solo usuario. No proponer ni introducir lo contrario sin que el usuario lo pida:

- **Sin EasyAdmin.** Las vistas (Diario, Historial, Estadísticas) son dashboards custom con controladores Symfony + `FormType`, no CRUDs genéricos.
- **Sin hexagonal estricta / sin capas Domain-Application-Infrastructure separadas.** Las entidades Doctrine SON el modelo de dominio.
- **Sin CQRS ni bus de comandos/queries general.** Servicios de aplicación normales con métodos claros.
- **Interfaces (puertos) solo puntuales**, donde ya existe razón real: `TranscriberInterface`, `SummaryGeneratorInterface`. No generalizar a otras partes del código sin justificación equivalente.
- **Symfony Messenger solo para la cadena Telegram → transcripción**, no como bus general.
- **Sin entidad `User` en BD.** Usuario único vía `in_memory` provider de Symfony Security y variables de entorno.
- Regla general: introducir un patrón solo cuando el problema que resuelve ya existe, no de forma anticipada.

## Flujo de trabajo

- Antes de implementar una funcionalidad, si el proyecto tiene OpenSpec inicializado (carpeta `openspec/`), pasar por un change proposal (`openspec change`) en lugar de tocar código directamente.
- No hay tests ni build configurados todavía — este bloque se actualizará en cuanto exista `composer.json` con comandos reales.
