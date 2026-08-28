# QA — Constructor HUB Tibox 0.3.0-beta.1

Fecha: 2026-08-28
Rama: `feat/hybrid-header-footer`
PR: `#2`
Commit de packaging: `20928b6a8eba5f99f6849de6a68c7ed2c3ec2a85`
Workflow run: `33199638327`

## Resultado CI

- PHP 8.0 syntax: PASS
- PHP 8.3 syntax: PASS
- JavaScript syntax / Node 22: PASS
- Build installable plugin ZIP: PASS
- Upload artifact: PASS

## Artifact

Nombre: `constructor-hub-tibox-0.3.0-beta.1`
Artifact ID: `9697207727`
Digest informado por GitHub Actions:

`sha256:4877c7172f0e78d2b636e17bd962885cebc92fc8cf8b7b27e84a4cd3266af731`

El artifact contiene un ZIP instalable de WordPress con carpeta raíz:

`constructor-hub-tibox/`

## Validación adicional del ZIP descargado

Se inspeccionó la estructura del paquete generado y contiene:

- bootstrap `tibox-ai-frontend.php` con versión `0.3.0-beta.1`;
- Component Manager;
- Hybrid Renderer;
- template híbrido;
- MVP full-page histórico para compatibilidad;
- documentación;
- ejemplos Tibox 2026 Header/Footer.

Además se volvió a ejecutar localmente sobre el contenido del ZIP:

- `php -l` sobre todos los archivos PHP: PASS;
- `node --check` sobre todos los archivos JavaScript: PASS.

## QA todavía pendiente

Este documento valida integridad/sintaxis/build. No sustituye el QA funcional en WordPress.

Pendiente en `tibox.cl`:

1. actualizar/instalar beta;
2. crear componentes Header/Footer;
3. aplicar solo a `/inicio-con-ia/`;
4. comprobar render Elementor central;
5. comprobar Rank Math/GTM;
6. comprobar responsive;
7. comprobar rollback desactivando modo híbrido.
