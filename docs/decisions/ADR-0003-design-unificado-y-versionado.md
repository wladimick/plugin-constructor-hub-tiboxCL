# ADR-0003 — Diseño unificado `hub_design` y versionado en tabla propia

- Estado: Aceptada
- Fecha: 2026-08-31
- Rama: `feat/hub-v0.5-refundacion`
- Sustituye parcialmente a: [ADR-0002](ADR-0002-landings-cpt-native-forms.md)

## Contexto

La auditoría del 2026-08-31 identificó tres problemas estructurales que no eran
bugs sino consecuencias del modelo de datos:

1. El código visual (HTML/CSS/JS) vivía en post meta. Las revisiones de
   WordPress **no cubren post meta**, de modo que "Borrador → Preview →
   Publicar → Nueva versión → Rollback" —requisito explícito del proyecto y
   único seguro real sobre una URL con campaña de Google Ads activa— no existía
   y no podía existir sobre ese modelo. Lo que había, `duplicate_landing()`, no
   es una versión: es una bifurcación con otra URL y otro historial.
2. Header/Footer (`hub_component`) y Landings (`hub_landing`) eran dos post
   types con dos almacenamientos, dos resolvedores de variables y dos
   renderers. Ya habían divergido: `{{MENU_PRIMARY}}` funcionaba solo en
   componentes y `{{HUB_FORM}}` solo en landings, de modo que un diseño generado
   por IA podía ser correcto para un destino e imprimir llaves literales en el
   otro.
3. Cada tipo nuevo (Mega Menu, Hero, Sección, Formulario) habría duplicado el
   problema una vez más.

## Decisión

### Un solo objeto de diseño

Se unifica todo en el CPT `hub_design` con un meta `_hub_type`
(`header`, `footer`, `menu`, `hero`, `section`, `form`, `landing`, `page`).
Un Header, un Hero y una Landing son la misma cosa con distinto **alcance de
render**. Los tipos con `viewable => true` tienen URL propia; el resto devuelve
404 y `noindex` si alguien los solicita directamente.

Añadir un tipo nuevo cuesta una fila en `HUB_Tibox_Design::types()`.

### La identidad en el CPT, el payload en tabla propia

```text
CPT hub_design                    tabla wp_hub_design_versions
├─ título, slug, estado           ├─ id · design_id · version
├─ permalink y SEO                ├─ status: draft | live | archived
├─ autor, papelera, búsqueda      ├─ html · css · js · manifest
└─ meta: tipo, modo, formulario   ├─ asset_dir · entry · checksum
   y `_hub_current_version` ──────┤ author_id · created_at · label
                                  └─ UNIQUE (design_id, version)
```

Criterio general del proyecto:

- **identidad de contenido** (URL, estado, SEO, permisos) → CPT;
- **versiones inmutables, leads y eventos** (muchas filas, consultables,
  comparables, exportables) → tabla propia;
- **configuración escalar por objeto** → post meta;
- **assets** → filesystem, referenciados desde la fila de versión.

Publicar es mover el puntero `_hub_current_version`. Rollback es moverlo atrás.
El historial nunca se reescribe. Guardar dos veces el mismo payload no crea una
versión nueva: se compara por checksum.

### Un solo registro de variables

`HUB_Tibox_Variables` es el único lugar donde se define o se resuelve una
`{{VARIABLE}}`, con versión de contrato y un método `unknown_in()` que permite
al importador devolver un error accionable en lugar de renderizar llaves.

### Assets a archivo, con scope opcional

El CSS y el JS de la versión publicada se compilan a
`uploads/constructor-hub/designs/{design}/{version}/` y se encolan. Se gana
caché de navegador, versionado por URL y compatibilidad con CSP. Si el
filesystem no es escribible, el renderer vuelve a la salida inline.

El aislamiento CSS (`HUB_Tibox_Css_Scoper`) es **opcional por diseño** y está
desactivado en los diseños migrados: se escribieron sin él y algunos estilizan
deliberadamente el markup del theme.

### Capacidades propias

`hub_manage_designs`, `hub_edit_design_code`, `hub_publish_designs`,
`hub_manage_leads`, `hub_export_leads`, `hub_manage_settings`, más el juego de
capacidades del post type. Publicar JavaScript en una URL pública pasa a ser
una concesión deliberada y no un efecto colateral del rol Editor; consultar
leads deja de exigir rol de administrador.

## Alternativas consideradas

### Versionar con revisiones de WordPress

Rechazada: las revisiones no cubren post meta. Guardar el código en
`post_content` para aprovecharlas obligaría a serializar tres lenguajes en un
campo y chocaría con `wp_filter_post_kses`.

### Mantener dos post types y sincronizarlos

Rechazada: duplica importador, renderer, variables y versionado, y la
divergencia ya observada demuestra que la sincronización manual no se sostiene.

### Guardar el código en archivos y no en base de datos

Rechazada como fuente de verdad: rompe la exportación/importación estándar de
WordPress, complica multisitio y deja el historial fuera de los backups de base
de datos. Los archivos se usan como **salida compilada**, no como origen.

## Consecuencias

Positivas:

- publicar es reversible en un clic;
- preview firmado y compartible de una versión sin publicar;
- un solo contrato para la IA, independiente del destino;
- añadir tipos es barato;
- assets cacheables y aislables.

Costos:

- una tabla más que mantener y migrar;
- la migración desde `hub_component`/`hub_landing` debe ser idempotente y
  reversible (`HUB_Tibox_Upgrade`, opción `hub_tibox_designs_unified`);
- los módulos históricos conviven en el repositorio durante la transición.

## Migración

`HUB_Tibox_Upgrade` copia cada `hub_component` y `hub_landing` a un
`hub_design`, convierte el meta en la primera versión publicada, conserva el
slug —de modo que las URLs históricas siguen resolviendo—, traslada el
directorio del package al nuevo id y pasa el objeto histórico a borrador para
no dejar contenido duplicado indexable. **No borra nada**: volver atrás es
poner `hub_tibox_designs_unified` en `0`.

## Referencias

- Auditoría 2026-08-31, hallazgos ALTO-04, ALTO-05, ALTO-06, MED-07, MED-09.
- `docs/ARCHITECTURE.md`, sección Component Registry.
