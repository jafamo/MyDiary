# 🎙️ Telegram Voice Notes

Web application that receives voice notes via Telegram, automatically transcribes them, generates a daily summary with the topics discussed, and lets you review/edit everything from a web interface with login.

> 📄 The full domain specification, flows and data model live in [`Especificaciones.md`](./Especificaciones.md) (Spanish). This README is just a technical summary. A Spanish version of this README is available at [`doc/README_ES.md`](./doc/README_ES.md).

## 🧱 Tech stack

| | Component | Technology |
|---|---|---|
| 🐘 | Language / runtime | PHP >= 8.4 |
| 🎼 | Framework | Symfony (latest LTS) |
| 🌿 | Views | Twig |
| 🗃️ | ORM | Doctrine |
| 📝 | Logging | Monolog |
| 🐬 | Database | PostgreSQL 16 |
| 📬 | Queue / async | Symfony Messenger (Doctrine or Redis) |
| ⏰ | Scheduler | Symfony Scheduler |
| 🐳 | Containers | Docker + Docker Compose |
| 🗣️ | Transcription (STT) | Open WebUI (Whisper) / `whisper.cpp` as fallback |
| 🧠 | Summary / topics (LLM) | Ollama (qwen2.5, llama3.1, gemma2, deepseek-r1) via OpenAI-compatible API |
| 🤖 | Bot | Telegram Bot API |
| 🖥️ | Target infrastructure | Mini PC with 32GB RAM |

## 🏗️ Architecture

Personal single-user project — deliberately avoiding over-engineering:

- ❌ No EasyAdmin — custom dashboards with Symfony controllers + `FormType`
- ❌ No strict hexagonal architecture or separate Domain/Application/Infrastructure layers — Doctrine entities ARE the domain model
- ❌ No CQRS or general command/query bus
- ❌ No `User` entity in the database — single user via Symfony Security's `in_memory` provider
- ✅ Targeted interfaces (ports) where there's a real reason: `TranscriberInterface`, `SummaryGeneratorInterface`
- ✅ Symfony Messenger used only for the Telegram → transcription chain

More detail and rationale in [`CLAUDE.md`](./CLAUDE.md) and section 4 of [`Especificaciones.md`](./Especificaciones.md).

## 🔄 General flow

1. 📲 User sends an audio message to the Telegram bot
2. 🪝 The Symfony webhook receives it and replies quickly ("Audio recibido ✅")
3. 📬 Symfony Messenger dispatches the transcription asynchronously
4. 🗣️➡️📄 Whisper / Open WebUI transcribes the audio
5. ⏰ At 21:00 (Europe/Madrid), Symfony Scheduler generates the daily summary with Ollama
6. 🌐 The user reviews/edits everything from the web: **Diario**, **Historial**, **Estadísticas**

## 📂 Views

- 🔐 Login
- 📔 Diario (Journal) — today's audios + transcriptions, with the daily summary at the end
- 🗓️ Historial (History) — browse by date
- 📊 Estadísticas (Stats) — audios/day, average duration, frequent topics
- 🚪 Logout

## 🌳 Version control

Git Flow (`main` for releases only, `develop` as the integration branch). See [`CLAUDE.md`](./CLAUDE.md) for branch details and commands.

## 🚧 Status

Project in the specification phase — no `composer.json` or build setup yet.
