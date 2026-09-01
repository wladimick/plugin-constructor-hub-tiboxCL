# Roadmap — Constructor HUB Tibox

Base: 2026-08-28. Reescrito: 2026-08-31 tras la auditoría integral y la
implementación de las fases 0 a 6 en `feat/hub-v0.5-refundacion`.

El roadmap prioriza migración segura de sitios existentes antes que reemplazo
total.

## Estado general

| Fase | Alcance | Estado |
| --- | --- | --- |
| 0 | Bloqueadores de producción | **Implementada** |
| 1 | Core sólido: diseño unificado y versionado | **Implementada** |
| 2 | Componentes, Insertion API y Design System | **Implementada** |
| 3 | Landings, formularios y leads | **Implementada** |
| 4 | Design Packages e IA | **Implementada** |
| 5 | Migración de Elementor y WPCode | **Implementada** |
| 6 | HUB Theme opcional y multi-sitio | **Implementada** |
| 7 | QA en WordPress real | **Pendiente — bloquea la release** |

Ninguna fase debe considerarse cerrada hasta la Fase 7. Todo lo implementado
pasó `php -l`, PHPCS con reglas de seguridad, PHPStan nivel 5 y el arnés de
tests propio, pero **no se ha ejecutado en un WordPress real**.

## Fase 0 — Bloqueadores

- [x] Validación de origen que no rechace envíos legítimos (CRIT-01).
- [x] Límite de envíos con IP real detrás de proxy (CRIT-02).
- [x] No descartar el diseño en silencio al guardar (CRIT-03).
- [x] Endpoint del formulario del MVP servido por el plugin (CRIT-04).
- [x] `post_password_required()` en los modos de documento completo (ALTO-01).
- [x] Límite real de descompresión del ZIP (ALTO-02).
- [x] Saneado de SVG en la importación (ALTO-03).
- [x] Correos de empleados fuera del core (ALTO-07).
- [x] `Reply-To` sin nombre interpolado (ALTO-10).
- [x] Colisión `ARCHITECTURE.md` / `architecture.md` resuelta (MED-10).
- [x] Hooks de activación y desactivación.

## Fase 1 — Core sólido

- [x] CPT unificado `hub_design` con tipos.
- [x] Tabla `wp_hub_design_versions`: draft / live / archived.
- [x] Publicar, rollback y preview firmado.
- [x] Registro único de variables con validación.
- [x] Compilador de assets con aislamiento CSS opcional.
- [x] Regiones Header y Footer independientes.
- [x] Capacidades propias y menú de primer nivel.
- [x] Migración idempotente y reversible desde los post types históricos.
- [x] `uninstall.php` no destructivo.
- [x] i18n, WPCS, PHPStan y tests en CI.
- [ ] Normalizar el nombre del archivo bootstrap y las constantes heredadas.
      Requiere una estrategia de actualización que no instale una segunda copia
      del plugin.

## Fase 2 — Componentes y Design System

- [x] Shortcode `[hub_design]`.
- [x] Bloque de Gutenberg con render en servidor.
- [x] Widget de Elementor.
- [x] Función de plantilla `constructor_hub_render()`.
- [x] Regiones en modo `theme`, `inject` y `replace`.
- [x] Tokens `--hub-*` con export e import.
- [x] Adaptador Elementor con detección de Theme Builder.
- [ ] Biblioteca inicial de componentes: hero, CTA, cards, logos.
- [ ] Primer Header y Footer de Prodata sobre el mismo core.
- [ ] Mega Menu real, más allá del tipo declarado.

## Fase 3 — Landings, formularios y leads

- [x] Token anti spam firmado y tiempo mínimo de envío.
- [x] Punto de extensión para reCAPTCHA o Turnstile.
- [x] Idempotencia que sobrevive al reintento.
- [x] Correo encolado con registro de entregas.
- [x] Evidencia de consentimiento por lead.
- [x] Exportador y borrador de datos personales de WordPress.
- [x] Retención configurable.
- [x] Exportación CSV de leads.
- [x] Conversiones offline de Google Ads.
- [ ] Adaptador reCAPTCHA o Turnstile propiamente dicho.
- [ ] Selector visual de campos del formulario, si un cliente lo pide.
- [ ] Bridge CRM/WebOps sobre `constructor_hub_landing_lead_created`.

