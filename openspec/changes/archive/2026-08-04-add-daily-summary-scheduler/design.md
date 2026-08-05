## Context

Ollama ya está confirmado y accesible en `192.168.4.200:11434`, con los modelos `qwen2.5:14b`, `llama3.1:8b`, `gemma2:27b`, `deepseek-r1:14b` disponibles (confirmado por API `/api/tags` en la fase de exploración de infraestructura). `AudioRecording`/`Transcription`/`DailySummary`/`Topic` ya existen con sus relaciones. `APP_TIMEZONE=Europe/Madrid` ya está en `.env`. `TranscriberInterface`/`SummaryGeneratorInterface` son las dos interfaces puntuales ya anticipadas en `CLAUDE.md` — esta es la segunda y última prevista.

## Goals / Non-Goals

**Goals:**
- Disparo diario automático a las 21:00 `Europe/Madrid` vía Symfony Scheduler, ejecutando `app:generate-daily-summary`.
- El mismo comando ejecutable manualmente (sin esperar al Scheduler), útil para pruebas y para regenerar un día concreto.
- Espera corta si hay audios `PENDING` del día antes de generar el resumen, para no dejar fuera transcripciones casi listas.
- Resumen + lista de temas generados por Ollama, guardados/actualizados en `DailySummary`/`Topic`.
- Manejo de errores según 3.3 paso 6: reintentos cortos, log estructurado, sin `DailySummary`, notificación de fallo, sin reintento al día siguiente.

**Non-Goals:**
- No se implementan vistas que muestren el resumen (paso 5, siguiente change).
- No se implementa edición manual del resumen desde la web (no hay vistas todavía).
- No se resuelve backfill masivo de días pasados como funcionalidad de producto — el comando acepta una fecha opcional por conveniencia de desarrollo/pruebas, pero no hay UI ni automatismo para ello.

## Decisions

### 1. Scheduler dispara `RunCommandMessage`, no un mensaje propio
Symfony Scheduler (`symfony/scheduler`) programa un `Symfony\Component\Scheduler\Messenger\RunCommandMessage('app:generate-daily-summary')` con un trigger cron `0 21 * * *` en timezone `Europe/Madrid`, vía un proveedor de schedule (`#[AsSchedule('default')]`). Coincide literalmente con la descripción de `Especificaciones.md` ("Symfony Scheduler dispara el comando"), y evita duplicar lógica entre un hipotético `GenerateDailySummaryMessage`/handler y el comando — un único punto de entrada (`GenerateDailySummaryCommand`) que delega en `DailySummaryService`.

### 2. `DailySummaryService::generateForDate(\DateTimeImmutable $date)` centraliza la lógica
El comando solo resuelve la fecha (hoy por defecto, o el argumento opcional `--date=YYYY-MM-DD`) y delega. Mismo patrón que `AudioRecordingService`: el comando queda fino y la lógica es testeable sin consola de por medio.

### 3. Límites de la espera corta por audios `PENDING` (paso 2)
No especificados en `Especificaciones.md` más allá de "ventana corta". Se fija: comprobar cada **15 segundos**, hasta un máximo de **3 minutos** (12 intentos) antes de continuar igualmente (con o sin los `PENDING` resueltos) — un audio que lleva minutos en cola cuando ya son las 21:00 probablemente tiene un problema real y no debe bloquear el resumen indefinidamente; el mensaje 3.4-bis y el estado `ERROR` ya cubren ese caso por separado.

### 4. Límite del día: rango horario en `Europe/Madrid`, convertido a UTC para la comparación real
"Día en curso" se calcula con la medianoche de `Europe/Madrid` como inicio y `+1 day` como fin (exclusivo), aplicado sobre `AudioRecording.receivedAt`. *(Confirmado durante la implementación)*: la app corre con `date_default_timezone = UTC` en runtime, y Doctrine escribe el valor "wall-clock" del `\DateTimeImmutable` tal cual (sin convertir de zona). Por tanto el rango Madrid se convierte explícitamente a UTC (`App\Service\DateRange::dayBoundaries()`, helper compartido) antes de usarlo en la query — comparar directamente un rango etiquetado "Europe/Madrid" contra columnas que en realidad contienen wall-clock UTC habría dado resultados incorrectos (desfase de 1-2 horas según horario de verano/invierno).

