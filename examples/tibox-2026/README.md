# Tibox 2026 — Header/Footer de referencia

Fuente: export de Claude Design entregado el 2026-08-28 para el prototipo Home Tibox.

Estos archivos son una **adaptación limpia para Constructor HUB Tibox**. No contienen el runtime `Standalone/Bundled Page` de Claude.

## Header

`header/index.html`, `header/style.css`, `header/script.js`.

Cambios respecto del export original:

- `{{CUSTOM_LOGO}}` usado como `src` se reemplaza por `{{CUSTOM_LOGO_URL}}`.
- El mega menú se elimina de esta variante de prueba.
- `Soluciones` enlaza a `{{HOME_URL}}servicios-ti-empresas/`.
- Eventos, Blog, Nosotros y Contacto usan rutas globales de WordPress.
- `Casos` conserva temporalmente `{{HOME_URL}}#casos` hasta definir una URL pública definitiva.
- El JavaScript conserva sticky header y navegación móvil, pero elimina la lógica `hub:megamenu-toggle`.

## Footer

`footer/index.html`, `footer/style.css`, `footer/script.js`.

Cambios respecto del export original:

- `{{CUSTOM_LOGO_URL}}` para la imagen del logo.
- CTA de diagnóstico enlaza a `{{HOME_URL}}contacto/`.
- Se mantiene la estructura visual propuesta por Claude.

## Cómo cargar en WordPress

1. Constructor HUB → Componentes HUB → Nuevo componente.
2. Crear `Header Tibox 2026 — Claude` con responsabilidad Header.
3. Copiar `header/index.html` en HTML, `header/style.css` en CSS y `header/script.js` en JavaScript.
4. Publicar.
5. Repetir para `Footer Tibox 2026 — Claude`.
6. Constructor HUB → Configuración.
7. Seleccionar ambos componentes.
8. Activar modo híbrido únicamente para `/inicio-con-ia/` durante QA.

## Nota de arquitectura

El renderer híbrido v0.3-dev requiere Header y Footer HUB a la vez. El reemplazo de una sola región dejando la otra entregada por el theme/Elementor requiere un adaptador Legacy específico y se implementará por separado; no se simula omitiendo una región del shell.
