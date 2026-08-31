# Constructor HUB Tibox

Constructor frontend progresivo para WordPress desarrollado por Tibox.

Su objetivo es permitir que sitios WordPress existentes migren gradualmente desde constructores visuales como Elementor hacia una capa frontend propia, liviana y generable con IA, **sin perder WordPress como backend** y sin exigir un cambio inmediato de theme.

Repositorio: `wladimick/plugin-constructor-hub-tiboxCL`

> **Estado:** `0.5.0-dev` en la rama `feat/hub-v0.5-refundacion`.
> La auditoría del 2026-08-31 derivó en una refundación del modelo de datos.
> El código pasa PHPCS, PHPStan nivel 5 y los tests, pero **todavía no se ha
> ejecutado en un WordPress real**: la Fase 7 del roadmap es QA y bloquea
> cualquier release estable.

## Leer primero

Toda persona o IA que vaya a trabajar en este repositorio debe comenzar por:

1. [`docs/START-HERE-AI.md`](docs/START-HERE-AI.md)
2. [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
3. [`docs/decisions/`](docs/decisions/) — ADR-0003 y ADR-0004 cambiaron el modelo
4. [`docs/DEVELOPMENT-PROTOCOL.md`](docs/DEVELOPMENT-PROTOCOL.md)
5. [`docs/CHANGELOG.md`](docs/CHANGELOG.md)
6. [`docs/ROADMAP.md`](docs/ROADMAP.md)

Para generar componentes con IA:
[`docs/AI-PACKAGE-CONTRACT.md`](docs/AI-PACKAGE-CONTRACT.md).
Para formularios y tracking:
[`docs/FORMS-AND-TRACKING.md`](docs/FORMS-AND-TRACKING.md).

La documentación es parte del producto. Un cambio de código que altere comportamiento, arquitectura, compatibilidad o flujo de trabajo debe quedar documentado.

## Problema que resuelve

Tibox y Prodata utilizan WordPress + Elementor. No es conveniente reemplazar todo el frontend de una sola vez ni cambiar inmediatamente el theme existente.

Constructor HUB permite una transición por capas:

```text
WordPress backend
├── contenido / medios / usuarios
├── SEO / Rank Math
├── analítica / GTM
└── Constructor HUB Tibox
    ├── Diseños (hub_design)
    │   ├── Header · Footer · Mega Menu
    │   ├── Hero · Sección · Formulario
    │   └── Landing · Página
    ├── Versiones: borrador → preview → publicar → rollback
    ├── Design System: tokens --hub-* por sitio
    ├── Inserción: shortcode · bloque · widget · función
    ├── Packages: manifest.json validado, ZIP de IA
    ├── Formularios y leads con privacidad y conversiones
    └── Mapa de migración y retirada de Elementor
```

## Modos objetivo

### Región

Header y Footer se migran **por separado**. En modo `inject` se conserva la
plantilla del theme y el diseño HUB se coloca con los hooks de WordPress; en
modo `replace`, Constructor HUB entrega el documento completo.

### Fragmento

Un componente HUB insertado dentro de una página que sigue siendo de Elementor,
con `[hub_design slug="..."]`, el bloque, el widget o
`constructor_hub_render()`. Es lo que permite reemplazar un hero sin reconstruir
la página.

### Documento

Landings y páginas con URL propia, renderizadas como fragmento en el shell HUB,
como documento HTML completo generado por IA, o como package ZIP servido desde
su carpeta.

En los tres casos se conservan `wp_head()`, `wp_body_open()` y `wp_footer()`.

El **HUB Theme** existe en [`theme/hub-theme/`](theme/) y es **opcional**: no se
empaqueta con el plugin.

## Sitios iniciales

- `tibox.cl`: transición desde WordPress + Hello Elementor + Elementor.
- `prodata.cl`: transición desde WordPress + Elementor sin cambiar inicialmente su theme.

El núcleo debe ser reutilizable. Los comportamientos específicos de cada sitio deben resolverse mediante configuración/adaptadores y nunca mezclarse con el core genérico.

## Relación con Cloud-tibox

`wladimick/Cloud-tibox` es el antecedente directo de varias ideas:

- WordPress como backend;
- HTML/CSS/JS generado con IA;
- Design Packages;
- variables dinámicas `{{...}}`;
- preview y rollback;
- separación entre contenido y presentación.

La diferencia es que Cloud-tibox nació con un theme propio. Constructor HUB debe poder instalarse primero **sin cambiar el theme existente**.

Ver [`docs/CLOUD-TIBOX-RELATION.md`](docs/CLOUD-TIBOX-RELATION.md).

## Estado de desarrollo

### `main`

Baseline documentada v0.2.0.

### `feat/hybrid-header-footer`

Beta v0.3.x con Componentes HUB y renderer híbrido Header/Footer + contenido Elementor.

### `feat/landings-module`

Versión de trabajo `0.4.0-dev`, apilada sobre la rama anterior.

Añade:

- `Constructor HUB → Landings`;
- páginas de campaña completas HTML/CSS/JS;
- renderer independiente del template del theme;
- modo canvas o Header/Footer HUB;
- variables `{{SITE_*}}`, `{{LANDING_*}}`, logo y `{{HUB_FORM}}`;
- formulario nativo sin WPForms/Elementor;
- endpoint REST genérico;
- `Constructor HUB → Envíos Landings`;
- almacenamiento de leads;
- notificación `wp_mail`;
- tracking UTM/GCLID/GBRAID/WBRAID;
- `dataLayer` `form_submit`;
- honeypot, rate limit e idempotencia;
- hook para bridges externos.

Ver [`docs/changes/2026-08-31-landings-module.md`](docs/changes/2026-08-31-landings-module.md).

## Contrato rápido para una Landing IA

La IA entrega tres piezas:

```text
index.html
style.css
script.js
```

No debe incluir `<!doctype>`, `<html>`, `<head>`, `<body>`, `<style>` o `<script>` dentro del campo HTML.

Variables disponibles inicialmente:

```text
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}
{{CURRENT_YEAR}}
{{CUSTOM_LOGO}}
{{CUSTOM_LOGO_URL}}
{{LANDING_URL}}
{{LANDING_TITLE}}
{{HUB_FORM}}
```

`{{HUB_FORM}}` inserta el formulario nativo. También se acepta un formulario IA personalizado con `data-hub-landing-form`.

## Principios no negociables

- WordPress sigue siendo backend.
- La transición debe ser reversible.
- No romper páginas Elementor existentes.
- No obligar a cambiar el theme actual.
- HTML semántico, CSS nativo y JavaScript nativo por defecto.
- IA nunca debe recibir ni generar secretos/API keys.
- Los assets deben cargar solo donde corresponden.
- SEO y analítica se deben conservar durante la migración.
- Cada cambio importante debe quedar registrado con fecha, rama y commit.
- Una IA nueva debe poder comprender el proyecto leyendo `/docs` sin depender de conversaciones anteriores.

## Flujo de desarrollo

No desarrollar directamente en `main`.

```text
main
└── feat/... | fix/... | refactor/... | docs/...
      ├── implementación
      ├── documentación
      ├── QA
      └── Pull Request
```

Las features apiladas deben indicar claramente su rama base y no fusionarse fuera de orden.

## Roadmap resumido

1. Fundación/documentación.
2. Header/Footer HUB.
3. Design System y biblioteca de componentes.
4. Design Packages para Claude/ChatGPT.
5. Landings HUB.
6. modos Legacy/Híbrido/HUB para Pages estándar.
7. adaptadores e integraciones.
8. eliminación selectiva de assets Elementor.
9. HUB Tibox Theme opcional.
10. operación multi-sitio/release.

Ver [`docs/ROADMAP.md`](docs/ROADMAP.md) para el detalle.
