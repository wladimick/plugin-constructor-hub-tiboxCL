# Contrato de Design Packages para IA

Fecha base: 2026-08-31. Contrato `hub_package: 1`.

Este es el documento que hay que pasarle a Claude Design, ChatGPT o cualquier
otra IA que vaya a producir un componente para Constructor HUB. Todo lo que el
importador acepta o rechaza está aquí.

## Estructura del ZIP

```text
package.zip
├── manifest.json      obligatorio
├── index.html         obligatorio
├── style.css          opcional
├── script.js          opcional
└── assets/            opcional
```

## manifest.json

```json
{
  "hub_package": 1,
  "type": "hero",
  "name": "Hero servicios TI",
  "slug": "hero-servicios-ti",
  "version": "1.0.0",
  "site": "generic",
  "entry": "index.html",
  "scope": "hub-scope-hero-servicios-ti",
  "description": "Hero de campaña con formulario lateral.",
  "variables": ["SITE_NAME", "PAGE_TITLE", "HUB_FORM"],
  "tokens": ["--hub-primary", "--hub-accent", "--hub-container"]
}
```

| Campo | Obligatorio | Qué hace |
| --- | --- | --- |
| `hub_package` | sí | Versión del contrato. Un valor mayor al que entiende el sitio se rechaza con un mensaje que pide actualizar el plugin. |
| `type` | sí | `header`, `footer`, `menu`, `hero`, `section`, `form`, `landing` o `page`. Determina dónde se puede usar y si tiene URL propia. |
| `name` | sí | Título del diseño en el admin. |
| `slug` | no | Referencia para `[hub_design slug="…"]`. Se deriva de `name` si falta. |
| `version` | no | Versión del diseño, informativa. El historial real lo lleva Constructor HUB. |
| `site` | no | `generic` salvo que el package sea deliberadamente específico de un cliente. |
| `entry` | no | HTML principal. Por defecto `index.html`. |
| `scope` | no | Si viene, el CSS se aísla automáticamente bajo esa clase. |
| `variables` | no | Variables que el package usa. **Se validan**: una variable que este sitio no conoce aborta la importación. |
| `tokens` | no | Tokens del Design System usados. Documental. |
| `mode` | no | `hub`, `standalone`, `package` o `legacy`. Solo aplica a `landing` y `page`. |

## Variables disponibles

Se escriben `{{NOMBRE}}` en el HTML.

**Sitio:** `SITE_URL`, `HOME_URL`, `SITE_NAME`, `SITE_DESCRIPTION`,
`CURRENT_YEAR`, `CUSTOM_LOGO`, `CUSTOM_LOGO_URL`, `PRIVACY_URL`.

**Navegación:** `MENU_PRIMARY`, `MENU_FOOTER`, `MENU_SECONDARY`.

**Contexto de la página:** `PAGE_ID`, `PAGE_TITLE`, `PAGE_URL`, `PAGE_EXCERPT`,
`FEATURED_IMAGE`.

**Diseño:** `DESIGN_ID`, `DESIGN_TITLE`, `DESIGN_URL`, `DESIGN_SCOPE`.

**Formulario:** `HUB_FORM`, `FORM_ENDPOINT`.

La lista viva está en el propio admin, en la caja *Contrato para IA* de
cualquier diseño, generada desde el registro real. Si el HTML usa una variable
que no está registrada, **la importación falla** con el nombre exacto: es
preferible a publicar una página que muestra `{{ALGO}}` a un visitante.

## Tokens del Design System

Un componente portable no contiene un solo color literal:

```css
/* correcto */
.hero { background: var(--hub-primary); }

/* incorrecto: deja de funcionar en cuanto se instala en otro cliente */
.hero { background: #0f172a; }
```

Tokens disponibles: `--hub-primary`, `--hub-secondary`, `--hub-accent`,
`--hub-text`, `--hub-muted`, `--hub-background`, `--hub-surface`, `--hub-border`,
`--hub-font-heading`, `--hub-font-body`, `--hub-font-mono`, `--hub-text-base`,
`--hub-line-height`, `--hub-container`, `--hub-gutter`, `--hub-section-space`,
`--hub-radius-sm`, `--hub-radius-md`, `--hub-radius-lg`, `--hub-shadow`.

Usa siempre un valor de reserva: `var(--hub-primary, #1f2937)`.

## Qué está prohibido

El importador rechaza el package si encuentra:

- archivos con una extensión fuera de la lista permitida (HTML, CSS, JS, JSON,
  imágenes, fuentes y texto). **PHP nunca se acepta**;
- rutas con `..`, rutas absolutas o enlaces simbólicos;
- `.htaccess`, `.user.ini`, `php.ini`, `web.config`;
- más de 600 archivos, más de 25 MB comprimidos, más de 120 MB descomprimidos o
  más de 20 MB por archivo;
- contenido que al descomprimirse supera el tamaño que el propio ZIP declara.

Además, todo SVG se sanea al importar: se eliminan `<script>`, `<foreignObject>`,
los manejadores `on*` y las URLs `javascript:`.

Por convención, tampoco incluyas:

- claves, tokens ni credenciales de ningún servicio;
- librerías externas sin justificación: prefiere HTML, CSS y JavaScript nativos;
- URLs escritas a mano cuando existe una variable HUB;
- estilos globales sin `scope`, salvo que el package sea parte explícita del
  Design System.

## Qué se espera de la calidad

- HTML semántico y accesible; un solo `<h1>` por página.
- Foco visible en todo lo interactivo.
- `prefers-reduced-motion` respetado.
- Responsive con unidades relativas; nada que provoque scroll horizontal.
- Imágenes con `loading="lazy"`, salvo el recurso LCP.
- IDs únicos: un componente puede insertarse dos veces en la misma página.

## Formularios

Para incluir el formulario estándar, basta `{{HUB_FORM}}`.

Para un formulario propio, el contrato está en
[`FORMS-AND-TRACKING.md`](FORMS-AND-TRACKING.md): atributo
`data-hub-landing-form`, campos `email` y `privacy` obligatorios, honeypot
`website` oculto y un `[data-hub-form-status]` para los mensajes. El resto
—token anti spam, `submission_id`, UTMs y click IDs— lo añade Constructor HUB.

## Ciclo de vida

```text
ZIP → validación → versión BORRADOR → preview firmado → publicar → (rollback)
```

Importar **nunca** reemplaza lo que está en producción. El package entra como
versión borrador y hay que publicarlo explícitamente. Cada versión anterior
queda archivada y se restaura en un clic.

Un diseño publicado puede exportarse de vuelta a ZIP desde la caja *Versiones*,
lo que permite llevarlo a otro sitio o devolvérselo a una IA para iterar.

## Ejemplo completo

[`examples/hub-package-hero/`](../examples/hub-package-hero/) contiene un
package mínimo válido, con instrucciones para empaquetarlo y probarlo.
