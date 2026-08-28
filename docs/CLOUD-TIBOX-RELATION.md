# Relación con Cloud-tibox

Fecha base: 2026-08-28.

Repositorio antecedente: `wladimick/Cloud-tibox`.

## Qué aporta Cloud-tibox

Cloud-tibox ya implementó y validó conceptualmente varias ideas que Constructor HUB Tibox debe aprovechar:

- separación WordPress/backend y frontend propio;
- TIBOX Core para lógica/contenido;
- theme ultraliviano para presentación;
- Header/Footer globales propios;
- plantillas HTML administrables;
- contrato para Claude Design/IA;
- variables dinámicas `{{...}}`;
- Slider Home administrado desde WordPress;
- Design Packages ZIP;
- preview;
- asignaciones;
- versionado/rollback;
- CSS/JS cargado por destino;
- prohibición de PHP arbitrario dentro del diseño.

## Diferencia fundamental

Cloud-tibox corresponde a un sitio nuevo donde es viable utilizar un theme propio desde el comienzo.

Constructor HUB Tibox nace para **sitios productivos existentes** como Tibox y Prodata, donde inicialmente:

- no se puede cambiar el theme de forma abrupta;
- Elementor sigue renderizando muchas páginas;
- hay plugins/snippets/integraciones en producción;
- la migración debe realizarse bloque por bloque.

Por lo tanto, Constructor HUB no debe copiar la dependencia Theme → diseño que existe en Cloud-tibox.

## Modelo recomendado

### Cloud-tibox

```text
WordPress
+ TIBOX Core
+ TIBOX Theme
+ Design Packages
```

### Constructor HUB

```text
WordPress
+ Constructor HUB Tibox
+ theme existente
+ Elementor opcional/transitorio
```

A futuro:

```text
WordPress
+ Constructor HUB Tibox
+ HUB Tibox Theme opcional
```

## Qué reutilizar conceptualmente

### 1. Design Packages

Formato base recomendado:

```text
manifest.json
index.html
style.css
script.js
assets/
```

Constructor HUB debe extender este concepto para soportar también **componentes** y no solo plantillas completas.

### 2. Contrato Claude Design / IA

Se mantiene el principio:

- IA produce presentación;
- WordPress produce datos/contenido;
- no generar PHP en paquetes;
- no incluir secretos;
- usar variables dinámicas;
- HTML semántico;
- CSS/JS nativo por defecto.

### 3. Variables

Variables iniciales compatibles conceptualmente:

```text
{{SITE_URL}}
{{HOME_URL}}
{{SITE_NAME}}
{{CURRENT_YEAR}}
{{CUSTOM_LOGO}}
{{MENU_PRIMARY}}
{{MENU_FOOTER}}
{{PAGE_ID}}
{{PAGE_TITLE}}
{{PAGE_URL}}
{{PAGE_EXCERPT}}
{{PAGE_CONTENT}}
{{FEATURED_IMAGE}}
```

Constructor HUB debe definir un registry formal y versionable de variables.

### 4. Preview/versionado/rollback

Es especialmente importante en sitios productivos. Un componente nuevo debe poder previsualizarse antes de sustituir el existente.

## Qué NO copiar directamente

- lógica acoplada al TIBOX Theme;
- branding específico Cloud;
- catálogo específico del sitio si no pertenece al core genérico;
- supuestos de que el theme es controlado por Tibox;
- templates PHP propios del theme como dependencia obligatoria.

## Posible convergencia futura

Constructor HUB podría llegar a convertirse en la capa común de presentación de futuros proyectos WordPress Tibox.

En ese escenario:

- `Constructor HUB Tibox` = producto/plugin estable y reutilizable;
- `HUB Tibox Theme` = theme opcional ultraliviano;
- proyectos como Cloud pueden migrar conceptos/paquetes hacia el estándar HUB cuando tenga sentido.

No realizar esa convergencia automáticamente: debe evaluarse cuando el contrato HUB esté estabilizado.