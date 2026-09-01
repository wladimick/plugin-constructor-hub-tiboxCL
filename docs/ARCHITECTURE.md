# Arquitectura — Constructor HUB Tibox

Fecha base: 2026-08-28. Reescrita: 2026-08-31 tras la auditoría integral.

Decisiones vigentes:
[ADR-0001](decisions/ADR-0001-core-independent-theme-elementor.md),
[ADR-0003](decisions/ADR-0003-design-unificado-y-versionado.md),
[ADR-0004](decisions/ADR-0004-insertion-api-y-regiones.md).
[ADR-0002](decisions/ADR-0002-landings-cpt-native-forms.md) queda sustituida
parcialmente por ADR-0003.

## Objetivo arquitectónico

Permitir que WordPress conserve sus capacidades de backend mientras la
presentación migra progresivamente a componentes HTML/CSS/JS administrados por
Constructor HUB.

## Capas

```text
┌──────────────────────────────────────────────────────────────────────┐
│  WordPress                                                           │
│  contenido · medios · usuarios · SEO (Rank Math) · GTM · wp_mail     │
└───────────────────────────────┬──────────────────────────────────────┘
                                │
┌───────────────────────────────▼──────────────────────────────────────┐
│  CONSTRUCTOR HUB — CORE (genérico, sin branding, sin Elementor)      │
│                                                                      │
│  ┌── Design Object ────────────────────────────────────────────┐    │
│  │  CPT  hub_design        identidad · título · slug · SEO      │    │
│  │  meta _hub_type         header|footer|menu|hero|section|      │    │
│  │                         form|landing|page                     │    │
│  │  meta _hub_current_version → fila de la tabla de versiones    │    │
│  └───────────────────────────┬─────────────────────────────────┘    │
│                              │                                       │
│  ┌── Version Store ──────────▼─────────────────────────────────┐    │
│  │  wp_hub_design_versions                                      │    │
│  │  id · design_id · version · status(draft|live|archived)      │    │
│  │  html · css · js · manifest · asset_dir · entry · checksum   │    │
│  │  publicar = mover el puntero. rollback = moverlo atrás.      │    │
│  └──────────────────────────────────────────────────────────────┘    │
│                                                                      │
│  Package Pipeline     ZIP → validar → manifest → versión borrador    │
│  Variable Registry    UN contrato {{...}}, versionado y validado     │
│  Design System        tokens --hub-* por sitio, impresos una vez     │
│  Asset Compiler       CSS/JS a archivos con hash + scope opcional    │
│  Render Engine        región · fragmento · documento · package       │
│  Insertion API        shortcode · bloque · widget · función          │
│  Forms Backend        REST · antispam · idempotencia · correo        │
│  Lead Store           tabla propia · export · retención · privacidad │
│  Capabilities         hub_manage_designs · hub_manage_leads · …      │
│  Migration Map        qué renderiza cada URL y de qué depende        │
└───────────────────────────────┬──────────────────────────────────────┘
                                │
┌───────────────────────────────▼──────────────────────────────────────┐
│  ADAPTERS (opcionales)                                               │
│  elementor · antispam · CRM/WebOps · wpcode-legacy                   │
└───────────────────────────────┬──────────────────────────────────────┘
                                │
┌───────────────────────────────▼──────────────────────────────────────┐
│  SITE CONFIG   tokens · logos · destinatarios · adapters activos     │
│  tibox.cl · prodata.cl · cliente-n                                   │
└───────────────────────────────┬──────────────────────────────────────┘
                                │
┌───────────────────────────────▼──────────────────────────────────────┐
│  THEME   el actual del sitio  →  HUB Theme opcional                  │
└──────────────────────────────────────────────────────────────────────┘
```

## Dónde vive cada dato

| Dato | Dónde | Por qué |
| --- | --- | --- |
| Landing o componente como objeto: título, slug, estado, autor, permalink, SEO | **CPT** | Es lo que WordPress hace bien. Reinventarlo es puro costo. |
| Versiones del código visual | **Tabla propia** | Filas inmutables, comparables. Las revisiones de WP no cubren post meta. |
| Assets de un package | **Filesystem** + fila de versión | Los sirve el servidor web, no PHP. |
| Configuración por objeto: modo, chrome, destinatarios, campaña | **Post meta** | Pocos valores escalares que se leen con el post. |
| Leads | **Tabla propia** | Alto volumen, campos fijos, filtrado, exportación, retención y borrado selectivo. |
| Entregas de correo | **Tabla propia** | Necesarias para depurar "el lead entró pero no llegó el correo". |
| Design System | **Option** + CSS generado | Un registro por sitio, leído en cada request. |

## Modos de renderizado

### Región

Header y Footer se configuran por separado:

- **`theme`**: sin cambios.
- **`inject`**: se conserva la plantilla del theme y el diseño HUB se coloca por
  `wp_body_open` / `wp_footer`, con un selector CSS **configurado** para ocultar
  la región del theme. Es el modo seguro sobre un theme desconocido y el que
  permite migrar una sola región.
