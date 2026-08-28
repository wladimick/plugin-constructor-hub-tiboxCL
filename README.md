# Constructor HUB Tibox

Constructor frontend progresivo para WordPress desarrollado por Tibox.

Su objetivo es permitir que sitios WordPress existentes migren gradualmente desde constructores visuales como Elementor hacia una capa frontend propia, liviana y generable con IA, **sin perder WordPress como backend** y sin exigir un cambio inmediato de theme.

Repositorio: `wladimick/plugin-constructor-hub-tiboxCL`

## Leer primero

Toda persona o IA que vaya a trabajar en este repositorio debe comenzar por:

1. [`docs/START-HERE-AI.md`](docs/START-HERE-AI.md)
2. [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
3. [`docs/DEVELOPMENT-PROTOCOL.md`](docs/DEVELOPMENT-PROTOCOL.md)
4. [`docs/CHANGELOG.md`](docs/CHANGELOG.md)
5. [`docs/ROADMAP.md`](docs/ROADMAP.md)

La documentación es parte del producto. Un cambio de código que altere comportamiento, arquitectura, compatibilidad o flujo de trabajo debe quedar documentado.

## Problema que resuelve

Tibox y Prodata utilizan actualmente WordPress + Elementor. No es viable reemplazar todo el frontend de una sola vez ni cambiar inmediatamente el theme existente.

Constructor HUB Tibox permite una transición por capas:

```text
WordPress
├── contenido
├── medios
├── usuarios
├── SEO / Rank Math
├── formularios / endpoints
├── analítica / GTM
└── Constructor HUB Tibox
    ├── Header HUB
    ├── Footer HUB
    ├── bloques HUB
    ├── páginas híbridas
    ├── páginas HUB completas
    └── optimización de assets
```

## Modos objetivo

### Legacy

Theme actual + Elementor continúan controlando la mayor parte de la página. HUB puede incorporar componentes puntuales.

### Híbrido

Header, Footer y determinados bloques son HUB. El contenido restante puede seguir viniendo de Elementor/WordPress.

### HUB

La página usa templates y componentes propios HTML/CSS/JS. Elementor puede dejar de cargarse en esa URL.

A futuro existirá un **HUB Tibox Theme opcional**, pero el plugin no debe depender de él. La misma instalación debe poder funcionar con Hello Elementor, themes existentes de clientes y un theme HUB futuro.

## Sitios iniciales

- `tibox.cl`: transición desde WordPress + Hello Elementor + Elementor.
- `prodata.cl`: transición desde WordPress + Elementor sin cambiar inicialmente su theme.

El núcleo del plugin debe ser reutilizable. Los comportamientos específicos de cada sitio deben resolverse mediante configuración/adaptadores y nunca mezclarse con el core genérico.

## Relación con Cloud-tibox

`wladimick/Cloud-tibox` es el antecedente directo de varias ideas de este proyecto:

- WordPress como backend.
- HTML/CSS/JS generado con IA.
- Design Packages.
- variables dinámicas `{{...}}`.
- preview y rollback.
- separación entre contenido y presentación.

La diferencia es que Cloud-tibox nació con un theme propio. Constructor HUB Tibox debe poder instalarse primero **sin cambiar el theme existente** y permitir una migración progresiva.

Ver [`docs/CLOUD-TIBOX-RELATION.md`](docs/CLOUD-TIBOX-RELATION.md).

## Estado actual

Versión de trabajo: `0.2.0`.

El código existente proviene del MVP llamado históricamente **Tibox AI Frontend**. Desde v0.2.0 la identidad pública pasa a **Constructor HUB Tibox**.

Por compatibilidad, algunos nombres internos (`TIBOX_AI_FRONTEND_*`, `TIBOX_AI_Frontend`, `home-ai`, etc.) todavía existen. No deben considerarse la arquitectura final. Su migración será gradual y documentada.

El MVP actual permite:

- reemplazar el template de una página seleccionada;
- mantener `wp_head()`, `wp_body_open()` y `wp_footer()`;
- conservar Rank Math/GTM/hooks globales;
- probar una home HTML/CSS/JS propia;
- descargar assets pesados de Elementor en modo agresivo;
- usar un formulario nativo conectado al endpoint REST existente de Tibox.

## Principios no negociables

- WordPress sigue siendo backend.
- La transición debe ser reversible.
- No romper páginas Elementor existentes.
- No obligar a cambiar el theme actual.
- HTML semántico, CSS nativo y JavaScript nativo por defecto.
- IA nunca debe recibir ni generar secretos/API keys.
- Los assets de un componente deben cargar solo cuando se utilizan.
- SEO y analítica se deben conservar durante la migración.
- Cada cambio importante debe quedar registrado con fecha, rama y commit.
- Una IA nueva debe poder comprender el proyecto leyendo `/docs` sin depender de conversaciones anteriores.

## Flujo de desarrollo

No desarrollar directamente en `main`.

```text
main
└── feat/... | fix/... | refactor/... | docs/...
      ├── implementación
      ├── documentación
      ├── QA
      └── Pull Request
```

Antes de cerrar un cambio se debe actualizar `docs/CHANGELOG.md` con el contexto suficiente para reconstruir qué se hizo y por qué.

## Roadmap resumido

1. Formalizar Constructor HUB Tibox y documentación.
2. Sistema global de Header/Footer reemplazables.
3. Biblioteca de componentes.
4. modos Legacy / Híbrido / HUB por página.
5. Design System por sitio.
6. Design Packages compatibles con Claude Design/ChatGPT.
7. preview, versionado y rollback.
8. adaptadores Tibox/Prodata.
9. eliminación selectiva de assets Elementor.
10. HUB Tibox Theme opcional para sitios 100% migrados.

Ver [`docs/ROADMAP.md`](docs/ROADMAP.md) para el detalle.
