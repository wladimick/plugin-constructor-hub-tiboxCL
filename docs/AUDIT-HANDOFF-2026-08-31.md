# Handoff de auditoría — 2026-08-31

Este documento existe para que una IA o revisor técnico pueda auditar el estado consolidado de **Constructor HUB Tibox** leyendo el repositorio sin depender del historial del chat.

## Estado de `main`

Fecha de consolidación: **2026-08-31**.

Merge de Header/Footer HUB:

- PR histórico de desarrollo: `#2` (`feat/hybrid-header-footer`), cerrado sin merge porque permanecía en Draft y el conector no pudo cambiar ese estado por un error GraphQL.
- PR de consolidación: `#4`.
- Merge commit en `main`: `ccd3949fcb0132cba6fa4107ad5c9479b4700776`.

Merge de Landings HUB:

- PR histórico de desarrollo: `#3` (`feat/landings-module`), cerrado sin merge porque estaba apilado sobre la rama del PR #2 y también permanecía en Draft.
- PR de consolidación: `#5`.
- Merge commit en `main`: `705ce5c66ff8bebffbfb310fd1f212d00755e6b4`.

Los PRs de consolidación usaron merge commits y conservaron los commits individuales de las ramas de desarrollo para mantener trazabilidad.

## Qué debe auditarse

Auditar el plugin completo desde `main`, no solo los últimos PRs.

Prioridad recomendada:

1. seguridad del importador ZIP y manejo de archivos en `uploads`;
2. seguridad y consistencia del endpoint REST de formularios;
3. almacenamiento/migración de leads y protección de datos personales;
4. compatibilidad de correo `wp_mail()` con WP Mail SMTP/SendGrid;
5. compatibilidad temporal con el hook histórico `tibox_landing_lead_created` y WebOps;
6. idempotencia, honeypot, rate limit y tracking UTM/Google Ads;
7. renderer HUB, HTML completo y packages;
8. conservación de `wp_head()`, `wp_body_open()` y `wp_footer()` donde corresponda;
9. compatibilidad Rank Math/GTM/Elementor/theme existente;
10. riesgos de duplicidad SEO al migrar landings históricas;
11. capacidades/roles de administración para componentes, landings y leads;
12. sanitización/escape de HTML, CSS, JS y metadatos;
13. rendimiento y carga de assets;
14. compatibilidad PHP 8.0/8.3;
15. arquitectura general y deuda heredada del MVP `Tibox AI Frontend`.

## Contexto de WPCode a migrar

El desarrollo actual busca absorber tres responsabilidades históricas que existían como snippets WPCode en Tibox:

- endpoint universal de formularios + almacenamiento local de leads;
- gestor de Landing Pages;
- importador ZIP de Claude Design.

La migración debe ser **controlada**. No asumir que los snippets pueden eliminarse solo porque el código equivalente existe en el plugin.

Antes de desactivar WPCode se debe validar al menos:

- migración/copia de landings históricas;
- migración/copia de leads históricos;
- correo interno;
- correo de confirmación;
- transporte real por WP Mail SMTP/SendGrid;
- hook/bridge WebOps;
- conversiones GTM/Google Ads;
- tracking UTMs y click IDs;
- URLs/canonical/noindex según corresponda;
- importación ZIP de un proyecto Claude real.

## SendGrid

Constructor HUB no debe guardar una API key de SendGrid ni implementar directamente el transporte SendGrid.

Contrato esperado:

```text
Constructor HUB
    -> wp_mail()
    -> WP Mail SMTP
    -> SendGrid (u otro mailer configurado por el sitio)
```

Esto mantiene el core reutilizable en Tibox, Prodata y futuros WordPress.

## Estado de calidad

El código fue consolidado a `main` **para auditoría**, no como declaración de release estable.

No interpretar el merge como QA funcional completo en producción.

La auditoría debe distinguir:

- defectos de seguridad;
- bugs funcionales;
- incompatibilidades WordPress/Elementor/theme;
- deuda técnica;
- mejoras opcionales;
- mejoras que deberían bloquear una release estable.

## Documentos obligatorios antes de auditar

Leer en este orden:

1. `docs/START-HERE-AI.md`
2. `docs/ARCHITECTURE.md`
3. `docs/DEVELOPMENT-PROTOCOL.md`
4. `docs/CHANGELOG.md`
5. `docs/ROADMAP.md`
6. `docs/CLOUD-TIBOX-RELATION.md`
7. `docs/SITE-ADAPTERS.md`
8. `docs/decisions/`
9. `docs/changes/`
10. este documento.

## Formato esperado de una auditoría externa

Para cada hallazgo indicar:

- severidad: crítica / alta / media / baja / recomendación;
- archivo y función/clase;
- comportamiento actual;
- riesgo concreto;
- escenario para reproducirlo;
- corrección recomendada;
- si bloquea instalación de prueba, producción o solo release estable.

No modificar código durante la primera pasada de auditoría. Primero entregar el inventario completo de hallazgos para poder priorizar y crear una rama de correcciones separada.
