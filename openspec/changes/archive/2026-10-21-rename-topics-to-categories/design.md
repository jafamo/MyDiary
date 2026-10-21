## Context

`Topic` es la etiqueta que Ollama extrae de cada `DailySummary` y que el usuario renombra y fusiona en `/topics`. En la web se llama «Temas», y desde `web-ui-themes` (0.20.0) también existe el «Tema» visual (Moderno/Cuaderno) en el menú de usuario. El usuario ha elegido cambiar solo los textos visibles de la web.

## Goals / Non-Goals

**Goals:**
- Que en la web solo «Tema» signifique el tema visual y que los `Topic` se llamen siempre «Categorías» / «categoría».

**Non-Goals:**
- Renombrar rutas (`/topics`, `app_topics*`), la API (`/api/v1/topics`, `top_topic`, tag `Temas`, mensajes `409`), clases PHP, CSS (`topic-*`), JS (`topics.js`) o tablas.
- Cambiar el prompt de Ollama. Ya usa «categoría general» para el `meaning` de la leyenda de emojis, pero es un texto interno que el usuario no ve.
- Cambiar el mensaje de Telegram (`🏷️ …`), que no dice «tema».

## Decisions

- **Solo textos visibles, sin migración.** Es lo que eligió el usuario frente a «textos + URL `/categorias`» y «renombrado completo con migración de tablas». No rompe clientes de la API y el cambio es pequeño. A cambio, el código y la URL siguen diciendo `topic`; Especificaciones.md lo deja escrito como alias explícito: «`Topic` = Categoría en la web».
- **El mensaje de duplicado de la web cambia y el de la API no.** Cada controlador tiene su propio `sprintf`. La API sigue con «tema» para no cambiar su contrato, así que no se tocan OpenAPI ni las colecciones.
- **Concordancia de género.** «Ya existe una categoría llamada "%s". Prueba a fusionarlas en su lugar.», «Ninguna categoría coincide con «…»», «No hay categorías todavía.», «Sin categorías registradas en este rango.», «Confirmar fusión de categorías», «Las categorías fusionadas dejarán de existir.».

## Risks / Trade-offs

- [El código y la URL dicen `topic` y la web dice «Categorías»] → Se documenta la equivalencia en Especificaciones.md y en la spec `topic-management`.
- [Algún texto «tema» se queda sin cambiar] → Antes del finish, `grep -niE '\btemas?\b'` sobre `templates/` y `public/js/`, revisando que cada coincidencia que quede se refiere al tema visual.

## Migration Plan

No hace falta: no hay cambios de esquema ni de configuración. Se despliega con el flujo habitual (pull y caché).
