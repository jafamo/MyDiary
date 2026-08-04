# 🎙️ Telegram Voice Notes

Aplicación web que recibe notas de voz por Telegram, las transcribe automáticamente, genera un resumen diario con los temas tratados y permite consultar/editar todo desde una interfaz web con login.

> 📄 La especificación completa del dominio, flujos y modelo de datos vive en [`Especificaciones.md`](../Especificaciones.md). Este README es solo un resumen técnico. Versión en inglés disponible en [`README.md`](../README.md).

## 🧱 Stack tecnológico

| | Componente | Tecnología |
|---|---|---|
| 🐘 | Lenguaje / runtime | PHP >= 8.4 |
| 🎼 | Framework | Symfony (última LTS) |
| 🌿 | Vistas | Twig |
| 🗃️ | ORM | Doctrine |
| 📝 | Logging | Monolog |
| 🐬 | Base de datos | PostgreSQL 16 |
| 📬 | Cola / async | Symfony Messenger (Doctrine o Redis) |
| ⏰ | Scheduler | Symfony Scheduler |
| 🐳 | Contenedores | Docker + Docker Compose |
| 🗣️ | Transcripción (STT) | Open WebUI (Whisper) / `whisper.cpp` como fallback |
| 🧠 | Resumen / temas (LLM) | Ollama (qwen2.5, llama3.1, gemma2, deepseek-r1) vía API compatible OpenAI |
| 🤖 | Bot | Telegram Bot API |
| 🖥️ | Infraestructura destino | Mini PC con 32GB RAM |

## 🏗️ Arquitectura

Proyecto personal de un solo usuario — deliberadamente sin sobre-ingeniería:

- ❌ Sin EasyAdmin — dashboards custom con controladores Symfony + `FormType`
- ❌ Sin hexagonal estricta ni capas Domain/Application/Infrastructure — las entidades Doctrine son el modelo de dominio
- ❌ Sin CQRS ni bus general de comandos/queries
- ❌ Sin entidad `User` en BD — usuario único vía `in_memory` provider de Symfony Security
- ✅ Interfaces puntuales donde hay razón real: `TranscriberInterface`, `SummaryGeneratorInterface`
- ✅ Symfony Messenger solo para la cadena Telegram → transcripción

Más detalle y motivos en [`CLAUDE.md`](../CLAUDE.md) y la sección 4 de [`Especificaciones.md`](../Especificaciones.md).

## 🔄 Flujo general

1. 📲 Usuario envía un audio al bot de Telegram
2. 🪝 Webhook de Symfony lo recibe y responde rápido ("Audio recibido ✅")
3. 📬 Symfony Messenger despacha la transcripción de forma asíncrona
4. 🗣️➡️📄 Whisper / Open WebUI transcribe el audio
5. ⏰ A las 21:00 (Europe/Madrid), Symfony Scheduler genera el resumen diario con Ollama
6. 🌐 El usuario consulta/edita todo desde la web: **Diario**, **Historial**, **Estadísticas**

## 📂 Vistas

- 🔐 Login
- 📔 Diario — audios + transcripciones del día, con resumen al final
- 🗓️ Historial — navegación por fecha
- 📊 Estadísticas — nº de audios/día, duración media, temas frecuentes
- 🚪 Logout

## 🌳 Control de versiones

Git Flow (`main` solo releases, `develop` como rama de integración). Ver [`CLAUDE.md`](../CLAUDE.md) para el detalle de ramas y comandos.

## 🚧 Estado

Proyecto en fase de especificación — sin `composer.json` ni build configurados todavía.
