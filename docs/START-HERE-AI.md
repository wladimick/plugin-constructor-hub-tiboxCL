# START HERE — contexto para IA y nuevos colaboradores

Última revisión: **2026-08-31**.

Este documento es el punto de entrada obligatorio para cualquier IA o persona que trabaje en **Constructor HUB Tibox**.

> Auditoría actual: leer también `docs/AUDIT-HANDOFF-2026-08-31.md`. El estado v0.4 fue consolidado en `main` expresamente para auditoría y todavía no debe interpretarse como release estable.

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

## 5. Estado funcional actual en `main`

El 2026-08-31 se consolidaron en `main` las fases v0.3 y v0.4 para permitir una auditoría integral del plugin.

### Header/Footer HUB — v0.3

Rama histórica: `feat/hybrid-header-footer`.

- PR histórico `#2`: cerrado sin merge por permanecer Draft.
- PR de consolidación `#4`: mergeado.
- Merge commit: `ccd3949fcb0132cba6fa4107ad5c9479b4700776`.

Incluye:

- CPT `hub_component`;
- Header/Footer HUB;
- HTML/CSS/JS separados;
- configuración de componentes activos;
- renderer híbrido `Header HUB + the_content() + Footer HUB`;
- CI PHP/JavaScript;
- ejemplo Header/Footer Tibox 2026.

### Landings HUB — v0.4

Rama histórica: `feat/landings-module`.

- PR histórico `#3`: cerrado sin merge por ser Draft/apilado.
- PR de consolidación `#5`: mergeado.
- Merge commit: `705ce5c66ff8bebffbfb310fd1f212d00755e6b4`.

El estado consolidado incluye, entre otras piezas:

- menú `Constructor HUB → Landings`;
- CPT público de Landings;
- HTML/CSS/JS generado por IA;
- renderer HUB;
- modos Legacy/HUB/HTML completo/Package en evolución;
- Header/Footer HUB opcionales;
- formulario HUB nativo;
- endpoint REST propio;
- tracking UTM/GCLID/GBRAID/WBRAID;
- honeypot/rate limit/idempotencia;
- correo mediante `wp_mail()`;
- compatibilidad esperada con WP Mail SMTP/SendGrid;
- almacenamiento de leads y migración desde la implementación WPCode en evolución;
- datos/protección de campañas Google Ads;
- importador ZIP de Claude/IA con validaciones de seguridad;
- compatibilidad temporal con integraciones históricas como WebOps.

**Importante:** el merge se realizó para que Claude/otra IA pueda auditar una única rama `main`. No equivale a QA funcional completo ni a release estable.

Leer obligatoriamente `docs/AUDIT-HANDOFF-2026-08-31.md` antes de emitir conclusiones de auditoría.

## 6. Arquitectura objetivo

Tres modos principales:

- **Legacy:** theme + Elementor controlan la página; HUB puede insertar componentes puntuales.
- **Híbrido:** HUB controla Header/Footer y/o bloques; Elementor puede continuar en contenido.
- **HUB:** HUB controla la página completa y puede descargar Elementor en esa URL.

Las **Landings HUB** son un caso especializado de modo HUB: WordPress mantiene el objeto/publicación/SEO, pero Constructor HUB renderiza el cuerpo visual según el modo configurado.

A futuro puede existir **HUB Tibox Theme**, pero debe ser opcional.

## 7. Regla multi-sitio

El core debe servir para Tibox, Prodata y futuros clientes.

Por lo tanto:

- branding pertenece a Design System/componentes, no al core;
- endpoints específicos pertenecen a adapters/bridges;
- formularios base deben ser genéricos;
- URLs/privacidad deben ser filtrables/configurables;
- no asumir Hello Elementor como theme obligatorio;
- transporte de correo debe pasar por WordPress (`wp_mail`) y no acoplar el core a SendGrid.

## 8. Reglas para IA

Antes de modificar código:

1. leer este archivo;
2. leer `AUDIT-HANDOFF-2026-08-31.md` si la tarea es auditoría/revisión;
3. leer `ARCHITECTURE.md`;
4. leer `DEVELOPMENT-PROTOCOL.md`;
5. revisar las últimas entradas de `CHANGELOG.md`;
6. revisar `ROADMAP.md`;
7. confirmar la rama actual y el estado de `main`;
8. revisar PRs/commits recientes;
9. revisar el código real antes de asumir que una conversación antigua sigue vigente.

Al modificar:

- no poner secretos en repo;
- no hardcodear branding Tibox dentro del core genérico;
- no hacer depender el core de Elementor;
- no hacer depender el core de un theme específico;
- mantener compatibilidad con SEO/hooks WordPress;
- cargar assets solo donde corresponden;
- documentar decisiones y compatibilidad;
- no considerar `main` v0.4 como release estable hasta cerrar auditoría y QA.

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

Los commits exclusivamente documentales quedan auditables mediante Git y no generan una cadena recursiva de auto-documentación.

## 10. Documentos que son fuente de verdad

- `README.md`: resumen público del proyecto.
- `docs/START-HERE-AI.md`: onboarding/contexto.
- `docs/AUDIT-HANDOFF-2026-08-31.md`: estado consolidado y alcance de auditoría actual.
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
