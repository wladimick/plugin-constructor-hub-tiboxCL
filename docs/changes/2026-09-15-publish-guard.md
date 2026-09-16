# 2026-09-15 — Publish Guard para diseños HUB

Rama: `feat/hub-publish-guard`  
Base: `feat/hub-content-schema`  
Estado: **implementación / QA WordPress real pendiente**.

## Objetivo

Añadir una revisión automática antes de que una versión HTML/CSS/JS generada o
modificada con IA pase a producción.

## Implementación

Se añadió `HUB_Tibox_Publish_Guard` con:

- errores estructurales que bloquean publicación;
- advertencias informativas para SEO, accesibilidad, seguridad y performance;
- caja **Revisión antes de publicar** en el editor de diseños;
- aviso administrativo con los motivos concretos cuando una publicación queda
  bloqueada;
- filtro central `constructor_hub_publish_allowed` ejecutado desde
  `HUB_Tibox_Version_Store::publish()`.

### Bloqueadores

- diseño sin HTML utilizable;
- variable fija no registrada;
- `CONTENT.*` no declarado en `content_schema`.

### Advertencias

- Page/Landing sin exactamente un H1;
- imágenes sin `alt`;
- campos `required` sin valor base;
- eventos JS inline;
- scripts/iframes externos;
- patrones JS que exigen revisión manual (`eval`, `new Function`, cookies,
  WebSocket, fetch externo, XMLHttpRequest);
- documento completo dentro de un tipo que normalmente usa shell HUB.

## Criterio de bloqueo

Solo los errores de alta certeza impiden `publish()`. Las advertencias nunca
bloquean porque el mismo método también participa en rollback y migraciones.

## QA automático

`tests/test-publish-guard.php` cubre:

- página limpia;
- `CONTENT.*` no declarado;
- variable fija desconocida;
- H1 ausente;
- imagen sin alt;
- JavaScript de revisión manual;
- HTML vacío;
- required vacío como warning.

## Pendiente

- CI completo de la rama;
- integración en WordPress real;
- comprobar notices durante guardar/publicar/rollback;
- validar con un package real exportado por Claude Design;
- ejecutar Lighthouse y validaciones SEO por fuera del guard.
