## Context

- **Interfaz actual**: Twig + `public/css/app.css` (≈400 líneas, tokens en `:root`, modo oscuro automático por `prefers-color-scheme`), sin framework CSS ni build de assets. Iconos en el sprite `templates/_partials/svg_sprite.html.twig`.
- **Navegación actual**: barra lateral en ≥760 px; en móvil, barra superior más barra inferior con 7 destinos.
- **Referencia visual**: lienzo «MyDiary — propuestas visuales», fila «v2». Hay dos artboards interactivos con el selector de tema:
  - `DiarioTemas` (móvil 390 px).
  - `DiarioTemasEscritorio` (escritorio 1280 px, selector dentro del menú del usuario).
- **Datos que Diario ya calcula** (`DiarioDashboardService`): racha actual, mejor racha, semana con tendencia y tema del mes.
- **Restricciones de AGENTS.md**:
  - Sin sobre-ingeniería.
  - Interfaces solo donde ya hay razón.
  - Constantes en un sitio compartido.
  - Logs con campos planos y con prefijo.
  - Nada de registro web de usuarios (el tema es una preferencia, no gestión de usuarios).

## Goals / Non-Goals

**Goals:**
- Dos temas completos (tokens de color, tipografía, radios, sombras) conmutables por el usuario y persistentes.
- Rediseño de la shell (barra lateral, barra superior, menú de usuario, barra inferior de 5 destinos con «Más») y de la vista Diario según los mockups v2.
- Que el resto de vistas sigan funcionando y tomen colores y tipografías del tema activo, sin romperse visualmente.
- Contraste ≥ 4.5:1 en texto y objetivos táctiles ≥ 44 px (reglas de ui-ux-pro-max).

**Non-Goals:**
- Rediseñar Historial, Resúmenes, Búsqueda, Recordatorios, Estadísticas, Temas o Login (cambios futuros, uno por vista).
- Temas definidos por el usuario o editor de colores.
- Exponer el tema en la API `/api/v1`.
- Introducir Tailwind, un bundler o AssetMapper.

## Decisions

Cada decisión indica la opción recomendada.

**Confirmadas por el usuario (2026-10-20):**
- D1: opción A, columna en `app_user`.
- D2: opción A, fuentes autoalojadas.
- D3: opción A, mismo HTML y CSS por tema.
- D5: opción C, se quita el modo oscuro automático.

D4, D6, D7 y D8 siguen la opción recomendada salvo que el usuario diga otra cosa en la revisión.

### D1. Dónde se guarda la preferencia de tema

| Opción | Pros | Contras |
|---|---|---|
| **A (recomendada)**: columna `ui_theme` en `app_user` (string, `NOT NULL DEFAULT 'moderno'`) | El servidor conoce el tema en el primer render: `data-theme` sale en el HTML y no hay parpadeo. Se mantiene entre dispositivos. Encaja con «las entidades Doctrine son el modelo». | Necesita migración y una ruta POST. |
| B: cookie `ui_theme` | Sin migración. Sin parpadeo (se lee en el servidor). | Se pierde al cambiar de navegador o dispositivo. |
| C: `localStorage` + script inline en `<head>` | Cero backend. | Parpadeo posible. El servidor no lo conoce. Contradice la preferencia de la guía por estado persistente. |

### D2. Cómo se cargan las fuentes

| Opción | Pros | Contras |
|---|---|---|
| **A (recomendada)**: autoalojadas en `public/fonts/` (woff2, subconjunto latin, solo los pesos usados) con `@font-face` y `font-display: swap` | Sin peticiones a terceros (privacidad de un diario personal). Funciona igual en local y en `zeus`. Versionadas con el repo. | Unos 300–400 KB de ficheros en el repo. Hay que bajar las licencias OFL. |
| B: Google Fonts por CDN (`<link>` en `base.html.twig`) | Trivial de añadir. | Cada visita avisa a Google. Depende de la red externa. |

Solo se carga la familia del tema activo. Pesos: Plus Jakarta Sans 400/600/700, JetBrains Mono 400/700, Newsreader 400/600 + 400 itálica, Roboto 400/500.

### D3. Cómo difieren los dos temas en el marcado

| Opción | Pros | Contras |
|---|---|---|
| **A (recomendada)**: un solo HTML por vista; las diferencias de disposición (tarjetas frente a línea de tiempo, tira semanal visible solo en Cuaderno) salen del CSS con `:root[data-theme="…"]` | Un único Twig que mantener y testear. Cambiar de tema no exige rerender. | El CSS de componentes crece con reglas por tema. Algunos elementos (tira semanal) se renderizan aunque estén ocultos. |
| B: parciales Twig por tema (`diario/_moderno.html.twig`, `diario/_cuaderno.html.twig`) | Cada tema tiene su marcado ideal. | Se duplica lógica y tests. Cada vista nueva por partida doble. Cambiar de tema exige recargar la página. |

### D4. Dónde vive el catálogo de temas

