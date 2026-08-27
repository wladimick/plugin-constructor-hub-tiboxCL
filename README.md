# Tibox AI Frontend

Plugin de WordPress para construir y probar páginas frontend livianas diseñadas con IA, manteniendo WordPress como backend.

## Objetivo

Permitir que páginas concretas de `tibox.cl` se rendericen sin depender del template de Hello Elementor ni del runtime de Elementor, sin reemplazar todavía las páginas actuales.

El enfoque es reversible:

1. La URL sigue siendo una **Página normal de WordPress**.
2. Rank Math continúa administrando title, description, canonical, Open Graph y robots.
3. El plugin reemplaza únicamente el template frontend de las páginas marcadas como **Tibox AI Frontend**.
4. `wp_head()`, `wp_body_open()` y `wp_footer()` se mantienen para conservar compatibilidad con SEO, GTM y snippets globales.
5. En modo agresivo se descargan assets conocidos de Elementor, Hello Elementor, Essential Addons, Prime Slider, Swiper, jQuery, Backbone, Marionette y otros componentes que no necesita la plantilla IA.

## MVP incluido

- Plantilla `home-ai` para probar una nueva página de inicio.
- Header liviano propio.
- Compatibilidad con el mega menú actual de WPCode mediante `data-open-tibox-mega-menu`.
- Formulario nativo conectado a `POST /wp-json/tibox/v1/lead`.
- Captura de UTM, `gclid`, `gbraid` y `wbraid`.
- Evento `dataLayer` `form_submit` solo cuando el endpoint confirma `lead_created`.
- Responsive y animaciones sin librerías externas.
- Lazy load de slides secundarios.
- Metabox por página para activar/desactivar el frontend IA.

## Dependencias actuales durante la etapa 1

Mientras migramos WPCode al plugin, deben continuar activos estos snippets:

- GTM: snippets `16005` y `16006` (`GTM-WQVDMTC`).
- Mega menú: `20042`, `20040` y `20044`.
- Endpoint de leads: `19963`.
- Bridge WebOps: `20019` si se quiere mantener la integración de marketing.

Rank Math debe seguir activo.

## Instalación

1. Descargar o clonar el repositorio.
2. Comprimir la carpeta del plugin como ZIP.
3. WordPress → Plugins → Añadir plugin → Subir plugin.
4. Activar **Tibox AI Frontend**.
5. Editar la página de prueba, por ejemplo `/inicio-con-ia/`.
6. En el metabox **Tibox AI Frontend**:
   - activar `Usar frontend liviano Tibox AI`;
   - seleccionar `Inicio IA — MVP`;
   - usar `Agresiva (sin Elementor/jQuery)` para la prueba de performance.
7. Actualizar la página y revisar el frontend.

## SEO durante la prueba

No conviene tener dos páginas indexables con prácticamente el mismo contenido.

Para `/inicio-con-ia/`, mientras sea una prueba:

- mantener title/description representativos;
- configurar **noindex** en Rank Math;
- no apuntar el canonical a `/` mientras la prueba siga siendo una URL independiente.

Cuando el nuevo frontend sustituya oficialmente el home, se puede aplicar el diseño a la página configurada como portada y mantener allí el SEO definitivo de `https://www.tibox.cl/`.

## Qué NO hace todavía

- No desactiva automáticamente snippets de WPCode.
- No migra todavía el endpoint de leads al plugin.
- No migra todavía GTM al plugin para evitar duplicarlo con WPCode.
- No incluye un editor visual.
- No cambia la portada actual ni elimina Elementor de otras páginas.

## Roadmap sugerido

### Fase 1 — Home de prueba

- Validar UI/UX.
- Verificar Rank Math, canonical, OG y robots.
- Verificar GTM/Google Ads.
- Probar formulario real y llegada a WordPress/WebOps.
- Comparar Lighthouse/PageSpeed con el home actual.

### Fase 2 — Consolidación

- Migrar mega menú, GTM y formulario desde WPCode al plugin.
- Añadir panel de configuración global.
- Añadir más plantillas IA: Nosotros, servicios, contacto, etc.
- Añadir controles de assets por plantilla.

### Fase 3 — Sustitución progresiva

- Migrar páginas una a una.
- Mantener redirecciones/canonicals existentes.
- Desactivar Elementor únicamente cuando ya no queden páginas que lo necesiten.
