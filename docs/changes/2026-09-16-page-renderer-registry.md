# Registro de Pages y renderers

Fecha: 2026-09-16

## Objetivo

Dar una vista operativa única de las Pages WordPress y del frontend que está sirviendo cada URL durante la convivencia entre Theme/Elementor y Constructor HUB.

## Pantalla

Nuevo menú:

`Constructor HUB -> Páginas del sitio`

La tabla muestra:

- Page y permalink público cuando corresponde;
- estado WordPress;
- renderer efectivo (`Theme / Elementor` o `Constructor HUB`);
- diseño HUB asignado;
- versión live del diseño;
- acciones de edición y preview.

## Fail-safe

La pantalla distingue entre el modo guardado y el renderer efectivo. Si una Page está configurada en HUB pero el diseño dejó de cumplir las condiciones mínimas, se muestra `Theme / Elementor` y la etiqueta `fail-safe activo`.

HUB solo se puede activar desde esta pantalla cuando existen simultáneamente:

1. un diseño asignado;
2. el post `hub_design` publicado;
3. una versión live.

La acción `Volver a Theme` cambia únicamente el renderer. No elimina la asignación, las versiones ni el contenido editorial HUB, por lo que funciona como rollback operativo inmediato.

## Permisos

- La pantalla requiere permiso para administrar diseños HUB.
- Cambiar el renderer público requiere `hub_publish_designs` o `manage_options`.
- Los cambios de renderer usan una URL firmada con nonce y vuelven al registro con un notice de resultado.

## QA pendiente en WordPress real

Validar en staging:

- Page Elementor sin HUB asignado;
- Page asignada pero todavía en Theme/Elementor;
- Page HUB activa;
- diseño asignado sin versión live para verificar fail-safe;
- switch HUB -> Theme -> HUB conservando permalink, SEO, Elementor y contenido HUB;
- preview de una versión working mientras Theme/Elementor sigue público.
