# Changelog técnico

Este archivo registra cambios de desarrollo relevantes para que una persona o IA pueda reconstruir la evolución del proyecto.

Formato requerido desde 2026-08-28: **fecha · rama · commit · objetivo · impacto · QA/deuda**.

---

## 2026-08-28 — Formalización como Constructor HUB Tibox

Rama: `feat/ai-frontend-mvp` (nombre histórico de la rama inicial; se conserva hasta cerrar el PR #1).

### `8c7c5ff4d391f16f24a93e4109b6e7232c1279d5` — identidad del plugin

- Tipo: refactor/branding compatible.
- Objetivo: renombrar públicamente `Tibox AI Frontend` a **Constructor HUB Tibox**.
- Archivo: `tibox-ai-frontend.php`.
- Cambios:
  - Plugin Name actualizado.
  - Plugin URI actualizado al repositorio renombrado.
  - versión pasa a `0.2.0`.
  - descripción cambia desde una home IA específica hacia constructor frontend progresivo.
- Compatibilidad:
  - se mantiene temporalmente el nombre histórico del archivo bootstrap, constantes y clase para no romper instalaciones del MVP.
- Deuda:
  - migrar namespaces/nombres internos de forma compatible en una fase posterior.

### `a34ffdeec0295431f8c04f6197d777b236cab470` — README como mapa del producto

- Tipo: documentación.
- Objetivo: redefinir alcance multi-sitio y flujo Legacy/Híbrido/HUB.
- Establece a Tibox y Prodata como primeras implementaciones del mismo core.
- Define relación conceptual con Cloud-tibox.
- Añade enlaces obligatorios a `/docs`.

### `62c80b90db4e5d3db5fd3b3e900c2e7e2a6525c2` — onboarding para IA

- Archivo: `docs/START-HERE-AI.md`.
- Objetivo: que una IA nueva entienda propósito, estado heredado, próximos pasos y reglas antes de modificar código.
- Documenta explícitamente nombres históricos `Tibox AI Frontend` que aún existen.

### `1c5f5615f191eafa75174bd0e38ae3577648ada8` — arquitectura objetivo

- Archivo: `docs/ARCHITECTURE.md`.
- Define:
  - core independiente;
  - modos Legacy/Híbrido/HUB;
  - Component Registry;
  - Design Packages;
  - variables dinámicas;
  - Design System;
  - adaptadores;
  - HUB Theme futuro opcional;
  - principios de SEO, seguridad y rendimiento.

### `ad7b04b38c965b2caceaca94ad69b25c5deb6149` — protocolo obligatorio

- Archivo: `docs/DEVELOPMENT-PROTOCOL.md`.
- Define reglas de ramas, commits, PR, QA, ADR y changelog.
- Desde esta fecha todo cambio funcional/arquitectónico debe registrar fecha, rama y commit.

### `7746b3077f56e17c26af73132e85c2c096f7bc15` — roadmap

- Archivo: `docs/ROADMAP.md`.
- Orden de trabajo establecido:
  1. fundación;
  2. Header/Footer;
  3. Design System;
  4. componentes;
  5. Design Packages;
  6. páginas híbridas/HUB;
  7. adaptadores;
  8. migración WPCode;
  9. HUB Theme opcional.

### `422257e3ad8319dcb0d147c7e788a1345be9d8c7` — relación con Cloud-tibox

- Archivo: `docs/CLOUD-TIBOX-RELATION.md`.
- Documenta qué conceptos reutilizar y qué acoplamientos no copiar.
- Design Packages, variables y contrato IA se consideran antecedentes directos.

### `82f0d1ee738b0bf6434594f8a84de84dc99cd434` — límites de adaptadores

- Archivo: `docs/SITE-ADAPTERS.md`.
- Separa core, adaptador Elementor, adaptador Tibox y futuro adaptador Prodata.
- Regla: diferencias visuales normales pertenecen al Design System, no a código específico del sitio.

### `eb6bd668bc5e4ea7c14fae7fa3b51a99ff5e0ef8` — ADR-0001

- Archivo: `docs/decisions/ADR-0001-core-independent-theme-elementor.md`.
- Decisión aceptada: el core no dependerá de Elementor ni de un theme específico.
- HUB Tibox Theme será opcional.

### `5f8ce97fa5d588fb34acaf78b17fd5be94112225` — deprecación del documento MVP

- Archivo: `docs/architecture.md`.
- El documento histórico queda como redirect hacia `docs/ARCHITECTURE.md` para evitar dos fuentes de verdad.

### QA/documentación de esta formalización

- Repositorio renombrado verificado: `wladimick/plugin-constructor-hub-tiboxCL`.
- PR #1 y rama histórica verificados tras el rename.
- No se ha cambiado todavía el comportamiento de renderizado del MVP salvo identidad pública/versión.
- No se ha hecho merge a `main`.

### Siguiente paso

Implementar **Header/Footer HUB globales en modo Híbrido** manteniendo contenido Elementor actual.

---

## 2026-08-27 — MVP histórico: Tibox AI Frontend

Rama: `feat/ai-frontend-mvp`.

Base `main`: `ed08412188c4a047a66b9be2363e8695d2c3c8d3` (`chore: initialize Tibox AI Frontend plugin repository`).

Rango del MVP anterior a la formalización HUB:

`ed08412188c4a047a66b9be2363e8695d2c3c8d3..af3674ebfe270bf955b41e7abdc768c505af423a`

Commits conocidos del desarrollo inicial incluyen:

- `9db9649bcd03e6cf65e19d867b124db4601216a8`
- `c93fb3edb3a742a2ca0171644aa627974dda0743`
- `1140a526682684c8caf82cdf43b7002b9934d4ca`
- `d790c1ce72692080e79e03877613c80d97a9248e`
- `3212f531ab8b1ca8a0135bf7098d3533ae6486b7`
- `af157f2b89fd8ae4327549f4431a020f28ec5aa8`
- `6726df4772b240b99a17d1e66fe7259bf37dee2f` — interacciones nativas y formulario de leads.
- `1207c8947072496fef882e4c144e9d326e8e44ea` — documentación de instalación/rollout del MVP.
- `d84627115773202f2902f6bab665c2f56316065c` — notas de arquitectura del MVP.
- `af3674ebfe270bf955b41e7abdc768c505af423a` — requisito PHP 8.

### Resultado del MVP

Se construyó una prueba de frontend de página completa capaz de:

- reemplazar el template por página;
- conservar hooks WordPress;
- reducir assets Elementor;
- usar HTML/CSS/JS nativo;
- probar una nueva home Tibox;
- conectar formulario a REST.

### Limitación identificada

El enfoque estaba demasiado orientado a reemplazar páginas completas. La dirección aprobada el 2026-08-28 cambia hacia **migración por componentes**, comenzando por Header/Footer y coexistencia con Elementor.

---

## Cómo agregar futuras entradas

Copiar esta estructura:

```md
## YYYY-MM-DD — Nombre del cambio

Rama: `feat/...`
Commit: `SHA`
PR: `#N` si existe

### Objetivo
...

### Antes
...

### Después
...

### Archivos/componentes
...

### Compatibilidad y riesgos
...

### QA
...

### Pendiente
...
```

Los commits exclusivamente documentales asociados a una misma implementación quedan auditables mediante Git y no requieren crear una recursión infinita de entradas auto-referenciales.