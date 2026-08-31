# START HERE — contexto para IA y nuevos colaboradores

Última revisión: **2026-08-31**.

Este documento es el punto de entrada obligatorio para cualquier IA o persona que trabaje en **Constructor HUB Tibox**.

## 1. Qué es este proyecto

Constructor HUB Tibox es un plugin WordPress creado para migrar progresivamente sitios existentes desde Elementor/constructores visuales hacia frontend HTML/CSS/JS propio, manteniendo WordPress como backend.

No es un reemplazo inmediato de WordPress. No es un theme. No es un page builder visual tradicional.

La visión es que WordPress administre contenido, medios, SEO, integraciones y datos; Constructor HUB administre progresivamente la presentación.

## 2. Casos iniciales

### tibox.cl

WordPress + Hello Elementor + Elementor. La transición comienza con Header/Footer, Landings y páginas de prueba, sin cambiar el theme productivo.

### prodata.cl

WordPress + Elementor. Debe seguir usando su theme actual inicialmente. La misma arquitectura HUB debe funcionar sin estilos Tibox hardcodeados.

## 3. Antecedente: Cloud-tibox

Repositorio relacionado: `wladimick/Cloud-tibox`.

Cloud-tibox demostró conceptos que deben reutilizarse conceptualmente:

- WordPress como backend;
- frontend propio;
- Design Packages ZIP;
- variables dinámicas `{{VARIABLE}}`;
- contenido administrado desde WordPress;
- preview/versionado/rollback;
- contrato claro para Claude Design/IA.

Diferencia crítica: Cloud-tibox puede utilizar un theme propio. Constructor HUB Tibox debe poder comenzar sobre themes existentes.

Leer `docs/CLOUD-TIBOX-RELATION.md`.

## 4. Estado técnico heredado

El primer MVP se llamó **Tibox AI Frontend**. El 2026-08-28 el producto se renombró a **Constructor HUB Tibox**.

Por compatibilidad todavía existen nombres históricos como:

- `tibox-ai-frontend.php`;
- `TIBOX_AI_FRONTEND_*`;
- clase `TIBOX_AI_Frontend`;
- carpeta/plantilla `home-ai`;
- clases CSS `tbx-ai-*`.

No asumir que esos nombres representan la arquitectura final. Deben migrarse gradualmente y con compatibilidad.

## 5. Estado funcional actual

### Baseline en `main`

`main` conserva la fundación v0.2.0 y la documentación base.

### Rama `feat/hybrid-header-footer`

Implementa:

- CPT `hub_component`;
- Header/Footer HUB;
- HTML/CSS/JS separados;
- configuración de componentes activos;
- renderer híbrido `Header HUB + the_content() + Footer HUB`;
- CI PHP/JavaScript;
- ejemplo Header/Footer Tibox 2026;
- build beta `0.3.0-beta.1`.

Esta rama todavía requiere QA WordPress antes de merge.

### Rama `feat/landings-module`

Rama apilada sobre `feat/hybrid-header-footer`.

Implementa **Landings HUB**:

- menú `Constructor HUB → Landings`;
- CPT público `hub_landing`;
- editor HTML/CSS/JS;
- template full-page sin render Elementor;
- canvas independiente o Header/Footer HUB;
- variables dinámicas;
- `{{HUB_FORM}}`;
- formularios IA custom con `data-hub-landing-form`;
- endpoint `POST /wp-json/constructor-hub/v1/landing-submit`;
- CPT privado `hub_landing_lead` y menú `Envíos Landings`;
- `wp_mail` por landing;
- UTMs/GCLID/GBRAID/WBRAID;
- honeypot, rate limit e idempotencia;
- evento `dataLayer` `form_submit`;
- hook `constructor_hub_landing_lead_created`;
- ejemplo `examples/landing-starter/`.

Leer:

- `docs/changes/2026-08-31-landings-module.md`;
- `docs/decisions/ADR-0002-landings-cpt-native-forms.md`.

## 6. Arquitectura objetivo

Tres modos principales:

- **Legacy:** theme + Elementor controlan la página; HUB puede insertar componentes puntuales.
- **Híbrido:** HUB controla Header/Footer y/o bloques; Elementor puede continuar en contenido.
- **HUB:** HUB controla la página completa y puede descargar Elementor en esa URL.

Las **Landings HUB** son un caso especializado de modo HUB: WordPress mantiene el objeto/publicación/SEO, pero Constructor HUB renderiza todo el cuerpo visual.

A futuro puede existir **HUB Tibox Theme**, pero debe ser opcional.

## 7. Regla multi-sitio

El core debe servir para Tibox, Prodata y futuros clientes.

Por lo tanto:

- branding pertenece a Design System/componentes, no al core;
- endpoints específicos pertenecen a adapters/bridges;
- formularios base deben ser genéricos;
- URLs/privacidad deben ser filtrables/configurables;
- no asumir Hello Elementor como theme obligatorio.

## 8. Reglas para IA

Antes de modificar código:

1. leer este archivo;
2. leer `ARCHITECTURE.md`;
3. leer `DEVELOPMENT-PROTOCOL.md`;
4. revisar las últimas entradas de `CHANGELOG.md`;
5. revisar `ROADMAP.md`;
6. confirmar la rama actual y el estado de `main`;
7. revisar PRs abiertos y ramas apiladas;
8. revisar el código real antes de asumir que una conversación antigua sigue vigente.

Al modificar:

- no poner secretos en repo;
- no hardcodear branding Tibox dentro del core genérico;
- no hacer depender el core de Elementor;
- no hacer depender el core de un theme específico;
- mantener compatibilidad con SEO/hooks WordPress;
- cargar assets solo donde corresponden;
- documentar decisiones y compatibilidad;
- no mergear features que aún requieren QA real si el riesgo frontend es alto.

## 9. Regla de documentación

Todo cambio significativo debe dejar trazabilidad mínima:

- fecha;
- rama;
- commit;
- objetivo;
- archivos/componentes afectados;
- comportamiento anterior;
- comportamiento nuevo;
- compatibilidad/riesgos;
- QA realizado;
- siguiente paso si quedó deuda técnica.

La fuente principal es `docs/CHANGELOG.md` y, para decisiones estructurales, `docs/decisions/`.

## 10. Documentos que son fuente de verdad

- `README.md`: resumen público del proyecto.
- `docs/START-HERE-AI.md`: onboarding/contexto.
- `docs/ARCHITECTURE.md`: arquitectura vigente y objetivo.
- `docs/DEVELOPMENT-PROTOCOL.md`: reglas para trabajar.
- `docs/CHANGELOG.md`: historial técnico.
- `docs/ROADMAP.md`: fases y pendientes.
- `docs/CLOUD-TIBOX-RELATION.md`: relación/reutilización conceptual de Cloud-tibox.
- `docs/SITE-ADAPTERS.md`: reglas para particularidades Tibox, Prodata y futuros sitios.
- `docs/changes/`: implementación detallada por fecha/feature.
- `docs/qa/`: evidencia de validaciones.
- `docs/decisions/`: Architecture Decision Records (ADR).

Si la documentación contradice el código, verificar el historial reciente y corregir la documentación en el mismo cambio.
