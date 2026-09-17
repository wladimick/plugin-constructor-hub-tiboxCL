# Package Upload Guard — 2026-09-17

## Objetivo

Rechazar Design Packages ZIP excesivamente grandes antes de que WordPress los almacene y antes de ejecutar extracción.

## Cobertura

El guard se aplica a los flujos administrativos HUB que usan `wp_handle_upload()`:

- Site Factory (`hub_tibox_site_factory_create`);
- Redesign Workflow (`hub_tibox_redesign_page`);
- Importador de Design Packages (`hub_tibox_import_package`).

## Política por defecto

- extensión requerida: `.zip`;
- tamaño comprimido máximo: 25 MB;
- archivo vacío: rechazado.

El límite reutiliza el filtro existente:

`constructor_hub_zip_max_compressed_bytes`

La extracción segura mantiene además sus límites independientes de archivos, tamaño total descomprimido, tamaño por archivo, extensiones permitidas, symlinks, path traversal y archivos de configuración bloqueados.

## Razón

La validación de contenido del ZIP ocurre durante la extracción, pero para ese momento WordPress ya recibió el upload. Este guard agrega una defensa temprana para reducir consumo innecesario de disco, memoria y tiempo de request, especialmente en staging y en sitios compartidos.
