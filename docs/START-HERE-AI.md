# START HERE — contexto para IA y nuevos colaboradores

Última revisión: **2026-08-31** (post-refundación v0.5).

Este documento es el punto de entrada obligatorio para cualquier IA o persona que trabaje en **Constructor HUB Tibox**.

> **Estado actual:** la auditoría del 2026-08-31 (`docs/AUDIT-HANDOFF-2026-08-31.md`)
> derivó en una refundación implementada en la rama `feat/hub-v0.5-refundacion`,
> versión `0.5.0-dev`. Antes de tocar código lee
> `docs/decisions/ADR-0003-design-unificado-y-versionado.md` y
> `docs/decisions/ADR-0004-insertion-api-y-regiones.md`: el modelo de datos
> cambió. Nada de esto se ha ejecutado todavía en un WordPress real; la Fase 7
> del roadmap es QA y bloquea cualquier release.

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

El primer MVP se llamó **Tibox AI Frontend**. El 2026-08-28 el producto se
renombró a **Constructor HUB Tibox**. El MVP de página completa
(`includes/class-tibox-ai-frontend.php`, `pages/home-ai/`,
`templates/ai-page.php`) se eliminó en la Fase 5.

Nombres históricos que **siguen existiendo a propósito**:

- `tibox-ai-frontend.php` como archivo bootstrap y como nombre de carpeta del
  plugin. WordPress identifica un plugin por su ruta: renombrarlo instalaría una
  segunda copia con clases duplicadas. Normalizarlo requiere una estrategia de
  actualización y está pendiente en la Fase 1 del roadmap.
- Constantes `TIBOX_AI_FRONTEND_*`.
- Los post types `hub_component` y `hub_landing`, registrados sin interfaz para
  que sus datos sigan disponibles tras la unificación.
- El endpoint REST `tibox/v1/lead`, servido por el plugin como alias del
  pipeline actual.
- El hook `tibox_landing_lead_created`, puente documentado con las integraciones
  WPCode.

## 5. Estado funcional actual

### Modelo de datos

Un único CPT **`hub_design`** con un meta de tipo: `header`, `footer`, `menu`,
`hero`, `section`, `form`, `landing`, `page`. El código visual **no** vive en
post meta: vive en la tabla `wp_hub_design_versions` como versiones inmutables
con estado `draft`, `live` o `archived`.

Publicar es mover un puntero. Rollback es moverlo atrás. Preview es una URL
firmada que caduca.

### Qué hay implementado

- Insertion API: shortcode `[hub_design]`, bloque de Gutenberg, widget de
  Elementor y `constructor_hub_render()`.
- Regiones Header y Footer independientes, en modo `theme`, `inject` o
  `replace`.
- Design System con tokens `--hub-*` exportables entre sitios.
- Compilador de assets a archivo con aislamiento CSS opcional.
- Contrato de Design Packages con `manifest.json` validado.
- Formularios con token anti spam firmado, idempotencia real, correo encolado y
  registro de entregas.
- Leads con evidencia de consentimiento, exportación CSV, conversiones offline
  de Google Ads, retención y las herramientas de privacidad de WordPress.
- Mapa de migración por URL y retirada de assets de Elementor basada en él.
- Migración WPCode por lotes con traspaso de URL explícito y redirección 301.
- HUB Theme opcional en `theme/hub-theme/`.
- Diagnóstico de compatibilidad y export/import de configuración.

### Qué NO está hecho

- QA en un WordPress real. Nada de lo anterior se ha ejecutado fuera de análisis
  estático y tests unitarios.
- Biblioteca de componentes, Mega Menu real, adaptador de CAPTCHA, bridge CRM.
- Migración de las homes de Tibox y Prodata.

Ver `docs/ROADMAP.md` para el detalle y `docs/CHANGELOG.md` para qué cambió en
cada fase.

## 6. Arquitectura objetivo

Tres alcances de render, no tres modos de página:

- **Región:** Header y Footer, configurables por separado. `inject` conserva la
  plantilla del theme; `replace` entrega el documento a HUB.
- **Fragmento:** un diseño insertado dentro de contenido que el HUB no controla,
  típicamente una página de Elementor. Es el mecanismo de la migración por
  piezas.
- **Documento:** los tipos `landing` y `page` tienen URL propia y se renderizan
  como fragmento en el shell HUB, como documento HTML completo o como package.

En todos los casos se conservan `wp_head()`, `wp_body_open()` y `wp_footer()`.

El **HUB Theme** existe en `theme/hub-theme/` y es opcional: no se empaqueta con
el plugin y Constructor HUB funciona igual sobre cualquier theme.

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
2. leer `ARCHITECTURE.md`;
3. leer los ADR, especialmente 0003 y 0004: el modelo de datos cambió;
4. leer `DEVELOPMENT-PROTOCOL.md`;
5. revisar las últimas entradas de `CHANGELOG.md`;
6. revisar `ROADMAP.md`, incluida la Fase 7 de QA pendiente;
7. si la tarea toca formularios o packages, leer `FORMS-AND-TRACKING.md` y
   `AI-PACKAGE-CONTRACT.md`;
8. confirmar la rama actual y el estado de `main`;
9. revisar el código real antes de asumir que una conversación antigua sigue
   vigente.

Antes de entregar:

```bash
composer install
composer run-script lint      # PHPCS, reglas de seguridad WordPress
composer run-script analyse   # PHPStan nivel 5
composer run-script test      # arnés propio
```

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
- `docs/AUDIT-HANDOFF-2026-08-31.md`: alcance de la auditoría que originó la refundación.
- `docs/AI-PACKAGE-CONTRACT.md`: contrato de Design Packages para IA.
- `docs/FORMS-AND-TRACKING.md`: contrato de formularios, leads y tracking.
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