- **A (recomendada)**: enum `App\Enum\UiTheme` (`Moderno = 'moderno'`, `Cuaderno = 'cuaderno'`, con `default()` y etiqueta e icono para el menú). Es el «sitio compartido genérico» que piden las convenciones, se valida con `tryFrom` y Doctrine lo mapea con `enumType`.
- B: parámetro `app.ui_themes` en `config/services.yaml`. Más flexible, pero sin tipado y sin uso real que lo justifique.

### D5. Modo oscuro

Hoy la app cambia a oscuro sola con `prefers-color-scheme`. Los mockups v2 solo definen los temas claros.

- A: Moderno mantiene una variante oscura automática (tokens basados en el mockup C «Nocturno»). Cuaderno es solo claro, porque es un tema de papel.
- B: los dos temas tienen variante oscura. Más trabajo de diseño y de contraste para Cuaderno.
- **C (elegida)**: eliminar el oscuro automático y dejar solo los dos temas claros.
  - Se borran los bloques `@media (prefers-color-scheme: dark)` y `:root[data-theme="dark"|"light"]` de `app.css`.
  - Se declara `color-scheme: light` para que los controles nativos no se oscurezcan.
  - Un tema oscuro podría volver más adelante como tercer valor de `UiTheme`.

### D6. Mecánica del cambio de tema

- **Recomendada**: formulario `POST /preferencias/tema` (CSRF `ui_theme`, campo `theme`) que guarda la preferencia y redirige a la URL de origen (`Referer` validado como ruta interna, si no a Diario). Funciona sin JS.
- Con JS, el mismo formulario se envía con `fetch` y se cambia `document.documentElement.dataset.theme` al instante (y se carga la familia tipográfica si falta), sin recargar.
- Log `info` en el canal `app`: mensaje `ui.theme_changed`, contexto `{ui_theme: 'cuaderno'}` (campo plano con prefijo, por Kibana).

### D7. Datos nuevos para Diario

- `DiarioDashboardService::getDailyCounts(int $days = 14): list<array{date: string, count: int}>`. Es una consulta agregada por día en Europe/Madrid (reutiliza `DateRange`) e incluye los días con 0 audios.
- La altura de cada barra se calcula en Twig respecto al máximo de la serie. Accesibilidad: `role="img"` con `aria-label` que resume el total y el valor de hoy.
- La tira semanal de Cuaderno usa `DateRange::weekBoundaries` y una consulta de días con audio en la semana (puede derivarse de la misma serie de 14 días).

### D8. Navegación móvil «Más»

- **Recomendada**: «Más» es un `<details>` o un botón que abre un panel inferior (sheet) con Resúmenes, Estadísticas y Temas, con el mismo JS de menús que ya existe en `base.html.twig` (`.menu.is-open`, cierre con Escape y clic fuera).
- Alternativa: una página `/mas` con la lista. Es más simple de hacer pero añade una navegación extra.

## Risks / Trade-offs

- [El resto de vistas usa clases y tokens actuales que el rediseño cambia] → Los tokens se renombran con alias para los nombres actuales (`--panel`, `--accent`…) y se revisa a mano cada vista en los dos temas antes del finish (tarea de verificación visual).
- [Contraste de colores de la librería en texto pequeño] → Se usan los tonos ya ajustados en los mockups (`#0E7490`, `#92400E`, `#4F46E5`, `#B91C1C`) y se comprueban con un verificador de contraste en la tarea de revisión.
- [Peso de las fuentes en móvil] → Subconjunto latin, `woff2`, solo los pesos usados, `preload` solo de la fuente del cuerpo del tema activo.
- [Parpadeo al cambiar de tema con JS] → El cambio aplica `data-theme` de inmediato; la fuente nueva entra con `font-display: swap`.
- [Tests funcionales que buscan textos o clases del Diario actual] → Se actualizan en el mismo cambio. Los escenarios de spec que no cambian (filtro, métricas, generación de resumen) se mantienen verdes.

## Migration Plan

1. Migración Doctrine: `ALTER TABLE app_user ADD ui_theme VARCHAR(20) DEFAULT 'moderno' NOT NULL`. Los usuarios existentes quedan en Moderno.
2. Despliegue normal (`doctrine:migrations:migrate` en `zeus`, como en cambios anteriores). No hay variables de entorno nuevas.
3. Rollback: `doctrine:migrations:migrate prev` elimina la columna. El CSS y las plantillas vuelven con la versión anterior.

## Open Questions

- Confirmar D4, D6, D7 y D8 en la revisión (D1, D2, D3 y D5 ya confirmadas).
- ¿El texto de la navegación debe decir «Diario» o «Hoy»? Los mockups usan ambos; se propone «Diario» en Moderno y en Cuaderno, por coherencia con las rutas.
- ¿Se mantiene el pie de métricas del resumen (tokens, tiempo, modelo) visible, o pasa a un desplegable «Detalles»? Los mockups v2 lo omiten; la spec actual lo exige. Se propone mantenerlo visible y discreto.
