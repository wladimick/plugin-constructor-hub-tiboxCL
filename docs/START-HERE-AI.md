# START HERE — contexto para IA y nuevos colaboradores

Última revisión base: 2026-08-28.

Este documento es el punto de entrada obligatorio para cualquier IA o persona que trabaje en **Constructor HUB Tibox**.

## 1. Qué es este proyecto

Constructor HUB Tibox es un plugin WordPress creado para migrar progresivamente sitios existentes desde Elementor/constructores visuales hacia frontend HTML/CSS/JS propio, manteniendo WordPress como backend.

No es un reemplazo inmediato de WordPress. No es un theme. No es un page builder visual tradicional.

La visión es que WordPress administre contenido, medios, SEO, integraciones y datos; Constructor HUB administre progresivamente la presentación.

## 2. Casos iniciales

### tibox.cl

Actualmente WordPress + Hello Elementor + Elementor. Se desea comenzar reemplazando Header y Footer, luego bloques y finalmente páginas completas.

### prodata.cl

WordPress + Elementor. Debe seguir usando su theme actual inicialmente. La misma arquitectura HUB debe funcionar sin estilos Tibox hardcodeados.

## 3. Antecedente: Cloud-tibox

Repositorio relacionado: `wladimick/Cloud-tibox`.

Cloud-tibox demostró varios conceptos que deben reutilizarse conceptualmente:

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

No asumir que esos nombres representan la arquitectura final. Deben migrarse gradualmente y con compatibilidad, nunca mediante un cambio destructivo improvisado.

## 5. Estado funcional del MVP

Hoy existe una prueba de página completa que puede:

- activarse por página mediante metabox;
- reemplazar el template del theme para esa página;
- mantener `wp_head()`, `wp_body_open()` y `wp_footer()`;
- cargar CSS/JS propios;
- reducir assets de Elementor;
- renderizar una home de prueba;
- enviar un formulario a un endpoint REST existente de Tibox.

Esto es un prototipo y no es todavía el modelo definitivo de componentes.

## 6. Próximo objetivo funcional

La prioridad posterior a la formalización documental es construir el **sistema global de Header/Footer HUB** sin requerir reemplazar el contenido Elementor de una página.

Objetivo esperado:

```text
Header: HUB
Contenido: theme/Elementor actual
Footer: HUB
```

Debe ser reversible y configurable.

## 7. Arquitectura objetivo

Tres modos:

- **Legacy:** theme + Elementor controlan la página; HUB puede insertar componentes puntuales.
- **Híbrido:** HUB controla Header/Footer y/o bloques; Elementor puede continuar en contenido.
- **HUB:** HUB controla la página completa y puede descargar Elementor en esa URL.

A futuro puede existir **HUB Tibox Theme**, pero debe ser opcional.

## 8. Reglas para IA

Antes de modificar código:

1. leer este archivo;
2. leer `ARCHITECTURE.md`;
3. leer `DEVELOPMENT-PROTOCOL.md`;
4. revisar las últimas entradas de `CHANGELOG.md`;
5. revisar `ROADMAP.md`;
6. confirmar la rama actual y el estado de `main`;
7. revisar el código real antes de asumir que una conversación antigua sigue vigente.

Al modificar:

- no poner secretos en repo;
- no hardcodear branding Tibox dentro del core genérico;
- no hacer depender el core de Elementor;
- no hacer depender el core de un theme específico;
- mantener compatibilidad con SEO/hooks WordPress;
- cargar assets solo donde corresponden;
- documentar decisiones y compatibilidad.

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

La fuente principal de esta trazabilidad es `docs/CHANGELOG.md` y, para decisiones estructurales, `docs/decisions/`.

## 10. Documentos que son fuente de verdad

- `README.md`: resumen público del proyecto.
- `docs/START-HERE-AI.md`: onboarding/contexto.
- `docs/ARCHITECTURE.md`: arquitectura vigente y objetivo.
- `docs/DEVELOPMENT-PROTOCOL.md`: reglas para trabajar.
- `docs/CHANGELOG.md`: historial técnico.
- `docs/ROADMAP.md`: fases y pendientes.
- `docs/CLOUD-TIBOX-RELATION.md`: relación/reutilización conceptual de Cloud-tibox.
- `docs/SITE-ADAPTERS.md`: reglas para particularidades Tibox, Prodata y futuros sitios.
- `docs/decisions/`: Architecture Decision Records (ADR).

Si la documentación contradice el código, verificar el historial reciente y corregir la documentación en el mismo cambio.