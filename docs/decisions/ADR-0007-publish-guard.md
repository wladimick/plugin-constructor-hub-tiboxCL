# ADR-0007 — Publish Guard antes de mover una versión a producción

- Estado: Aceptada
- Fecha: 2026-09-15
- Rama: `feat/hub-publish-guard`
- Base: `feat/hub-content-schema`
- Depende de: ADR-0003, ADR-0005 y ADR-0006

## Contexto

Constructor HUB permite que una IA entregue HTML/CSS/JS y que una versión se
publique con un clic. El versionado y rollback reducen riesgo, pero no evitan
publicar un diseño que ya contiene un error estructural visible: una variable no
registrada, un `CONTENT.*` sin schema o un documento vacío.

Además existen señales que no deben bloquear de forma automática, pero sí deben
quedar visibles antes de publicar: H1 ausente o duplicado, imágenes sin `alt`,
JavaScript con APIs de mayor riesgo, scripts/iframes externos y manejadores
inline.

## Decisión

Se introduce `HUB_Tibox_Publish_Guard` como diagnóstico previo a publicación.

El reporte tiene dos niveles:

### Error — bloquea publicación

Solo se usa para problemas estructurales con alta certeza:

- HTML vacío cuando el tipo necesita contenido;
- variables `{{VARIABLE}}` no registradas;
- `{{CONTENT.*}}` usado pero no declarado en `content_schema`.

La decisión se aplica en `HUB_Tibox_Version_Store::publish()` mediante el filtro
`constructor_hub_publish_allowed`, de modo que los distintos caminos de
publicación no puedan saltarse accidentalmente el mismo control.

### Advertencia — no bloquea

Son señales que requieren contexto humano:

- Page/Landing sin exactamente un H1;
- imágenes sin atributo `alt`;
- campos editoriales `required` sin valor base;
- manejadores JavaScript inline;
- `script` o `iframe` externos;
- `eval`, `new Function`, `document.cookie`, WebSocket, `fetch()` externo o
  XMLHttpRequest en JavaScript;
- `html`, `head` o `body` dentro de un Page/Landing que normalmente usa el shell
  HUB.

No se bloquean porque pueden existir casos válidos o porque una Page asignada
puede completar el contenido mediante overrides editoriales.

## UX

Cada diseño HUB recibe una caja **Revisión antes de publicar** con errores y
advertencias del working version.

Si una publicación se bloquea, se muestra un aviso administrativo con los
hallazgos concretos. El usuario corrige el diseño, guarda otra versión y vuelve
a publicar.

## Límites deliberados

Publish Guard no reemplaza:

- Rank Math/Yoast;
- Lighthouse/Core Web Vitals;
- auditoría WCAG;
- revisión de CSP;
- escáner de malware;
- QA visual y responsive;
- pruebas de formularios y conversiones.

Es una barrera temprana contra fallos obvios en código generado por IA, no una
certificación de calidad.

## Seguridad de rollback y migraciones

`Version_Store::publish()` también se usa para rollback y algunos flujos de
migración. Por eso el gate central solo bloquea errores estructurales de alta
certeza. Las advertencias nunca devuelven `false`.

Esto evita convertir un criterio editorial subjetivo en un bloqueo operativo de
recuperación.

## Consecuencias

Positivas:

- una variable inventada por la IA no llega a producción como texto literal;
- el editor ve señales SEO/accesibilidad/seguridad antes del cutover;
- todos los caminos de publicación comparten un gate mínimo;
- no se obliga a instalar otro plugin para el chequeo estructural.

Costos:

- los chequeos basados en regex son deliberadamente conservadores;
- no interpretan JavaScript ni DOM como un navegador;
- QA WordPress real sigue siendo obligatorio.

## QA obligatorio

1. publicación limpia debe continuar normalmente;
2. variable fija desconocida debe bloquear;
3. `CONTENT.*` no declarado debe bloquear;
4. Page sin H1 debe publicar con advertencia;
5. imagen sin `alt` debe publicar con advertencia;
6. JavaScript riesgoso debe advertir sin bloquear;
7. rollback de una versión estructuralmente válida debe seguir funcionando;
8. migraciones históricas válidas no deben quedar bloqueadas por advertencias.

## Referencias

- `includes/class-hub-publish-guard.php`
- `includes/class-hub-version-store.php`
- `tests/test-publish-guard.php`
