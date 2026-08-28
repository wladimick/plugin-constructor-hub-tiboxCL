# Protocolo de desarrollo y documentación

Vigente desde: 2026-08-28.

Este protocolo aplica a personas, ChatGPT, Claude, Codex y cualquier otra IA que modifique Constructor HUB Tibox.

## Objetivo

Que el repositorio pueda comprenderse y retomarse en cualquier fecha únicamente leyendo el código y `/docs`, sin depender de memoria de conversaciones previas.

## Antes de trabajar

1. Revisar el estado ACTUAL de `main`.
2. Revisar PRs abiertos y ramas relevantes.
3. Leer:
   - `README.md`;
   - `docs/START-HERE-AI.md`;
   - `docs/ARCHITECTURE.md`;
   - últimas entradas de `docs/CHANGELOG.md`;
   - `docs/ROADMAP.md`.
4. Confirmar que la tarea no contradice una decisión ADR vigente.
5. No asumir que una descripción histórica sigue siendo válida si el código actual dice otra cosa.

## Ramas

Nunca implementar features directamente en `main`.

Convención:

```text
feat/<descripcion>
fix/<descripcion>
refactor/<descripcion>
docs/<descripcion>
chore/<descripcion>
```

Toda entrada de changelog debe indicar la rama utilizada.

## Commits

Usar mensajes claros y de alcance reducido:

```text
feat: add global HUB header renderer
fix: preserve Rank Math output in HUB templates
refactor: extract Elementor adapter
docs: document package manifest contract
chore: bump plugin version
```

Evitar mensajes ambiguos como `changes`, `update`, `fix stuff`.

## Documentación obligatoria por cambio

Para cada cambio funcional, estructural, de integración o compatibilidad registrar en `docs/CHANGELOG.md`:

- fecha (`YYYY-MM-DD`);
- rama;
- commit de implementación;
- autor/agente cuando sea útil;
- objetivo;
- archivos principales afectados;
- comportamiento anterior;
- comportamiento nuevo;
- decisiones tomadas;
- riesgos/compatibilidad;
- QA realizado;
- deuda técnica/siguiente paso.

No es necesario crear una cadena infinita de entradas para commits cuyo único propósito sea actualizar el changelog de ese mismo cambio. La entrada documenta el **cambio de desarrollo** y enlaza su commit objetivo; Git conserva automáticamente los commits documentales.

## ADR — Architecture Decision Records

Crear un archivo en `docs/decisions/` cuando una decisión:

- afecte arquitectura;
- establezca una dependencia importante;
- cambie el contrato de componentes/Design Packages;
- modifique la estrategia de compatibilidad;
- sea difícil/costoso revertir;
- necesite contexto para una IA futura.

Formato:

```text
ADR-0001-titulo.md
ADR-0002-titulo.md
```

Cada ADR incluye:

- estado: propuesta/aceptada/sustituida;
- fecha;
- contexto;
- decisión;
- alternativas consideradas;
- consecuencias;
- referencias a rama/commit/PR.

## Pull Requests

Cada PR debe indicar:

- problema/objetivo;
- alcance;
- qué NO incluye;
- arquitectura afectada;
- compatibilidad;
- QA;
- documentación actualizada;
- rollback cuando aplique.

No hacer merge de un cambio importante si su documentación está obsoleta.

## Versiones

Usar Semantic Versioning mientras sea posible:

- patch: corrección compatible;
- minor: funcionalidad compatible;
- major: cambio incompatible.

La versión del plugin debe actualizarse cuando se prepare una entrega instalable significativa, no necesariamente por cada commit interno.

## Reglas para componentes generados por IA

Por defecto entregar:

```text
manifest.json
index.html
style.css
script.js (solo si hace falta)
assets/
```

No incluir:

- PHP generado dentro de paquetes visuales;
- secretos;
- librerías externas sin justificar;
- URLs hardcodeadas cuando existe variable HUB;
- estilos globales no namespaced salvo parte explícita del Design System.

Preferir:

- HTML semántico;
- CSS nativo;
- JS nativo;
- accesibilidad;
- `prefers-reduced-motion`;
- responsive;
- tokens `--hub-*`.

## Reglas de compatibilidad

Tibox y Prodata son sitios productivos existentes.

Por lo tanto:

- el default debe mantener el comportamiento existente;
- activar HUB debe ser explícito;
- ofrecer rollback/desactivación;
- no eliminar Elementor mientras existan páginas dependientes;
- no cambiar theme automáticamente;
- no eliminar WPCode/integraciones sin inventario y reemplazo validado;
- preservar hooks WordPress/SEO/analítica.

## QA mínimo según tipo de cambio

### PHP

- sintaxis;
- permisos/nonces cuando aplique;
- sanitización/escape;
- comportamiento con plugin desactivado/revertido.

### Frontend

- desktop/mobile;
- teclado/focus;
- consola sin errores;
- rutas/assets correctos;
- sin referencias `file:///`;
- no duplicar IDs;
- no degradar LCP intencionalmente.

### SEO

- title;
- meta description;
- canonical;
- robots;
- Open Graph;
- schema cuando aplique.

### Analítica

- GTM presente una sola vez;
- `dataLayer` esperado;
- eventos solo cuando corresponde.

### Elementor/híbrido

- contenido Elementor sigue visible;
- widgets interactivos restantes funcionan;
- no descargar dependencia todavía usada;
- header/footer no aparecen duplicados.

## Regla final

Si una IA no puede explicar con claridad por qué existe un archivo, de dónde viene una dependencia o qué sitio utiliza una integración, debe investigar el repositorio/documentos antes de modificarla. No completar huecos mediante suposiciones silenciosas.