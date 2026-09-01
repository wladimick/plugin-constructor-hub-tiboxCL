# ADR-0004 — Insertion API y regiones independientes

- Estado: Aceptada
- Fecha: 2026-08-31
- Rama: `feat/hub-v0.5-refundacion`
- Depende de: [ADR-0003](ADR-0003-design-unificado-y-versionado.md)

## Contexto

El objetivo del proyecto es reemplazar progresivamente Header, Footer, Mega
Menu, Hero, secciones, formularios y páginas **sin romper las páginas antiguas**.
La auditoría del 2026-08-31 constató que el código no ofrecía ningún camino para
eso:

- no existía forma de insertar un componente HUB dentro de una página que sigue
  siendo de Elementor: ni shortcode, ni bloque, ni widget, ni función de
  plantilla. Los componentes solo se renderizaban sustituyendo la plantilla
  completa;
- el modo híbrido exigía Header **y** Footer HUB publicados a la vez
  (`hybrid_is_configured()`), de modo que no se podía migrar solo el header, que
  es exactamente el primer paso del roadmap.

En la práctica la única migración posible era "página completa o nada".

## Decisión

### Cuatro entradas, un solo renderer

```text
[hub_design slug="..."]              → contenido clásico, Elementor, WPBakery
Bloque "Diseño HUB"                  → editor de bloques (render en PHP)
Widget "Diseño HUB"                  → Elementor, dentro del adaptador
constructor_hub_render('slug')       → plantillas de theme y HUB Theme futuro
```

Las cuatro resuelven a `HUB_Tibox_Render::render()`, de modo que aislamiento,
variables, assets, preview y versión publicada se comportan igual en todas.

El bloque se renderiza en servidor a propósito: un bloque con markup guardado
divergiría del shortcode en cuanto se publique una versión nueva del componente.

Una referencia inexistente no imprime nada para un visitante y sí un aviso para
quien puede administrar diseños. Un shortcode roto visible en una landing de
campaña es peor que un hueco.

### Región por región, y dos formas de tomarla

Cada región (`header`, `footer`) se configura por separado con tres modos:

- **`theme`**: sin cambios.
- **`inject`**: se conserva la plantilla del theme y el diseño HUB se coloca por
  `wp_body_open` / `wp_footer`; opcionalmente se oculta la región del theme con
  un selector CSS **configurado**, nunca adivinado.
- **`replace`**: Constructor HUB entrega el documento completo
  (`templates/hub-shell.php`).

`inject` es el modo que hace viable la migración parcial sobre un theme
desconocido; `replace` es el destino cuando ambas regiones ya son HUB.

El alcance de cada región es `all`, `selected` o `except`, con lista de IDs.

### El Design System es la unidad de portabilidad

Un componente correcto referencia `var(--hub-primary)`, no `#0f172a`. Los tokens
`--hub-*` viven en una opción por sitio, se imprimen una sola vez en `wp_head`
y son exportables e importables en JSON. Llevar un componente de tibox.cl a
prodata.cl debe ser cambiar tokens y contenido, no editar el core.

Los valores por defecto son deliberadamente neutros: una instalación nueva no
debe parecerse a ningún cliente.

## Alternativas consideradas

### Ocultar la región del theme automáticamente

Rechazada. No existe forma fiable de identificar el header de un theme
arbitrario. Adivinar por nombre de clase es lo que hacía `strip_heavy_assets()`
del MVP, y es cómo una página pierde el slider sin que nadie se entere.

### Reemplazar siempre el documento completo

Rechazada como modo único: obliga a tener Header y Footer HUB antes de poder
migrar nada, que es el problema que este ADR resuelve.

### Bloque con markup guardado en el post

Rechazada: publicar una versión nueva del componente no actualizaría las páginas
donde está insertado, que es justamente el valor de tener componentes.

## Consecuencias

Positivas:

- se puede sustituir un hero dentro de una página Elementor sin tocar el resto;
- se puede migrar solo el header;
- publicar una versión actualiza todas las inserciones a la vez;
- los componentes se vuelven portables entre sitios.

Costos:

- el modo `inject` necesita un selector CSS por theme, que alguien debe
  determinar y mantener;
- con Elementor Pro y Theme Builder activo puede haber dos headers: el adaptador
  lo detecta y avisa, pero no lo resuelve solo;
- un componente insertado dos veces en la misma página duplica IDs de elemento.
  `HUB_Tibox_Render::render_count()` lo registra para el mapa de migración.

## Referencias

- Auditoría 2026-08-31, hallazgos ALTO-04 y ALTO-05.
- `docs/SITE-ADAPTERS.md`, regla de portabilidad.