### 5. `SummaryGeneratorInterface` y formato de respuesta de Ollama
```php
interface SummaryGeneratorInterface
{
    /**
     * @param list<string> $transcriptions
     * @return array{summary: string, topics: list<string>}
     */
    public function generate(array $transcriptions): array;
}
```
`OllamaSummaryGenerator` llama al endpoint compatible OpenAI de Ollama (`POST /v1/chat/completions`, modelo configurable vía `OLLAMA_MODEL`, por defecto `qwen2.5:14b` — buen equilibrio tamaño/calidad entre los 4 modelos disponibles) con un prompt que pide **explícitamente una respuesta JSON** `{"summary": "...", "topics": ["...", ...]}`, y parsea la respuesta. Si el modelo no devuelve JSON válido, se trata como fallo (mismo camino de reintento/error que un fallo de red — ver decisión 7).

### 6. Upsert de `DailySummary`/`Topic`
Si ya existe un `DailySummary` para la fecha (re-ejecución manual del comando), se **actualiza** en vez de duplicar: `summary_text`/`generated_at` se sobrescriben, y las asociaciones de `Topic` se reemplazan por completo (se limpia la colección `topics` y se vuelve a poblar) — evita acumular temas obsoletos de una ejecución anterior fallida a medias. Los `Topic` se buscan por `name` exacto y se crean si no existen (ya documentado en `Especificaciones.md` sección 6).

### 7. Manejo de errores: reintentos síncronos cortos, no Messenger
A diferencia de la transcripción (asíncrona, con `retry_strategy` de Messenger), esta llamada ocurre dentro de la ejecución síncrona del comando — se implementa un bucle de reintento simple: **2 reintentos, 5 segundos de espera entre cada uno** (3 intentos totales), coherente con "se reintenta un par de veces con espera corta". Si el tercer intento también falla: `SummaryGenerationException` (mismo patrón `errorCode`/`errorMessage` descriptivo que `TranscriptionException`), log `error` estructurado (`event: daily_summary.generation_failed`, `date`, `error_code`, `error_message`, `attempt_count`), no se persiste `DailySummary`, y se notifica *"No se pudo generar el resumen de hoy ⚠️"* por Telegram. No hay mecanismo de reintento al día siguiente (el usuario puede volver a ejecutar el comando a mano si quiere).

## Risks / Trade-offs

- **[Riesgo] Modelo de Ollama no obedece el formato JSON pedido**: mitigado parcialmente pidiéndolo explícitamente en el prompt, pero no hay garantía — si el parseo falla se trata como error (decisión 5/7), no se inventa un resumen parcial.
- **[Riesgo] `symfony/scheduler` requiere un worker consumiendo el schedule**: el trigger no se dispara solo por estar configurado — hace falta `bin/console messenger:consume scheduler_default` corriendo (o añadirlo al transporte ya consumido por `diary-messenger-worker`). Se añade como transporte adicional al mismo `diary-messenger-worker` en vez de crear un contenedor nuevo, para no ampliar la infraestructura sin necesidad real.
- **[Trade-off] Ventana de espera de audios `PENDING` es una heurística fija (15s/3min)**: ajustable si en la práctica resulta muy corta o muy larga una vez en uso real.

## Migration Plan

No aplica (funcionalidad nueva). Pasos de puesta en marcha:
1. Instalar `symfony/scheduler`, configurar el proveedor de schedule.
2. Añadir `scheduler` como transporte que consume `diary-messenger-worker` (junto a `async`).
3. Implementar `SummaryGeneratorInterface`/`OllamaSummaryGenerator`, `DailySummaryService`, `GenerateDailySummaryCommand`.
4. Confirmar `OLLAMA_BASE_URL=http://192.168.4.200:11434` y fijar `OLLAMA_MODEL=qwen2.5:14b` en `.env`.
5. Probar el comando manualmente contra datos reales/de prueba antes de confiar en el disparo automático de las 21:00.

## Open Questions

Ninguna bloqueante.
