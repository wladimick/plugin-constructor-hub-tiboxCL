# Roadmap — Constructor HUB Tibox

Base: 2026-08-28.

El roadmap prioriza migración segura de sitios existentes antes que reemplazo total.

## Fase 0 — Fundación del producto

Estado: **en curso**.

- [x] Renombrar concepto a Constructor HUB Tibox.
- [x] Repositorio renombrado.
- [x] Definir visión multi-sitio Tibox/Prodata.
- [x] Definir arquitectura Legacy/Híbrido/HUB.
- [x] Crear onboarding para IA.
- [x] Crear protocolo de documentación.
- [x] Documentar relación con Cloud-tibox.
- [ ] Normalizar namespaces internos heredados de `Tibox AI Frontend` sin romper upgrades.
- [ ] Añadir CI básico PHP/JS.

## Fase 1 — Header y Footer globales HUB

Prioridad inmediata.

Objetivo: poder reemplazar Header y/o Footer manteniendo el contenido existente de Elementor.

- [ ] Registry de componentes globales.
- [ ] tipo `header`.
- [ ] tipo `footer`.
- [ ] selección de componente activo.
- [ ] ámbito global o páginas seleccionadas.
- [ ] preview solo para administradores.
- [ ] publicar/rollback.
- [ ] evitar Header/Footer duplicados con Elementor Theme Builder/theme actual.
- [ ] adaptador Elementor para esta responsabilidad.
- [ ] mantener `wp_head`, `wp_body_open`, `wp_footer`.
- [ ] primer Header Tibox.
- [ ] primer Footer Tibox.
- [ ] primer Header/Footer Prodata.

## Fase 2 — Design System

- [ ] configuración por sitio.
- [ ] tokens de color.
- [ ] tipografías.
- [ ] containers.
- [ ] spacing.
- [ ] radius.
- [ ] botones/links base.
- [ ] variables CSS `--hub-*`.
- [ ] export/import del Design System.

El core nunca debe hardcodear la identidad visual de Tibox.

## Fase 3 — Biblioteca de componentes

- [ ] heroes.
- [ ] CTAs.
- [ ] grids/cards.
- [ ] logos/partners.
- [ ] navegación.
- [ ] formularios.
- [ ] secciones genéricas.
- [ ] shortcode/bloque de inserción temporal para páginas Elementor.
- [ ] assets por componente.
- [ ] preview/versionado.

## Fase 4 — Design Packages v1

Tomar como antecedente Cloud-tibox.

- [ ] `manifest.json`.
- [ ] `index.html`.
- [ ] `style.css`.
- [ ] `script.js` opcional.
- [ ] `assets/`.
- [ ] import ZIP seguro.
- [ ] validación manifest.
- [ ] preview.
- [ ] versión.
- [ ] rollback.
- [ ] asignación a tipo/destino.
- [ ] variables dinámicas.
- [ ] documentación específica Claude Design/ChatGPT.

## Fase 5 — Página Híbrida

- [ ] establecer modo por página.
- [ ] Legacy.
- [ ] Híbrido.
- [ ] HUB.
- [ ] combinar componentes HUB + `the_content()`.
- [ ] mapa visual de qué tecnología renderiza cada sección.
- [ ] estados de migración por página.

## Fase 6 — Página HUB completa

- [ ] renderer completo.
- [ ] plantillas Home/Página/Single/Archive/404.
- [ ] eliminar assets Elementor solo cuando no sean necesarios.
- [ ] conservar SEO/analítica.
- [ ] migrar home Tibox.
- [ ] migrar home Prodata.
- [ ] medir Core Web Vitals antes/después.

## Fase 7 — Adaptadores e integraciones

### Elementor

- [ ] detección Theme Builder.
- [ ] assets dependency map.
- [ ] compatibilidad Elementor Pro.
- [ ] desactivación selectiva segura.

### SEO

- [ ] Rank Math validado.
- [ ] contrato genérico que no dependa de Rank Math.

### Analítica

- [ ] GTM/dataLayer.
- [ ] evitar tags duplicados.

### Formularios

- [ ] interfaz backend genérica.
- [ ] adaptador endpoint Tibox.
- [ ] adaptador WPForms si se requiere.

## Fase 8 — Migración de dependencias WPCode

Solo después de inventario y QA.

- [ ] identificar snippets que pertenecen realmente al frontend HUB.
- [ ] migrarlos al plugin.
- [ ] mantener otros snippets fuera si no pertenecen al producto.
- [ ] estrategia para evitar doble ejecución durante transición.

## Fase 9 — HUB Tibox Theme opcional

No es requisito para usar Constructor HUB.

- [ ] theme ultraliviano.
- [ ] templates WordPress mínimos.
- [ ] soporte blog/single/archive/404.
- [ ] integración nativa con Constructor HUB.
- [ ] herramienta/checklist de migración desde Hello Elementor/theme anterior.

## Fase 10 — Operación multi-sitio

- [ ] export/import configuración.
- [ ] paquetes reutilizables por cliente.
- [ ] separación core vs site adapters.
- [ ] diagnósticos de compatibilidad.
- [ ] estado de versiones instalado.
- [ ] mecanismo de actualización/release.

## Criterio de éxito

Un sitio completamente migrado debería poder operar como:

```text
WordPress backend
+
Constructor HUB Tibox
+
Theme existente o HUB Theme
```

sin requerir Elementor para el render frontend de las páginas migradas, pero manteniendo una transición segura para las páginas que todavía lo utilicen.