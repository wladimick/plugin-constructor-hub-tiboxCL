# QA — Constructor HUB Tibox 0.3.0-beta.1

Fecha: 2026-08-28
Rama: `feat/hybrid-header-footer`
PR: `#2`
Commit de packaging compatible: `8993d3fd1308a20f5057c34073ce308b73fcf503`
Workflow run: `33199765996`

## Resultado CI

- PHP 8.0 syntax: PASS
- PHP 8.3 syntax: PASS
- JavaScript syntax / Node 22: PASS
- Build installable plugin ZIP: PASS
- Upload artifact: PASS

## Artifact válido para actualizar instalaciones existentes

Nombre: `constructor-hub-tibox-0.3.0-beta.1`
Artifact ID: `9697258159`
Digest informado por GitHub Actions:

`sha256:7dd9c607f124b41160f215ec4f2869bd140c6b8ad704b6659707aa08d97d2c9d`

El artifact contiene un ZIP instalable de WordPress cuya carpeta raíz es deliberadamente:

`tibox-ai-frontend/`

## Por qué se conserva ese nombre de carpeta

Las primeras instalaciones del MVP usaron `tibox-ai-frontend/`. Aunque el nombre público del producto ahora es **Constructor HUB Tibox**, cambiar la carpeta del ZIP a `constructor-hub-tibox/` haría que WordPress pudiera interpretarlo como un segundo plugin instalado en paralelo.

Eso podría cargar dos copias de las mismas clases PHP y provocar un error fatal.

Por compatibilidad de upgrade, la carpeta histórica se conserva temporalmente. El renombre físico del plugin se hará mediante una migración explícita futura.

## Artifact anterior descartado

El build generado desde `20928b6a8eba5f99f6849de6a68c7ed2c3ec2a85` utilizaba carpeta raíz `constructor-hub-tibox/`.

Ese artifact **no debe usarse para actualizar una instalación que ya tenga el MVP anterior**. Fue reemplazado por el build compatible generado desde `8993d3fd1308a20f5057c34073ce308b73fcf503`.

## Validación adicional del ZIP descargado

Se inspeccionó la estructura del paquete compatible y contiene:

- carpeta raíz `tibox-ai-frontend/`;
- bootstrap `tibox-ai-frontend.php` con versión `0.3.0-beta.1`;
- Component Manager;
- Hybrid Renderer;
- template híbrido;
- MVP full-page histórico para compatibilidad;
- documentación;
- ejemplos Tibox 2026 Header/Footer.

Además se volvió a ejecutar localmente sobre el contenido del ZIP descargado:

- `php -l` sobre todos los archivos PHP: PASS;
- `node --check` sobre todos los archivos JavaScript: PASS.

## QA todavía pendiente

Este documento valida integridad/sintaxis/build. No sustituye el QA funcional en WordPress.

Pendiente en `tibox.cl`:

1. actualizar la instalación existente con el ZIP compatible;
2. crear componentes Header/Footer;
3. aplicar solo a `/inicio-con-ia/`;
4. comprobar render Elementor central;
5. comprobar Rank Math/GTM;
6. comprobar responsive;
7. comprobar rollback desactivando modo híbrido.
