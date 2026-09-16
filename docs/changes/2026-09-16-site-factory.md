# Site Factory — flujo guiado para Pages HUB

Fecha: 2026-09-16

## Objetivo

Reducir la cantidad de pasos técnicos necesarios para transformar un package generado por IA en una Page WordPress administrable, sin sacrificar el fail-safe del proyecto.

Site Factory no reemplaza los objetos existentes de Constructor HUB. Los orquesta:

1. importa un package ZIP mediante `HUB_Tibox_Package`;
2. exige que `manifest.json` declare `type: page` para continuar con la asignación;
3. crea una Page WordPress o usa una existente;
4. asigna el diseño mediante `HUB_Tibox_Page_Assignment`;
5. mantiene `Theme / Elementor` como renderer por defecto;
6. opcionalmente publica la versión, publica el diseño y activa HUB si el usuario tiene permiso y lo solicita explícitamente.

## UX

Nuevo menú:

`Constructor HUB -> Crear página`

El formulario se divide en tres decisiones:

- ZIP de diseño generado por IA.
- Crear una Page nueva o seleccionar una existente.
- Mantener el renderer actual o publicar/activar HUB.

Después de preparar la página, Site Factory entrega accesos directos a:

- preview firmado en la URL de la Page;
- edición de la Page WordPress;
- edición del diseño HUB.

## Seguridad y fail-safe

- Importar un ZIP nunca activa HUB por sí solo.
- El usuario necesita `hub_edit_design_code` para usar Site Factory.
- Activar HUB requiere `hub_publish_designs` o `manage_options`.
- Publicar la Page WordPress requiere `publish_pages`.
- Si `Publish Guard` bloquea la versión, la Page permanece en modo `Theme / Elementor`.
- Una falla al publicar el post `hub_design` también impide el cambio de renderer.
- Elegir una Page existente no elimina Elementor ni modifica su contenido editorial.

## Pruebas automáticas

`tests/test-site-factory.php` cubre las reglas puras de activación:

- no activar sin solicitud explícita;
- no activar sin permisos;
- no activar si falla Publish Guard;
- no activar si el diseño no logra publicarse;
- publicar una Page solo cuando existe solicitud y permiso.

## QA real pendiente

Antes de producción se debe validar en staging:

1. package Page real exportado por Claude Design;
2. Page nueva en borrador;
3. Page existente creada con Elementor;
4. preview firmado conservando permalink y SEO;
5. activación HUB con Publish Guard aprobado;
6. activación bloqueada por un package inválido;
7. rollback a Theme / Elementor desde la Page;
8. persistencia del `content_schema` después de importar una segunda versión visual.

Este flujo sigue siendo parte de `0.5.0-dev`; no convierte la rama en release de producción.
