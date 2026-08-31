# Cambio — Header/Footer HUB híbridos

- Fecha: 2026-08-28
- Rama: `feat/hybrid-header-footer`
- Estado: desarrollo / QA pendiente en WordPress real
- Baseline `main`: `3944fa9f37ee4fe897883abecae39dcc5656fea7`

## Objetivo

Crear la primera capacidad funcional de Constructor HUB Tibox posterior a la formalización v0.2.0:

```text
Header     Constructor HUB
Contenido  WordPress / Elementor existente
Footer     Constructor HUB
```

sin cambiar el theme ni descargar assets de Elementor mientras el contenido central todavía pueda necesitarlos.

## Commits de implementación

### `b18b77535349db25807840fba287b1d81c58e8fe`

`feat: add HUB header footer component manager`

Añade `includes/class-hub-component-manager.php`.

Responsabilidades:

- registra CPT privado `hub_component`;
- componentes de tipo `header` y `footer`;
- campos separados HTML/CSS/JavaScript;
- solo usuarios con `unfiltered_html` pueden guardar código visual sin filtrado;
- variables dinámicas iniciales;
- selección de Header/Footer publicados activos;
- activación/desactivación del renderer híbrido;
- alcance `selected` o `all_pages`;
- selección múltiple de páginas para pruebas controladas.

Variables iniciales:

- `{{SITE_URL}}`
- `{{HOME_URL}}`
- `{{SITE_NAME}}`
- `{{CURRENT_YEAR}}`
- `{{CUSTOM_LOGO}}`
- `{{MENU_PRIMARY}}`
- `{{MENU_FOOTER}}`

### `61e8251c637e5e8d13bdffaebf4a1b0e8ddb2ae9`

`feat: add hybrid page renderer for HUB components`

Añade `includes/class-hub-hybrid-renderer.php`.

Responsabilidades:

- detecta Pages dentro del alcance configurado;
- respeta el MVP full-page histórico si `_tibox_ai_frontend_enabled=1`;
- usa un template híbrido propio;
- agrega clases body `constructor-hub-tibox` y `hub-render-mode-hybrid`;
- imprime CSS de Header/Footer en `wp_head`;
- imprime JS de Header/Footer en `wp_footer`;
- deliberadamente NO elimina assets Elementor/theme.

### `96c40fb0719648a07cb0de86d77d9b24b9f0d942`

`feat: add hybrid page template`

Añade `templates/hybrid-page.php`.

Estructura:

```text
<!doctype html>
wp_head()
wp_body_open()
Header HUB
<main>
  the_content()
</main>
Footer HUB
wp_footer()
```

`the_content()` mantiene el punto de integración con Elementor y filtros WordPress existentes.

### `713fb0cb2a8d874445a813c096de90b8a78cbe16`

`feat: wire hybrid component system into plugin`

- versión de desarrollo `0.3.0-dev`;
- carga Component Manager;
- carga Hybrid Renderer;
- conserva el core MVP histórico de página completa.

### `b8432516566b4544125964cda2e403ebd0df70b2`

`ci: add PHP and JavaScript syntax validation`

Añade `.github/workflows/ci.yml`.

CI esperado:

- PHP lint 8.0;
- PHP lint 8.3;
- `node --check` para JavaScript.

## Antes

El plugin solo podía sustituir una página completa mediante el MVP `Tibox AI Frontend`. Esto no permitía mantener el contenido Elementor existente entre Header/Footer propios.

## Después

Existe una base genérica para almacenar y activar componentes Header/Footer y un renderer híbrido para Pages.

El modo está desactivado por defecto y requiere:

1. Header publicado;
2. Footer publicado;
3. ambos seleccionados en Configuración;
4. renderer híbrido activado;
5. Page dentro del alcance configurado.

## Compatibilidad

### Theme

No cambia el theme activo.

El template híbrido sí evita utilizar el template de página del theme en las Pages seleccionadas, pero conserva sus CSS/JS cargados por WordPress para reducir riesgo durante la transición.

### Elementor

No se desactiva ni se descargan sus assets en modo Híbrido.

El contenido se obtiene mediante `the_content()`, donde Elementor puede continuar procesando la página.

### MVP histórico

Si una página mantiene `_tibox_ai_frontend_enabled=1`, el renderer híbrido no la intercepta.

## Seguridad

- campos guardados con nonce y capabilities;
- solo usuarios con `unfiltered_html` pueden guardar HTML/CSS/JS sin filtrar;
- el código visual no evalúa PHP;
- se documenta expresamente no almacenar secretos.

Riesgo asumido: HTML/JS de componentes es código confiable de administradores. En fases futuras se evaluará un importador de Design Packages con validación más estricta.

## QA

### Ejecutado

- revisión estructural del código;
- CI agregado para validación reproducible en GitHub.

### Pendiente

- resultado efectivo del workflow GitHub Actions;
- instalación en WordPress de prueba;
- probar Page Elementor real;
- verificar Header/Footer sin duplicados;
- desktop/mobile;
- Rank Math;
- GTM/dataLayer;
- consola JS;
- revertir a Legacy desactivando el checkbox.

## Limitaciones v0.3 inicial

- solo Pages;
- sin preview privado todavía;
- sin rollback/version selector dedicado más allá de seleccionar otro componente publicado;
- sin Design System;
- sin import ZIP;
- menú dinámico depende de ubicaciones `primary` y `footer` si el HTML usa esas variables;
- no existe todavía adaptador Elementor especializado para Theme Builder Header/Footer.

## Próximos pasos

1. completar CI;
2. agregar preview seguro para administradores;
3. crear adaptador Elementor para evitar Header/Footer duplicados;
4. crear primer Header/Footer Tibox como componentes de prueba, sin hardcodearlos en el core;
5. probar en `/inicio-con-ia/` u otra Page de staging antes de alcance global;
6. posteriormente repetir con Prodata.