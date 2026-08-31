# Changelog técnico

Este archivo registra cambios de desarrollo relevantes para que una persona o IA pueda reconstruir la evolución del proyecto.

Formato requerido desde 2026-08-28: **fecha · rama · commit · objetivo · impacto · QA/deuda**.

---

## 2026-08-28 — Header/Footer HUB híbridos

Rama: `feat/hybrid-header-footer`.
Baseline `main`: `3944fa9f37ee4fe897883abecae39dcc5656fea7`.
Estado: en desarrollo / QA WordPress pendiente.

Documento detallado: [`changes/2026-08-28-hybrid-header-footer.md`](changes/2026-08-28-hybrid-header-footer.md).

### `b18b77535349db25807840fba287b1d81c58e8fe` — component manager

- Añade CPT privado `hub_component`.
- Tipos iniciales: Header y Footer.
- Guarda HTML/CSS/JavaScript por separado.
- Añade variables dinámicas iniciales.
- Añade configuración de Header/Footer activos y alcance híbrido.

### `61e8251c637e5e8d13bdffaebf4a1b0e8ddb2ae9` — renderer híbrido

- Intercepta únicamente Pages configuradas.
- Mantiene prioridad del MVP full-page histórico cuando está activo.
- No descarga Elementor/theme assets en modo Híbrido.
- Imprime CSS/JS de componentes mediante hooks WordPress.

### `96c40fb0719648a07cb0de86d77d9b24b9f0d942` — template híbrido

- Estructura: `wp_head()` → Header HUB → `the_content()` → Footer HUB → `wp_footer()`.
- Permite que Elementor siga procesando el contenido central.

### `713fb0cb2a8d874445a813c096de90b8a78cbe16` — wiring v0.3-dev

- Integra Component Manager y Hybrid Renderer en bootstrap.
- Versión de desarrollo `0.3.0-dev`.
- Mantiene temporalmente core/nombres históricos del MVP.

### `b8432516566b4544125964cda2e403ebd0df70b2` — CI

- Añade GitHub Actions.
- PHP syntax: 8.0 y 8.3.
- JavaScript: `node --check`.

### `53fb1e4d1725ac7f16a625b078b2882e7b9dbc98` — registro detallado

- Documenta arquitectura del cambio, seguridad, compatibilidad, QA y pendientes.

### Compatibilidad/riesgo

- Modo desactivado por defecto.
- Requiere Header y Footer publicados/seleccionados.
- v0.3 inicial solo se aplica a Pages.
- No cambia theme.
- No desactiva Elementor.
- Falta validar en un WordPress/Elementor real antes de merge.

### Próximos pasos

- validar CI;
- crear PR draft;
- probar en WordPress real;
- agregar preview para administradores;
- adapter Elementor para Header/Footer de Theme Builder;
- primer Header/Footer Tibox como componentes de prueba.

---

## 2026-08-28 — Formalización como Constructor HUB Tibox

Rama: `feat/ai-frontend-mvp` (nombre histórico de la rama inicial).
PR: `#1`.
Merge a `main`: `3944fa9f37ee4fe897883abecae39dcc5656fea7`.

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

### `15d6c7c207801ddc78d8aa8299a94ffe447b43c9` — changelog base

- Reconstruye historial del MVP y formalización.
- Establece plantilla de trazabilidad futura.

### Resultado

- Repositorio renombrado verificado: `wladimick/plugin-constructor-hub-tiboxCL`.
- PR #1 mergeado sin squash para conservar commits individuales.
- `main` pasa a ser una baseline documentada de Constructor HUB Tibox v0.2.0.

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