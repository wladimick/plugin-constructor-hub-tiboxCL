# Arquitectura — Constructor HUB Tibox

Fecha base: 2026-08-28.

## Objetivo arquitectónico

Permitir que WordPress conserve sus capacidades de backend mientras la presentación migra progresivamente a componentes y páginas HTML/CSS/JS administrados por Constructor HUB Tibox.

## Capas

```text
┌──────────────────────────────────────────────┐
│ WordPress backend                            │
│ páginas · entradas · medios · usuarios       │
│ SEO · REST · formularios · integraciones     │
└──────────────────────────────────────────────┘
                     │
┌──────────────────────────────────────────────┐
│ Constructor HUB Tibox                        │
│                                              │
│ Core                                         │
│ ├─ configuración                             │
│ ├─ renderer                                  │
│ ├─ variables dinámicas                       │
│ ├─ registry de componentes                   │
│ ├─ assets                                    │
│ ├─ preview/versionado                        │
│ └─ compatibilidad                            │
│                                              │
│ Presentación                                 │
│ ├─ Header                                    │
│ ├─ Footer                                    │
│ ├─ bloques                                   │
│ ├─ páginas                                   │
│ └─ Design Packages                           │
│                                              │
│ Adaptadores                                  │
│ ├─ Elementor                                 │
│ ├─ Tibox                                     │
│ ├─ Prodata                                   │
│ └─ futuros sitios/themes                     │
└──────────────────────────────────────────────┘
                     │
┌──────────────────────────────────────────────┐
│ Theme                                        │
│ actual del sitio hoy / HUB Theme futuro      │
└──────────────────────────────────────────────┘
```

## Principio central: core independiente

El core de Constructor HUB no puede asumir:

- que Elementor está instalado;
- que Hello Elementor es el theme;
- que el sitio usa colores/logo Tibox;
- que Rank Math es siempre el plugin SEO;
- que el formulario pertenece a Tibox;
- que existe WPCode.

Esas integraciones deben resolverse mediante detección, configuración o adaptadores.

## Modos de renderizado

### 1. Legacy

El theme actual y Elementor continúan renderizando normalmente.

HUB puede:

- insertar bloques vía shortcode/hook;
- preparar preview;
- administrar Design System/componentes sin sustituir la página.

### 2. Híbrido

HUB controla partes globales o locales sin eliminar todavía el render existente.

Ejemplo:

```text
HEADER     HUB
MAIN       Elementor / the_content()
FOOTER     HUB
```

También puede ocurrir:

```text
HEADER     HUB
HERO       HUB
RESTO      Elementor
FOOTER     HUB
```

Este será el modo principal de transición de Tibox y Prodata.

### 3. HUB

HUB controla el template de la URL completa.

El shell debe conservar los hooks WordPress necesarios:

- `wp_head()`;
- `wp_body_open()`;
- `wp_footer()`.

Esto permite mantener SEO, analítica e integraciones sin utilizar necesariamente el markup del theme.

En este modo se pueden descargar assets de Elementor de forma selectiva y segura.

## Theme actual vs HUB Theme futuro

### Hoy

Constructor HUB debe funcionar encima del theme existente.

### Futuro

Puede existir un **HUB Tibox Theme** ultraliviano que implemente únicamente el chasis WordPress:

- `header.php`/shell;
- `footer.php`/shell;
- `page.php`;
- `single.php`;
- archives;
- 404;
- hooks y soporte WordPress.

El contenido visual seguirá viviendo en Constructor HUB, por lo que cambiar desde Hello Elementor hacia HUB Theme no debe obligar a reconstruir componentes.

## Component Registry objetivo

Cada componente tendrá identidad y versión.

Ejemplo conceptual:

```text
components/
└─ header/
   └─ corporate-v2/
      ├─ manifest.json
      ├─ index.html
      ├─ style.css
      ├─ script.js
      └─ assets/
```

Tipos previstos:

- header;
- footer;
- hero;
- navigation;
- section;
- CTA;
- form;
- cards/grids;
- page package.

## Design Packages

Se tomará como antecedente el sistema de `Cloud-tibox`.

Formato objetivo:

```text
package.zip
├─ manifest.json
├─ index.html
├─ style.css
├─ script.js
└─ assets/
```

Características requeridas:

- validación y extracción segura;
- preview sin publicar;
- versionado;
- rollback;
- asignación por destino;
- assets relativos;
- CSS/JS cargado solo en destinos activos;
- sin PHP arbitrario dentro del paquete.

## Variables dinámicas

La IA debe diseñar contra un contrato de variables y no hardcodear datos que WordPress conoce.

Base propuesta:

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

Las variables específicas se deben agrupar por dominio y documentar.

## Design System

Cada sitio debe tener tokens configurables, no estilos Tibox dentro del core.

Ejemplo:

```css
--hub-primary
--hub-secondary
--hub-accent
--hub-text
--hub-background
--hub-font-heading
--hub-font-body
--hub-container
--hub-radius-sm
--hub-radius-md
--hub-radius-lg
--hub-section-space
```

Los componentes generados por IA deben preferir estos tokens.

## Adaptador Elementor

Responsabilidades futuras:

- detectar Elementor/Elementor Pro;
- identificar si una URL todavía necesita Elementor;
- evitar descargar assets mientras una sección dependa de ellos;
- en modo HUB completo, descargar CSS/JS no utilizados;
- manejar Theme Builder Header/Footer cuando corresponda;
- proporcionar una salida reversible.

Nunca modificar directamente contenido Elementor sin una acción explícita.

## Compatibilidad SEO/analítica

Constructor HUB no debe intentar sustituir automáticamente el plugin SEO.

Debe preservar los hooks del `<head>` y footer necesarios.

Durante pruebas paralelas:

- evitar contenido duplicado indexable;
- usar `noindex` en prototipos cuando corresponda;
- validar canonical;
- validar Open Graph/schema;
- validar GTM/dataLayer.

## Formularios

El frontend HUB debería poder usar formularios HTML propios y enviar datos a servicios/backend WordPress.

Los endpoints, lógica de negocio y destinos deben desacoplarse del HTML del componente.

El formulario del MVP Tibox es un caso específico y no debe convertirse en dependencia del core.

## Seguridad

Los paquetes visuales no pueden ejecutar PHP arbitrario.

Nunca almacenar en paquetes:

- API keys;
- passwords;
- tokens;
- secretos de webhooks;
- credenciales de servicios.

Sanitizar HTML/configuración y validar uploads.

## Rendimiento

Principios:

- CSS y JS por componente/destino;
- JavaScript nativo por defecto;
- no incluir librerías por comodidad si no son necesarias;
- imágenes responsive/lazy salvo recurso LCP;
- evitar render blocking innecesario;
- no descargar assets de terceros a ciegas: validar dependencia real.

## Estado heredado 0.1 → 0.2

El MVP `Tibox AI Frontend` implementó primero páginas completas y eliminación agresiva de assets.

Constructor HUB cambia la dirección hacia componentes e hibridación antes de páginas completas.

Nombres internos heredados se conservarán temporalmente por compatibilidad y serán migrados con ADR/changelog.