- **`replace`**: Constructor HUB entrega el documento completo. Una región en
  modo `theme` no se imprime en este modo.

### Fragmento

Un diseño insertado dentro de contenido que el HUB no controla, mediante
shortcode, bloque, widget de Elementor o `constructor_hub_render()`. Es el
mecanismo de la migración por piezas.

### Documento

Los tipos `landing` y `page` tienen URL propia y pueden renderizarse como:

- **HUB**: fragmento dentro de `templates/hub-shell.php`;
- **documento completo**: HTML íntegro de una IA, con los hooks de WordPress
  inyectados de vuelta;
- **package**: servido desde su carpeta con `<base>` para que las rutas
  relativas funcionen.

En todos los casos se conservan `wp_head()`, `wp_body_open()` y `wp_footer()`.
Es lo que mantiene SEO, analítica e integraciones sin usar el markup del theme.

## Contrato para la IA

Un componente generado por IA es un ZIP con `manifest.json`, `index.html`,
`style.css` opcional, `script.js` opcional y `assets/`. El manifest declara
tipo, versión, variables y scope; las variables se validan contra el registro
real del sitio y una desconocida aborta la importación en lugar de imprimirse
literalmente en una página.

El contrato completo está en [`AI-PACKAGE-CONTRACT.md`](AI-PACKAGE-CONTRACT.md);
el de formularios y tracking, en
[`FORMS-AND-TRACKING.md`](FORMS-AND-TRACKING.md).

Un package **nunca** se publica al importarse: entra como versión borrador que
hay que previsualizar y publicar explícitamente.

## Aislamiento

El CSS de un diseño puede prefijarse automáticamente con su clase de scope
(`HUB_Tibox_Css_Scoper`), lo que resuelve la mayoría de las colisiones con el
theme y con Elementor. Es opcional por diseño y está desactivado en los
migrados, porque se escribieron sin él.

El CSS y el JS de la versión publicada se compilan a archivos en `uploads` y se
encolan: caché de navegador, versionado por URL y compatibilidad con CSP. Si el
filesystem no es escribible, se vuelve a la salida inline.

## Design System

Cada sitio define sus tokens `--hub-*`. Un componente correcto referencia
`var(--hub-primary)`, nunca `#0f172a`. Es lo que permite mover un componente de
tibox.cl a prodata.cl cambiando configuración y no código.

El core no contiene la identidad visual de ningún cliente. Si cambiar un logo,
un color o una URL normal requiere editar el core, la separación está mal hecha.

## Elementor

Todo lo que sabe que Elementor existe vive en `includes/adapters/` y no hace
nada cuando Elementor no está. El adaptador aporta el widget, la detección de
Theme Builder y el filtro `constructor_hub_elementor_needed`, que responde si
una página todavía necesita Elementor a partir del modo de edición guardado y
del markup real.

La retirada de assets se apoya en ese inventario, está desactivada por defecto y
nunca elimina un handle del que dependa algo todavía encolado. Adivinar por
nombre de handle —lo que hacía el MVP— es cómo una página pierde una
funcionalidad sin que nadie se entere.

## Seguridad

- El código de diseño es HTML, CSS y JavaScript servidos en el origen del sitio:
  editarlo exige la capacidad `hub_edit_design_code`, no el rol de Editor.
- Los packages se validan antes de escribir un solo byte: traversal, symlinks,
  allowlist de extensiones, archivos de configuración bloqueados, límites de
  tamaño y verificación del tamaño real descomprimido.
- Todo SVG se sanea en la importación.
- PHP nunca se acepta dentro de un package ni se evalúa desde un diseño.
- Los paquetes no pueden contener secretos, y el core no almacena credenciales
  de ningún proveedor.

## Rendimiento

- CSS y JS por diseño y por destino, en archivos cacheables.
- El runtime del formulario solo se carga en páginas que contienen un formulario.
- JavaScript nativo por defecto; ninguna librería por comodidad.
- La retirada de assets de terceros se basa en inventario, nunca a ciegas.

## Compatibilidad

- El default mantiene el comportamiento existente; activar HUB es explícito.
- La migración a diseños unificados corre en dos fases: primero copia cada
  objeto histórico a un `hub_design` en borrador (stage); solo si el lote
  completo no tuvo fallos, publica los nuevos diseños y retira los históricos
  (cutover). Una migración parcial nunca activa el modelo unificado ni deja
  contenido invisible: el sitio sigue exactamente como estaba.
- La unificación es reversible con `HUB_Tibox_Upgrade::rollback_to_legacy()`
  (`Constructor HUB → Diagnóstico` o `wp hub rollback-to-legacy`), que restaura
  el estado que tenía cada objeto histórico antes del cutover, no solo apaga
  una opción.
- Los post types históricos siguen registrados y sus datos intactos.
- El HUB Theme es opcional y no se empaqueta con el plugin.
