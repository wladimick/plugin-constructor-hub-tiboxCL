# ADR-0005 — Asignar un diseño HUB a una Page WordPress existente

- Estado: Aceptada
- Fecha: 2026-09-11
- Rama: `feat/page-assignment`
- Base: `feat/hub-v0.5-refundacion`
- Depende de: [ADR-0003](ADR-0003-design-unificado-y-versionado.md) y [ADR-0004](ADR-0004-insertion-api-y-regiones.md)

## Contexto

Tibox ya tiene páginas productivas construidas con Elementor y con URLs
indexadas, metadata Rank Math, ACF, analítica e integraciones ligadas al post de
WordPress. Crear una nueva `hub_design` de tipo `page` con URL propia no resuelve
la migración de esas páginas: obligaría a publicar otra URL o a introducir una
redirección 301 innecesaria.

El primer caso real es
`/ciberseguridad/gestion-monitoreo-seguridad-soc/`. El objetivo es poder
rediseñarla con IA y Constructor HUB sin cambiar su permalink ni borrar su
contenido Elementor mientras se valida el reemplazo.

## Decisión

### La Page existente conserva la identidad

La Page de WordPress sigue siendo el objeto público y conserva:

- post ID;
- permalink;
- estado;
- Rank Math, canonical, Open Graph y schema;
- imagen destacada, ACF y cualquier metadata del post;
- contenido Elementor como fallback y rollback.

La Page recibe dos metas escalares:

```text
_hub_assigned_design_id
_hub_page_render_mode = theme | hub
```

El diseño asignado debe ser un `hub_design` de tipo `page`.

### Activación explícita y fail-safe

El default es `theme`: Elementor/theme sigue renderizando exactamente como
antes.

`hub` solo toma la URL si el diseño asignado:

1. sigue existiendo;
2. es de tipo `page`;
3. está publicado;
4. tiene una versión live utilizable.

Si cualquiera de esas condiciones deja de cumplirse, la URL vuelve al renderer
del theme en lugar de quedar en blanco.

### Preview sobre la URL real antes del cutover

Una versión HUB puede previsualizarse mediante el sistema firmado existente,
pero usando como base el permalink de la Page original. Eso permite revisar la
composición, Rank Math, GTM y comportamiento real de esa URL sin cambiar aún
`_hub_page_render_mode`.

El preview no altera la asignación guardada y emite `noindex,nofollow`.

### Shell propio para la Page asignada

Cuando HUB toma el documento, `templates/assigned-page.php` preserva
`wp_head()`, `wp_body_open()` y `wp_footer()`. El queried object sigue siendo la
Page original; el diseño HUB solo reemplaza el cuerpo visual.

Por eso el SEO y las integraciones que dependen del post actual siguen
resolviendo contra la URL existente.

### Elementor no se borra

El adaptador Elementor informa que la Page ya no necesita sus assets solo cuando
la asignación HUB está activa y es utilizable. Los datos de Elementor no se
modifican. Volver el selector a `theme` es el rollback inmediato.

La retirada selectiva de assets sigue dependiendo además del optimizador
global, que permanece apagado por defecto.

## Alternativas consideradas

### Crear una nueva Page/landing HUB y redirigir la URL histórica

Rechazada para migraciones normales: cambia innecesariamente la identidad del
contenido y añade riesgo SEO, de tracking y de integraciones.

### Copiar HTML/CSS/JS directamente dentro de la Page original

Rechazada: rompería el modelo de versionado de ADR-0003 y volvería a mezclar
identidad WordPress con payload visual.

### Sobrescribir el contenido Elementor al activar HUB

Rechazada: eliminaría el rollback más seguro. El contenido legacy debe
permanecer intacto hasta cerrar QA y una eventual limpieza posterior explícita.

## Consecuencias

Positivas:

- una página indexada puede migrarse sin cambiar URL;
- el cutover es un selector reversible;
- preview y producción usan la misma Page y el mismo contexto SEO;
- Elementor puede retirarse URL por URL, no sitio completo;
- el mapa de migración puede mostrar qué Page ya está completamente asignada a
  HUB.

Costos:

- existe una relación adicional Page → `hub_design`;
- el shell de Page asignada debe mantenerse compatible con el renderer general;
- el QA real debe verificar Rank Math, GTM, formularios, responsive y rollback
  sobre una copia de Tibox antes de usarlo en producción.

## QA obligatorio antes de producción

Sobre una copia de `tibox.cl`:

1. asignar un diseño de prueba a la página SOC manteniendo modo `theme`;
2. abrir preview firmado sobre la URL original;
3. comprobar title, description, canonical, Open Graph, schema y GTM;
4. activar `hub` y verificar que la URL no cambia;
5. confirmar que Elementor deja de ser necesario solo en esa URL;
6. volver a `theme` y comprobar rollback inmediato;
7. repetir en desktop y mobile, con caché limpia.

## Referencias

- `includes/class-hub-page-assignment.php`
- `templates/assigned-page.php`
- `includes/adapters/class-hub-elementor-adapter.php`
- `includes/class-hub-migration-map.php`
- `docs/ARCHITECTURE.md`
