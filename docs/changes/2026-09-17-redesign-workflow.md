# Redesign Workflow — 2026-09-17

## Objetivo

Permitir que una Page ya construida con Constructor HUB reciba una nueva versión visual generada por IA sin perder:

- Page ID y permalink;
- metadatos SEO de WordPress;
- contenido Elementor/Theme existente;
- diseño HUB asignado;
- valores `CONTENT.*` editados en WordPress;
- versión live anterior mientras se revisa el rediseño.

## Nuevo flujo

`Constructor HUB -> Actualizar página`

1. seleccionar una Page que ya tenga un diseño HUB asignado;
2. subir un nuevo ZIP generado por Claude Design, ChatGPT u otra IA;
3. ejecutar preflight sin crear todavía una versión;
4. importar el package sobre el **mismo `hub_design`**;
5. comparar `content_schema` entrante con el schema live/working anterior;
6. dejar la nueva versión en draft o publicarla explícitamente;
7. mantener el renderer actual salvo activación explícita desde Theme/Elementor.

## Regla de identidad

Un rediseño nunca crea un segundo diseño si la Page ya tiene uno asignado.

Los valores editoriales están almacenados usando el `design_id`. Reutilizar ese mismo ID hace que una nueva versión visual conserve automáticamente los textos, URLs, imágenes y otros campos editados en WordPress.

## Preflight

Antes de llamar al importador que crea una versión, el flujo revisa:

- seguridad y estructura del ZIP mediante el importador seguro existente;
- presencia y validez de `manifest.json`;
- contrato HUB;
- `type: page`;
- entry HTML válido;
- variables HUB desconocidas;
- referencias `CONTENT.*` no declaradas.

Un preflight fallido no modifica el diseño ni crea una nueva versión.

## Compatibilidad de content_schema

El rediseño calcula cuatro grupos:

- campos agregados;
- campos retirados;
- cambios de tipo;
- campos obligatorios nuevos sin default.

Los valores de campos retirados no se borran. Quedan almacenados y simplemente dejan de participar mientras el schema no los declare.

### Cambios que fuerzan revisión manual

La publicación automática se bloquea cuando existe:

- cambio de tipo de un campo existente;
- campo nuevo `required` sin `default`.

El ZIP sí se guarda como nueva versión draft para permitir preview y corrección.

## Renderer seguro

- Page actualmente HUB + rediseño draft: sigue HUB con la versión live anterior.
- Page actualmente Theme/Elementor + rediseño draft: sigue Theme/Elementor.
- Theme/Elementor solo cambia a HUB si se pidió activación explícita y la nueva versión consiguió publicarse.
- Si Publish Guard bloquea la nueva versión, la versión live anterior queda intacta.

## QA requerido en staging

1. importar un rediseño compatible de una Page HUB live;
2. confirmar que la URL continúa sirviendo la versión anterior hasta publicar;
3. verificar que valores `CONTENT.*` sobreviven al rediseño;
4. probar cambio de tipo en `content_schema` y confirmar que no publica automáticamente;
5. probar un campo required nuevo sin default;
6. probar Theme/Elementor -> preview HUB -> publicar -> activar HUB;
7. confirmar Rank Math/canonical/schema/OG y Page ID sin cambios;
8. confirmar que volver a Theme no elimina versiones ni contenido HUB.
