# 2026-09-15 — Content Schema editable para páginas IA

Rama: `feat/hub-content-schema`  
Base: `feat/page-assignment`  
Estado: **implementación inicial / QA WordPress real pendiente**.

## Objetivo

Separar contenido editorial de presentación para que una página generada con IA
pueda cambiar de versión visual sin perder textos, links e imágenes editados
desde WordPress.

## Comportamiento anterior

Los Design Packages versionaban HTML/CSS/JS. El contenido que la IA escribía
dentro del HTML quedaba acoplado al código: cambiar copy o fotografía implicaba
editar el diseño, y una iteración visual nueva debía volver a incorporar esos
valores manualmente.

## Comportamiento nuevo

Un package puede declarar `content_schema` en `manifest.json` y usar
`{{CONTENT.*}}` dentro de `index.html`.

Constructor HUB:

- valida el schema al importar;
- rechaza placeholders no declarados;
- admite `text`, `textarea`, `url`, `media`, `number`, `boolean` y `select`;
- crea campos editoriales en el `hub_design`;
- crea campos editoriales en una Page que tenga ese diseño asignado;
- guarda valores fuera de `wp_hub_design_versions`;
- conserva esos valores al publicar o hacer rollback visual;
- usa Media Library para imágenes;
- permite `.url`, `.alt` e `.id` en campos `media`;
- mantiene la precedencia `schema default < diseño < Page`.

Los valores viven en `_hub_content_values`, agrupados por `design_id`, para no
mezclar el contenido de diseños distintos en un mismo host.

## Archivos principales

- `includes/class-hub-content.php`
- `includes/class-hub-variables.php`
- `includes/class-hub-package.php`
- `includes/class-hub-plugin.php`
- `tibox-ai-frontend.php`
- `assets/js/content-fields-admin.js`
- `tests/test-content-schema.php`
- `docs/decisions/ADR-0006-content-schema-editable.md`
- `docs/AI-PACKAGE-CONTRACT.md`
- `examples/hub-package-page-editable/`

## Compatibilidad

- `content_schema` es opcional; packages existentes siguen siendo válidos.
- El contrato sigue en `hub_package: 1` porque la ampliación es aditiva.
- No se añade ACF ni Elementor como dependencia.
- El renderer fijo de variables sigue resolviendo `SITE_*`, `PAGE_*`, menús y
  formularios como antes.
- `CONTENT.*` se resuelve antes del registro fijo y solo cuando el diseño lo usa.
- Una Page asignada a HUB se convierte en host de contenido solo si el diseño
  asignado coincide con el que se está renderizando; Header/Footer/componentes
  no heredan accidentalmente el contenido de la Page.

## Seguridad

- No existe tipo editorial `html`.
- El schema limita rutas y máximo de campos.
- Los valores se sanitizan según tipo.
- Las salidas se escapan según tipo.
- El media picker guarda attachment IDs.
- Editar contenido de una Page requiere permiso para editar esa Page; editar el
  diseño conserva las capacidades HUB.

## QA realizado

Se añadió cobertura unitaria para:

- normalización de schema válido;
- rutas inválidas;
- rechazo de tipo `html`;
- obligación de `options` para `select`;
- extracción de placeholders;
- sufijos `.url`, `.alt`, `.id` de media;
- detección de referencias no declaradas.

El QA de integración en WordPress real sigue pendiente y forma parte de la
Fase 7: import ZIP, Media Library, Page Assignment, preview, publicación,
rollback, Elementor, Rank Math/GTM y persistencia entre versiones.

## Siguiente paso

1. Ejecutar CI completo sobre la rama.
2. Corregir cualquier hallazgo estático/unitario.
3. Probar el example package en una copia WordPress.
4. Añadir Publish Guard para required fields, SEO, JS externo y accesibilidad.
5. Preparar una RC instalable solo después de QA real.
