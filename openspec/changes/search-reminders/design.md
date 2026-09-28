## Context

`SearchController` genera el embedding de la consulta y combina `TranscriptionRepository::searchBySimilarity` y `DailySummaryRepository::searchBySimilarity` (pgvector, distancia coseno). `Reminder` solo tiene `date`, `time`, `text`, `createdAt` y `updatedAt`, sin embedding. Si falla el embedding, `search()` devuelve `[]` y la vista muestra "Sin resultados".

Los textos de los recordatorios son cortos ("Cita dentista", "ITV coche"), así que una búsqueda por palabras es más útil y predecible que una semántica. El usuario eligió búsqueda por palabras con `ILIKE` y los resultados en una sección aparte.

## Goals / Non-Goals

**Goals:**
- Encontrar recordatorios por subcadena de su texto, sin distinguir mayúsculas/minúsculas ni tildes.
- Mostrarlos en una sección propia en `/busqueda`, independiente de Ollama.

**Non-Goals:**
- Embeddings de `Reminder` o búsqueda semántica sobre recordatorios.
- Mezclar recordatorios en el ranking por similitud.
- Búsqueda por palabras en transcripciones o resúmenes.
- Índices (trigram/GIN): con un solo usuario y pocos recordatorios, un recorrido secuencial es suficiente.

## Decisions

### Normalización con `unaccent` de PostgreSQL
`WHERE unaccent(lower(r.text)) LIKE unaccent(lower(:pattern)) ESCAPE '\'`, con la extensión `unaccent` activada en una migración (`CREATE EXTENSION IF NOT EXISTS unaccent`), igual que ya se hizo con `vector`.
- *Alternativa:* `ILIKE` sin `unaccent`. Descartada: "medico" no encontraría "Médico", algo muy habitual al escribir rápido en el móvil.
- *Alternativa:* normalizar en PHP y filtrar en memoria. Descartada: carga todos los recordatorios en cada búsqueda.
- La imagen `pgvector/pgvector` (producción y servicio Postgres de CI) se basa en la imagen oficial de Postgres, que ya incluye `unaccent` en contrib.

### Consulta DQL con una función `unaccent` registrada
Registrar una función DQL `unaccent` en `config/packages/doctrine.yaml` (junto a `cosine_distance`), con una clase `FunctionNode` mínima en `src/Doctrine/Unaccent.php`, y escribir `ReminderRepository::searchByText(string $query, int $limit): list<Reminder>` con el QueryBuilder, como el resto del repositorio.
- *Alternativa:* SQL nativo con `ResultSetMappingBuilder`. Válida, pero rompe el estilo QueryBuilder del repositorio y duplica el mapeo.

### Escapado de comodines
Antes de construir `%…%`, escapar `\`, `%` y `_` en la consulta para que se busquen de forma literal.

### Controlador y plantilla
- `SearchController` inyecta `ReminderRepository`, calcula `reminders` siempre que la consulta no esté vacía y la pasa a la plantilla junto a `results`. El fallo del embedding solo vacía `results`.
- Constante `MAX_REMINDER_RESULTS = 20` en el controlador, junto a `MAX_RESULTS` (es específica de esta vista).
- Plantilla: bloque "Recordatorios (N)" antes de la lista semántica, reutilizando las clases de tarjetas/listas existentes. El enlace apunta a `app_recordatorios` con `date`, `year` y `month`, igual que "Ver día" apunta a Historial. "Sin resultados" solo se muestra si `results` y `reminders` están vacíos. Se actualiza el texto de ayuda inicial para mencionar los recordatorios.

## Risks / Trade-offs

- [No hay coincidencias por sinónimos en recordatorios] → Es lo que se decidió; la búsqueda semántica sigue cubriendo transcripciones y resúmenes.
- [La migración `CREATE EXTENSION` necesita permisos de superusuario] → El usuario de la app ya creó `vector` en la misma base, así que tiene esos permisos. La BD de test la crean las migraciones de la misma forma.
- [Rendimiento con `unaccent(lower(...))` sin índice] → Volumen muy bajo; se puede añadir un índice funcional más adelante si hiciera falta.

## Migration Plan

1. Desplegar y ejecutar las migraciones (`doctrine:migrations:migrate`), que crean la extensión `unaccent`.
2. Rollback: `down()` hace `DROP EXTENSION IF EXISTS unaccent`; la búsqueda de recordatorios fallaría, así que se revierte junto con el código.