## Fase 4 — Design Packages e IA

- [x] `manifest.json` v1 con validación.
- [x] Validación de variables contra el registro real.
- [x] Importación como versión borrador, nunca sobre lo publicado.
- [x] Importación para todos los tipos de diseño.
- [x] Exportación a ZIP.
- [x] Assets por versión.
- [x] Contrato documentado y package de ejemplo verificado por los tests.
- [ ] Importar un proyecto real de Claude Design de principio a fin.
- [ ] Biblioteca compartida de packages entre sitios.

## Fase 5 — Migración de Elementor y WPCode

- [x] Mapa de migración por URL.
- [x] Dequeue selectivo basado en el inventario.
- [x] Migración WPCode por lotes con cursor.
- [x] Traspaso de URL explícito con redirección 301.
- [x] Comandos WP-CLI.
- [x] MVP de página completa eliminado.
- [ ] Migrar la home de Tibox.
- [ ] Migrar la home de Prodata.
- [ ] Medir Core Web Vitals antes y después de cada página migrada.
- [ ] Desactivar los snippets WPCode uno a uno.

## Fase 6 — HUB Theme opcional y multi-sitio

- [x] Theme ultraliviano con hooks intactos.
- [x] `theme.json` con los tokens como paleta del editor.
- [x] Diagnóstico de compatibilidad.
- [x] Export e import de configuración entre sitios.
- [ ] Checklist de migración desde Hello Elementor validado en un sitio real.
- [ ] Mecanismo de release y actualización del plugin.

## Fase 7 — QA en WordPress real

**Esta fase bloquea cualquier declaración de release estable.**

Debe ejecutarse sobre una copia de tibox.cl, no sobre una instalación limpia:
lo que hay que probar es la convivencia con el theme, Elementor, WPCode y los
plugins existentes.

### Instalación y migración

- [ ] Actualizar desde v0.4 y verificar que la migración a `hub_design` conserva
      títulos, slugs, código, packages y configuración de Header/Footer.
- [ ] Verificar que las URLs de landings publicadas siguen resolviendo.
- [ ] Verificar que revertir `hub_tibox_designs_unified` a `0` devuelve el
      comportamiento anterior.

### Render

- [ ] Header HUB en modo `inject` sobre Hello Elementor, con y sin selector de
      ocultación.
- [ ] Ambas regiones en modo `replace`.
- [ ] Landing en modo HUB, documento completo y package.
- [ ] `[hub_design]` dentro de una página de Elementor.
- [ ] Bloque HUB en el editor de bloques.
- [ ] Widget HUB en el editor de Elementor.
- [ ] Aislamiento CSS activado y desactivado.
- [ ] Preview firmado desde una sesión sin iniciar.
- [ ] Publicar una versión y hacer rollback.

### SEO y analítica

- [ ] Rank Math: title, description, canonical, Open Graph y schema en los tres
      modos, incluido el documento completo.
- [ ] Un solo contenedor GTM en la página.
- [ ] `form_submit` una sola vez por lead creado.
- [ ] Ningún tipo de diseño no visible indexable.

### Formularios

- [ ] Envío correcto con el formulario estándar y con uno escrito por IA.
- [ ] Honeypot, tiempo mínimo y límites por IP y por correo.
- [ ] Reintento tras pérdida de respuesta: un solo lead.
- [ ] Envío detrás de Cloudflare con la cabecera de IP configurada.
- [ ] Correo interno y de confirmación entregados por SendGrid vía WP Mail SMTP.
- [ ] Registro de entregas coherente con lo recibido.

### Datos personales

- [ ] Exportación y borrado desde las herramientas de WordPress.
- [ ] Retención automática elimina lo esperado y nada más.

### Rendimiento

- [ ] Core Web Vitals antes y después en al menos tres páginas.
- [ ] Optimizador de assets activado sin romper ninguna página del mapa.

## Criterio de éxito

Un sitio completamente migrado debería poder operar como:

```text
WordPress backend
+ Constructor HUB Tibox
+ theme existente o HUB Theme
```

sin requerir Elementor para el render de las páginas migradas, y manteniendo una
transición segura para las que todavía lo utilicen.
