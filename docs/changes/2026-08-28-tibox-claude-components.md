# 2026-08-28 — Adaptación Header/Footer Claude para Tibox

Rama: `feat/hybrid-header-footer`
PR: `#2`
Versión de prueba: `0.3.0-beta.1`

## Objetivo

Tomar el ZIP `TIBOX Home Prototype Built.zip` exportado desde Claude Design y convertir su Header/Footer en componentes limpios compatibles con Constructor HUB, sin usar el runtime Standalone/Bundled Page.

## Fuente recibida

El ZIP contiene, entre otros:

- `header.html`
- `header.css`
- `header.js`
- `footer.html`
- `footer.css`
- `footer.js`
- `Home TIBOX - Standalone.html`
- archivos de páginas completas y assets.

Los archivos Standalone se consideran referencia visual/export portable, no código para pegar directamente en Constructor HUB.

## Cambios y commits

### `d9b35b48e324695f950ffd4024a822f323743f86`

Primer ajuste del Component Manager:

- añade variable `{{CUSTOM_LOGO_URL}}` además de `{{CUSTOM_LOGO}}`;
- corrige `{{SITE_URL}}` para funcionar correctamente con rutas `{{SITE_URL}}/ruta/`;
- exploró activación parcial Header/Footer.

### `c7a75a0efa3cfd9741f28fb1caf7d67c37f61044`

- hizo el template tolerante a una región inactiva;
- esta aproximación no resolvía el problema de conservar el Header/Footer Legacy del theme, solo omitía la región.

### `1cde0bfde9d350ac4fc3cf9fa07876d6119e28ee`

Corrección arquitectónica:

- el renderer híbrido vuelve a exigir Header + Footer HUB completos;
- se documenta que sustituir una sola región y conservar la otra del theme requiere un adaptador Legacy específico;
- se mantiene `{{CUSTOM_LOGO_URL}}`.

Esta decisión evita vender como “Legacy” una región que en realidad quedaría ausente del shell.

### `d03dbc5e34c5df04f224f7a08aea5af95f23dd3e`

- añade `examples/tibox-2026/header/index.html` limpio;
- elimina el mega menú del Header de prueba;
- `Soluciones` pasa a enlace directo a Servicios TI;
- rutas globales para Eventos, Blog, Nosotros y Contacto;
- logo mediante `{{CUSTOM_LOGO_URL}}`.

### `edc21c089344fae13539d61050c4c22146122919`

- añade CSS del Header Tibox 2026.

### `9f3b4c25aa0d4798957194afae13295b0504c7bf`

- añade JS del Header;
- conserva sticky header y navegación móvil;
- elimina lógica/evento de Mega Menu.

### `8fffb7672ec26e689481b8224caa7aef43da95e2`

- añade Footer Tibox 2026 limpio;
- logo con `{{CUSTOM_LOGO_URL}}`;
- CTA de diagnóstico enlaza a Contacto global.

### `65b5f5d632525ec8a4a2230e084f1d34888b20a4`

- añade CSS del Footer Tibox 2026.

### `e9134032542dbe8da64a209d17e52b8b69da1f18`

- añade JS del Footer para evento de preferencias de cookies.

### `261055b19aa3fd84201513c99089718da5c1e72d`

- documenta los ejemplos, origen y procedimiento de carga manual.

### `54bd4a2346f3a97ab582575e9c20bd9efc3f3b4b`

- identifica la build de QA como `Constructor HUB Tibox 0.3.0-beta.1`.

## Comportamiento nuevo

La prueba prevista para Tibox es:

```text
/inicio-con-ia/

Header  -> HUB / Tibox 2026 Claude
Main    -> the_content() / contenido WordPress-Elementor existente
Footer  -> HUB / Tibox 2026 Claude
```

El Mega Menu queda fuera de este QA.

## Compatibilidad

- no cambia el theme;
- no desactiva Elementor;
- no hace dequeue de assets Elementor en modo híbrido;
- conserva `wp_head()`, `wp_body_open()` y `wp_footer()`;
- requiere que el sitio tenga un Custom Logo de WordPress para que `{{CUSTOM_LOGO_URL}}` tenga valor; si no existe debe cargarse/configurarse antes del QA o reemplazarse temporalmente por una URL de medios.

## QA pendiente

1. Instalar `0.3.0-beta.1` en Tibox.
2. Crear y publicar Header/Footer desde `examples/tibox-2026/`.
3. Aplicar solo a `/inicio-con-ia/`.
4. Validar logo, enlaces y responsive.
5. Validar contenido Elementor central.
6. Verificar Rank Math, GTM/dataLayer y formularios globales.
7. Confirmar que no existe Header/Footer duplicado.
8. Desactivar modo híbrido y confirmar rollback inmediato.

## Pendiente posterior

Diseñar adaptador Legacy que permita reemplazar solo Header o solo Footer sin perder la otra región del theme/Elementor.
