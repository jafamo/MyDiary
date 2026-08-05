## 1. Preparación

- [x] 1.1 Instalar `symfony/scheduler` — nombre de schedule `default`, transporte a consumir `scheduler_default` (convención confirmada con `bin/console debug:scheduler`)
- [x] 1.2 Añadir/confirmar variables en `.env`/`.env.example`: `OLLAMA_BASE_URL=http://192.168.4.200:11434` (ya prevista), `OLLAMA_MODEL=qwen2.5:14b`
- [x] 1.3 Añadir binding de `$ollamaBaseUrl`/`$ollamaModel` en `config/services.yaml`

## 2. Generador de resumen (Ollama)

- [x] 2.1 `src/Contract/SummaryGeneratorInterface.php` (`generate(array $transcriptions): array{summary, topics}`)
- [x] 2.2 `src/Contract/SummaryGenerationException.php` (mismo patrón que `TranscriptionException`: `errorCode`, `errorMessage` descriptivo)
- [x] 2.3 `src/Service/Ollama/OllamaSummaryGenerator.php`: llama al endpoint compatible OpenAI de Ollama, pide JSON explícito en el prompt, parsea la respuesta, lanza `SummaryGenerationException` si falla la petición o el parseo
- [x] 2.4 Registrar el alias de servicio `SummaryGeneratorInterface` → `OllamaSummaryGenerator` en `config/services.yaml`
- [x] 2.5 Tests unitarios de `OllamaSummaryGenerator` con `MockHttpClient` (éxito, error HTTP, JSON inválido en la respuesta)

## 3. Repositorios: finders por fecha

- [x] 3.1 `AudioRecordingRepository`: `findPendingReceivedOn(\DateTimeImmutable $date): array`, `findTranscribedReceivedOn(\DateTimeImmutable $date): array` (rango `Europe/Madrid`, vía `App\Service\DateRange` — nuevo helper compartido; confirmado que la app corre en UTC en runtime y hubo que convertir explícitamente el rango Madrid→UTC para que coincida con cómo Doctrine escribe `receivedAt`)
- [x] 3.2 `DailySummaryRepository`: `findOneByDate(\DateTimeImmutable $date): ?DailySummary`
- [x] 3.3 `TopicRepository`: `findOneByName(string $name): ?Topic`

## 4. `DailySummaryService`

- [x] 4.1 `src/Service/DailySummaryService.php::generateForDate()`: espera corta por `PENDING` (15s, hasta 3 min), recoge transcripciones `TRANSCRIBED`, llama al generador con reintentos (2 reintentos, 5s de espera), guarda/actualiza `DailySummary` + `Topic` (reemplazando asociaciones), maneja el fallo definitivo (log + notificación, sin persistir). Los tiempos de espera/reintento son parámetros del constructor con esos valores por defecto (no constantes fijas), para poder inyectar valores mínimos en tests sin esperas reales
- [x] 4.2 Tests unitarios de `DailySummaryService`: generación nueva, actualización de un día existente, fallo tras agotar reintentos, día sin transcripciones — confirmado: no se llama al generador ni se crea `DailySummary` si no hay transcripciones ese día (se sale silenciosamente, no se considera fallo)

## 5. Comando y Scheduler

- [x] 5.1 `src/Command/GenerateDailySummaryCommand.php` (`app:generate-daily-summary`, opción `--date=YYYY-MM-DD`, por defecto hoy)
- [x] 5.2 Configurar el proveedor de schedule (`#[AsSchedule('default')]` o equivalente) con `RunCommandMessage('app:generate-daily-summary')` en trigger cron `0 21 * * *` timezone `Europe/Madrid` — verificado con `bin/console debug:scheduler` (próxima ejecución correcta en +0200/CEST). *(Hallazgo)*: hubo que instalar `dragonmantank/cron-expression`, requerido por `CronExpressionTrigger` y no incluido automáticamente por `symfony/scheduler`
- [x] 5.3 Test del comando (`CommandTester`) para la ejecución manual con `--date`
- [x] 5.4 Cambiar `command` de `diary-messenger-worker` en `docker-compose.yml` a `php bin/console messenger:consume async scheduler_default -vv`
- [x] 5.5 Relanzar el stack y confirmar que `diary-messenger-worker` sigue `Up` (no crashea en bucle) con el nuevo comando — confirmado, log muestra "Consuming messages from transports 'async, scheduler_default'"

## 6. Verificación

- [x] 6.1 `make test` pasa en verde con todos los tests nuevos — 48 tests, 117 assertions
- [x] 6.2 `make cs-check` pasa sin errores sobre el código nuevo — 0/53 ficheros con violaciones
- [x] 6.3 Ejecución manual de `bin/console app:generate-daily-summary --date=<fecha con transcripciones de prueba>` contra Ollama real, confirmar `DailySummary`/`Topic` creados correctamente en `diary-postgres` — confirmado con `qwen2.5:14b`: resumen coherente y 5 temas extraídos correctamente; datos de prueba limpiados después
- [x] 6.4 Confirmar log JSON estructurado ante un fallo forzado (`OLLAMA_BASE_URL` apuntando a un puerto inválido temporalmente) — confirmado tras 3 intentos (~11s), log JSON con `event`, `date`, `error_code`, `error_message`, `attempt_count`; sin `DailySummary` persistido; `.env` restaurado y datos de prueba limpiados
