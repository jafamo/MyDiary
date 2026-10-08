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
| 🐬 | Database | PostgreSQL 16 + pgvector |
| 📬 | Queue / async | Symfony Messenger (Doctrine or Redis) |
| ⏰ | Scheduler | Symfony Scheduler |
| 🐳 | Containers | Docker + Docker Compose |
| 🗣️ | Transcription (STT) | Open WebUI (local Whisper) |
| 🧠 | Summary / topics (LLM) | Ollama via OpenAI-compatible API |
| 🔎 | Semantic search | Ollama embeddings (`nomic-embed-text`) + pgvector cosine distance |
| 🤖 | Bot | Telegram Bot API |
| 🖥️ | Target infrastructure | Mini PC with 32GB RAM |

## 🏗️ Architecture

Personal single-user project — deliberately avoiding over-engineering:

- ❌ No EasyAdmin — custom dashboards with Symfony controllers + `FormType`
- ❌ No strict hexagonal architecture or separate Domain/Application/Infrastructure layers — Doctrine entities ARE the domain model
- ❌ No CQRS or general command/query bus
- ✅ Single `User` entity in the database (Symfony Security) — no self-registration or web password recovery; users are managed via `bin/console app:user:*`
- ✅ Targeted interfaces (ports) where there's a real reason: `TranscriberInterface`, `SummaryGeneratorInterface`
- ✅ Symfony Messenger used only for the Telegram → transcription chain

More detail and rationale in [`AGENTS.md`](./AGENTS.md) and section 4 of [`Especificaciones.md`](./Especificaciones.md).

## 🔄 General flow

1. 📲 User sends an audio message to the Telegram bot
2. 🪝 The Symfony webhook receives it and replies quickly ("Audio recibido ✅")
3. 📬 Symfony Messenger dispatches the transcription asynchronously
4. 🗣️➡️📄 Whisper / Open WebUI transcribes the audio; on failure it's retried and, if it keeps failing, marked `ERROR` (retryable from the web)
5. ⏰ At 21:00 (Europe/Madrid), Symfony Scheduler generates the daily summary with Ollama — or on demand, right away, from a button in Diario. Summaries not written in Spanish are rejected and retried; if it still fails, a Telegram notice is sent
6. 🔔 At 08:00 (Europe/Madrid), Telegram sends the reminders due today
7. 🌐 The user reviews everything from the web: **Diario**, **Historial**, **Resúmenes**, **Búsqueda**, **Recordatorios**, **Estadísticas**, **Temas** — editing transcriptions, retrying failed ones, or deleting audios in cascade (DB + files)

```mermaid
flowchart TD
    A["📲 Voice note sent via Telegram"] --> B["🪝 Telegram webhook"]
    B -->|"Audio recibido ✅"| C[("AudioRecording · PENDING")]
    B --> D["📬 TranscribeAudioMessage<br/>(Symfony Messenger / Redis)"]
    D --> E["⚙️ Messenger worker"]
    E --> F["🗣️ Whisper / Open WebUI"]
    F -->|success| G[("Transcription saved<br/>AudioRecording · TRANSCRIBED")]
    F -->|"fails after retries"| H[("AudioRecording · ERROR")]
    G --> I["✅ Telegram confirmation"]
    H --> J["❌ Telegram failure notice"]

    subgraph WEB["🌐 Web app"]
        K["📔 Diario / 🗓️ Historial"]
        K -->|edit| L["✏️ Edit transcription<br/>(regenerates export file)"]
        K -->|delete| M["🗑️ Cascade delete<br/>(DB + audio + export files)"]
        K -->|retry| N["🔄 Reset ERROR → PENDING"]
        K -->|"generate summary"| O["🧠 DailySummaryService"]
    end

    G --> K
    H --> K
    N --> D

    P["⏰ Scheduler · 21:00 Europe/Madrid"] --> O
    O --> Q["🧠 Ollama"]
    Q --> R[("DailySummary + Topics")]
    R --> K
    R --> S["📊 Estadísticas"]
```

## 📂 Views

- 🔐 Login
- 📔 Diario (Journal) — today's audios + transcriptions, with the daily summary at the end
- 🗓️ Historial (History) — browse by date
- 🏷️ Resúmenes (Summaries) — list of daily summaries with their topics, filterable by date range
- 🔎 Búsqueda (Search) — natural-language search over transcriptions and daily summaries (embeddings), plus word search over reminders (case- and accent-insensitive), shown in its own section
- 🔔 Recordatorios (Reminders) — monthly calendar, upcoming and past lists, create/edit/delete
- 📊 Estadísticas (Stats) — audios/day, average duration, frequent topics, AI usage
- 🏷️ Temas (Topics) — table to rename and merge duplicated topics
- 🚪 Logout

## 📱 JSON API

A second entry point under `/api/v1`, meant for the iPhone app (phased plan in [ROADMAP.md](ROADMAP.md)). The web UI keeps working with its session login.

- 🔑 Opaque per-device tokens: `POST /api/v1/login` returns the token, sent afterwards as `Authorization: Bearer <token>`. Only its SHA-256 hash is stored; it expires after 90 days without use
- 🚪 `POST /api/v1/logout` revokes the token in use; `GET /api/v1/me` returns the user and token details
- 🧱 Every error is `{"code": "...", "message": "..."}`; login is limited to 5 attempts per 15 minutes
- 🛠️ Tokens are listed and revoked from the console: `bin/console app:user:token:list <username>` / `app:user:token:revoke <id>`
- 📖 Docs: Swagger UI at `/doc/api` (web session required), schema in [doc/openapi.json](doc/openapi.json), and request collections in [doc/MyDiary.postman_collection.json](doc/MyDiary.postman_collection.json) and [doc/api.http](doc/api.http)

## 🌳 Version control

Git Flow (`main` for releases only, `develop` as the integration branch). See [`AGENTS.md`](./AGENTS.md) for branch details and commands.

## 🔍 Static analysis

CI (`.github/workflows/ci.yml`) runs on every push/PR: PHP-CS-Fixer and PHPStan, then PHPUnit with coverage, then SonarQube with that coverage (only on `develop` and PRs: SonarQube Community has no branch support, and `main` only receives code already analysed on `develop`). Locally:

```bash
make cs-check   # PSR-12
make phpstan    # PHPStan (level 5 + baseline)
make test       # PHPUnit
```

To run SonarQube locally (Docker only, no scanner install needed; no coverage data):

```bash
SONAR_TOKEN=<your-token> make sonar
```

`SONAR_HOST_URL` defaults to `https://sonarqube.jfarinos.keenetic.pro` and can be overridden the same way.

Pushing a `X.Y.Z` tag creates the GitHub Release automatically from the matching `CHANGELOG.md` section (`.github/workflows/release.yml`).

## 🚀 Deploying

`make deploy` pulls `main`, clears the cache and restarts `diary-php` and `diary-messenger-worker`, but **does not run migrations**: if the release notes in [`CHANGELOG.md`](./CHANGELOG.md) list any, run `make migrate` afterwards.

Before deploying (or updating `OLLAMA_EMBEDDING_MODEL`), make sure the embeddings model is pulled on the Ollama server (e.g. `ollama pull nomic-embed-text`) — semantic search generates embeddings on demand and fails silently (logged, non-blocking) if the model isn't available.

## 🚧 Status

In active development, deployed and in daily use. See [`openspec/changes/archive/`](./openspec/changes/archive/) for the history of implemented changes.
