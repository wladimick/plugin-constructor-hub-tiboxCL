# Changelog técnico

Este archivo registra cambios de desarrollo relevantes para que una persona o IA pueda reconstruir la evolución del proyecto.

Formato requerido desde 2026-08-28: **fecha · rama · commit · objetivo · impacto · QA/deuda**.

---

## 2026-09-01 — Tercera revisión del PR #7: stage transaccional

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Origen: quinto hallazgo de la segunda auditoría sobre el PR #7, encontrado
después de corregir los cuatro primeros (rollback, migración parcial, menú de
Correo, orden de arranque).

### El problema

`stage_component()`/`stage_landing()` llamaban a `seed_version()` y a
`move_package_directory()` y devolvían `status=created` sin mirar qué
devolvían. `seed_version()` era `void`: si
`HUB_Tibox_Version_Store::create()` devolvía `0` por un error de base de
datos, o si `publish()` devolvía `false`, el ítem se reportaba igual como
`created`, con un diseño sin versión utilizable. Lo mismo con
`HUB_Tibox_Filesystem::copy_directory()`: su valor de retorno (`bool`) se
ignoraba, así que una copia de package fallida dejaba un `entry` apuntando a
archivos que nunca se copiaron.

Con la migración en dos fases ya introducida, esto era peor que un bug
cosmético: un ítem mal clasificado como `created` hace que
`evaluate_migration_result()` lo cuente como éxito, lo que podía activar el
cutover —y por tanto `hub_tibox_designs_unified`— sobre un diseño roto.

### La corrección

El stage de cada objeto pasa a ser una tubería verificada paso a paso:

```text
crear hub_design (o reanudar uno incompleto de un intento anterior)
→ copiar metadata
→ crear versión       → verificar (Version_Store::create() > 0)
→ publicar versión    → verificar (Version_Store::publish() === true)
→ copiar package si corresponde → verificar (copy_directory() === true)
→ recién entonces status=created, y se marca _hub_migration_staged
```

Cualquier paso indispensable que falle:

- devuelve `status=failed` con un error accionable (qué diseño, qué paso, por
  qué);
- nunca llega a marcar `_hub_migration_staged`, con lo que
  `evaluate_migration_result()` lo clasifica correctamente como fallo y el
  cutover completo no se ejecuta;
- deja el diseño creado **identificable** en lugar de borrarlo: un
  `hub_design` con `_hub_legacy_source_id` pero sin `_hub_migration_staged` es,
  por definición, un intento incompleto.

`seed_version()` y `move_package_directory()` ahora devuelven
`array{status,error}` en vez de `void`, y cada uno delega la decisión a un
clasificador puro y estático, sin llamadas a WordPress:

- `HUB_Tibox_Upgrade::evaluate_version_write()` distingue explícitamente un
  fallo de `create()` (no hay versión) de un fallo de `publish()` (la versión
  existe pero no se pudo promover) — son errores distintos y merecían mensajes
  distintos.
- `HUB_Tibox_Upgrade::evaluate_package_copy()` distingue un package no
  declarado (skip legítimo) de un directorio origen ausente en disco y de una
  copia que falló.

### Retry idempotente sin duplicar filas

Antes, un reintento repetía `wp_insert_post()` para cualquier legacy que no
apareciera como "existente", lo que habría creado un `hub_design` nuevo cada
vez que el mismo ítem volviera a fallar tras el punto de creación. Ahora
`find_migrated()` solo cuenta como migrado un diseño con
`_hub_migration_staged = '1'`; un diseño con la metadata de origen pero sin esa
marca se localiza con el nuevo `find_staged_design_id()` y se **reanuda** —se
reintentan solo los pasos que faltan— en lugar de duplicarse.

### Archivos principales

`includes/class-hub-upgrade.php`, `tests/test-upgrade-stage-transaction.php`.

### Compatibilidad

- Nuevo meta `_hub_migration_staged` en los diseños creados por la migración.
  No afecta instalaciones que ya completaron una migración antes de este
  cambio: `run_and_record_migration()` solo vuelve a ejecutar el stage
  mientras `hub_tibox_designs_unified` siga en `0`.

### QA

87 aserciones (21 nuevas): `evaluate_version_write()` cubre el fallo de
`create()`, el fallo de `publish()` y el éxito, con mensajes distinguibles
entre sí; `evaluate_package_copy()` cubre el skip legítimo, el directorio
origen ausente y la copia fallida; un grupo adicional simula el reintento
exitoso tras un primer intento fallido, a nivel de clasificador y a nivel del
resumen del lote. PHPCS y PHPStan nivel 5 sin errores.

Lo que esto **no** cubre —y no puede cubrir sin una base de datos real— es que
`find_staged_design_id()` efectivamente reutilice la misma fila en un
WordPress real en vez de crear un duplicado: eso, como el cutover y el
rollback, queda para la Fase 7.

---

## 2026-09-01 — Segunda revisión del PR #7: migración de dos fases y rollback real

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Origen: segunda auditoría sobre el PR #7, antes de iniciar la Fase 7. Cuatro
hallazgos, todos sobre `HUB_Tibox_Upgrade` y su integración con el arranque del
plugin.

### 1 · El rollback no restauraba el estado anterior

`insert_design()` pasaba cada objeto histórico a borrador en el momento de
crear su reemplazo, sin guardar qué estado tenía antes. Poner
`hub_tibox_designs_unified` en `0` no revertía nada: los componentes y landings
migrados seguían en borrador.

La migración se reescribió en dos fases:

- **Stage**: cada objeto no migrado se copia a un `hub_design` nuevo, creado
  siempre como borrador. El objeto histórico no se toca en esta fase.
- **Cutover**: solo si el stage completo no tuvo fallos, se guarda el estado
  original de cada objeto histórico en `_hub_migration_previous_status`, el
  diseño nuevo se publica con ese mismo estado, y el histórico pasa a borrador.

`HUB_Tibox_Upgrade::rollback_to_legacy()` es la rutina explícita e idempotente
que faltaba: restaura cada objeto histórico a su estado guardado, retira su
reemplazo a borrador y pone `hub_tibox_designs_unified` en `0`. Llamarla de
nuevo con el sitio ya revertido es un no-op. Disponible desde
**Constructor HUB → Diagnóstico** (checkbox de confirmación, gate en
`manage_options`) y desde `wp hub rollback-to-legacy`.

### 2 · Una migración parcial activaba Unified igual

`install()` ponía `hub_tibox_designs_unified` en `1` sin mirar si
`migrate_legacy_designs()` había devuelto elementos omitidos por error. Con el
modelo anterior (sin fases) eso además dejaba objetos migrados con éxito en
borrador mientras el sitio seguía en modo Legacy — invisibles, no solo
inconsistentes.

Ahora la fase de cutover, que es la única que cambia el estado público de
cualquier objeto, **solo se ejecuta si el stage completo no tuvo fallos**.
`HUB_Tibox_Upgrade::evaluate_migration_result()` es la función pura que decide
esto — sin llamadas a WordPress, cubierta por 12 aserciones nuevas — y
`hub_tibox_designs_unified` solo se activa cuando su resultado es `complete`.

Un resultado `partial` deja el sitio funcionando exactamente como antes de
intentar migrar, registra los fallos con su error, y ofrece un reintento
explícito — desde **Constructor HUB → Diagnóstico** o `wp hub retry-migration`
— que es seguro de repetir: los elementos ya migrados se detectan y se saltan.

### 3 · El menú de Correo quedaba inalcanzable tras unificar

`HUB_Tibox_Landing_Mailer` registraba su submenú bajo
`edit.php?post_type=hub_component`, pero ese CPT pasa a `show_ui => false` en
cuanto el sitio se unifica: el menú padre deja de existir y la página de Correo
se vuelve inalcanzable desde el admin.

Ahora se registra en los dos sitios — bajo el CPT histórico solo mientras el
sitio no esté unificado, y bajo `constructor_hub_admin_menu` en cuanto lo está
— de modo que siempre hay exactamente un menú visible, nunca cero ni dos.

### 4 · Ventana de inconsistencia en la petición que completa la migración

`HUB_Tibox_Plugin::instance()` se invoca al final del archivo bootstrap, lo que
para el archivo principal de un plugin ocurre **antes** de que WordPress dispare
`plugins_loaded`. Su constructor decidía `is_unified()` en ese mismo instante,
antes de que la rutina de actualización (enganchada en `plugins_loaded` con
prioridad 5) hubiera podido ejecutarse. La petición que completaba una
migración seguía renderizando por el camino Legacy, mientras el contenido
histórico que esa misma migración acababa de retirar ya estaba en borrador.

La decisión se difiere a `plugins_loaded` con prioridad 10 — después de que la
actualización haya corrido en la misma petición — mediante
`HUB_Tibox_Plugin::needs_deferred_boot()`, una función pura que recibe si
`plugins_loaded` ya se disparó y no depende de WordPress para poder probarse.
Cubierta por 2 aserciones nuevas.

Como consecuencia, **Constructor HUB → Diagnóstico** ahora se registra siempre
disponible (vía `HUB_Tibox_Site_Config`, movida a la sección "siempre
disponible" del bootstrap), con una entrada equivalente en el menú
**Herramientas** cuando el sitio no está unificado — antes esa pantalla
desaparecía por completo en modo Legacy, lo que habría dejado sin salida a
cualquiera que necesitara reintentar o confirmar un rollback.

### Archivos principales

`includes/class-hub-upgrade.php` (reescrito), `class-hub-plugin.php`,
`class-hub-landing-mailer.php`, `class-hub-site-config.php`, `class-hub-cli.php`,
`uninstall.php`, `tests/test-upgrade-migration.php`,
`tests/test-plugin-boot-order.php`.

### Compatibilidad

- Nuevas opciones: `hub_tibox_designs_unification_status` (pending / partial /
  complete / rolled_back) y `hub_tibox_designs_rollback_result`. Añadidas a la
  limpieza opcional de `uninstall.php`.
- Nuevo meta `_hub_migration_previous_status` en los objetos históricos
  migrados; se borra al hacer rollback.
- El comportamiento de una migración que ya se completó con el código anterior
  no cambia: `hub_tibox_designs_unified` en `1` sigue significando lo mismo.

### QA

PHPCS y PHPStan nivel 5 sin errores. 66 aserciones (14 nuevas: 12 sobre
`evaluate_migration_result()`, 2 sobre `needs_deferred_boot()`). El rollback y
el cutover en sí —lo que efectivamente escriben en la base de datos— solo se
verifican en la Fase 7, sobre WordPress real: son exactamente el tipo de
comportamiento que un arnés de tests puros no puede cubrir.

---

## 2026-08-31 — Documentación y afinado post-fases

Rama: `feat/hub-v0.5-refundacion`.

### Cambios

- `README.md`, `docs/START-HERE-AI.md`, `docs/ARCHITECTURE.md` y
  `docs/ROADMAP.md` reescritos sobre el estado real: modelo `hub_design`,
  alcances de render por región, fragmento y documento, y una **Fase 7 de QA en
  WordPress que bloquea cualquier release estable**.
- **Resolución perezosa de variables**: `HUB_Tibox_Variables::replace()` solo
  resuelve las variables que el contenido usa realmente. Antes, cada render
  construía los tres menús de navegación y el formulario estándar completo
  —incluido su token anti spam— aunque el diseño no los referenciara.
- 11 aserciones nuevas sobre el contrato de variables, incluidas las que
  garantizan que los alias históricos `{{LANDING_URL}}` y `{{LANDING_TITLE}}`
  siguen registrados.

---

## 2026-08-31 — Fase 6: HUB Theme opcional y operación multi-sitio

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

### Objetivo

Cerrar el recorrido: un theme para cuando el sitio ya no necesita el anterior, y
las herramientas para poner en marcha un cliente nuevo sin copiar ajustes a
mano.

### Cambios

- **HUB Theme** en `theme/hub-theme/`: chasis ultraliviano con `header.php`,
  `footer.php`, `index.php`, `singular.php` y `404.php`, hooks de WordPress
  intactos, Header y Footer delegados a las regiones HUB y `theme.json` que
  expone los tokens `--hub-*` como paleta del editor.
  Sigue siendo **opcional**; no se empaqueta dentro del plugin.
  Si Constructor HUB se desactiva, el sitio sigue navegable con una cabecera de
  respaldo mínima.
- **Diagnóstico** (`Constructor HUB → Diagnóstico`): escritura en uploads,
  ZipArchive, WP-Cron, transporte de correo, Elementor, plugin SEO, entregas
  fallidas y estado del modelo de diseños, más las versiones de todo.
- **Export/import de configuración**: tokens, regiones y ajustes generales viajan
  en JSON entre sitios. Las regiones se transportan por slug de diseño, no por
  ID. **Los destinatarios de correo nunca se importan**: enviar los leads de un
  cliente al buzón de otro es exactamente el fallo que la auditoría encontró en
  los correos codificados en el core.

### Archivos principales

`theme/hub-theme/`, `theme/README.md`, `includes/class-hub-site-config.php`.

### QA

PHPCS y PHPStan nivel 5 sin errores. Pendiente: activar el theme sobre una copia
de tibox.cl y comparar Core Web Vitals contra Hello Elementor.

---

## 2026-08-31 — Fase 5: migración de Elementor y WPCode

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

### Objetivo

Elementor no se retira por decisión, se retira por inventario. Esta fase aporta
el inventario, un dequeue que se apoya en él, y una migración WPCode que no se
cae con volúmenes reales ni cambia URLs sin que alguien lo pida.

### Cambios

- **Mapa de migración** (`Constructor HUB → Mapa de migración`): para cada
  contenido, qué lo renderiza, si todavía necesita Elementor y qué componentes
  HUB inserta. La detección usa el modo de edición guardado y el markup real, no
  el nombre de los handles encolados.
- **`HUB_Tibox_Asset_Optimizer`** sustituye al `strip_heavy_assets()` del MVP.
  Desactivado por defecto; solo actúa donde Constructor HUB controla el
  documento y el inventario dice que Elementor no hace falta; y nunca elimina un
  handle del que dependa algo todavía encolado.
- **Migración WPCode por lotes** con cursor persistido, tanto en el admin como
  por línea de comandos. Un timeout cuesta un reintento, no un estado parcial
  desconocido.
- **Traspaso de URL explícito (ALTO-09)**: la landing migrada nace en borrador
  con slug propio. Publicar la URL histórica es una acción por landing que
  publica el diseño, retira el original y registra la redirección 301. Copiar
  datos ya no puede generar contenido duplicado indexable.
- **WP-CLI**: `wp hub migrate-wpcode`, `wp hub designs`, `wp hub purge-leads`.
- La migración WPCode ahora escribe diseños `hub_design`, no el post type
  histórico, e importa el package de cada landing a la carpeta de su versión.
- Se elimina el MVP de página completa: `includes/class-tibox-ai-frontend.php`,
  `pages/home-ai/`, `templates/ai-page.php` y `assets/css/ai-shell.css`. Con
  ellos desaparecen el branding de Tibox y las URLs codificadas dentro del core
  (BAJO-03), y el endpoint que apuntaba a WPCode deja de tener consumidores.
  El alias REST `tibox/v1/lead` se mantiene servido por el plugin.

### Archivos principales

`includes/class-hub-migration-map.php`, `class-hub-asset-optimizer.php`,
`class-hub-cli.php`, `class-hub-legacy-migrator.php`,
`class-hub-landing-lead-store.php`.

### Compatibilidad y riesgos

- La optimización de assets sigue apagada tras actualizar: activarla es una
  decisión por sitio y se revierte desmarcando una casilla.
- El traspaso de URL cambia el slug del diseño al de la landing histórica.
  Conviene hacerlo fuera del horario de campaña y verificar la URL final del
  anuncio inmediatamente después.

### QA

PHPCS y PHPStan nivel 5 sin errores, ahora con los stubs de WP-CLI.
Pendiente: migración real sobre una copia del WordPress de Tibox.

---

## 2026-08-31 — Fase 4: Design Packages e IA

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Contrato: [`AI-PACKAGE-CONTRACT.md`](AI-PACKAGE-CONTRACT.md).
Ejemplo: [`examples/hub-package-hero/`](../examples/hub-package-hero/).

### Objetivo

Convertir "sube tu ZIP de Claude Design" en un contrato verificable. El
importador anterior aceptaba cualquier ZIP, buscaba un HTML y lo extraía: sin
tipo, sin versión, sin variables declaradas y sin destino.

### Cambios

- **`manifest.json` v1 obligatorio**, con validación de contrato, tipo, nombre y
  variables declaradas. Un contrato futuro se rechaza en lugar de interpretarse a
  medias.
- **Validación de variables contra el registro real**: si el HTML usa
  `{{PRECIO_MENSUAL}}` y el sitio no la conoce, la importación falla nombrando la
  variable, en lugar de publicar una página que muestra llaves a un visitante.
- **Importar crea una versión borrador**, nunca reemplaza lo publicado. El aviso
  de éxito entrega directamente el enlace de preview firmado.
- **Importación para todos los tipos**, no solo landings, y con destino
  seleccionable: crear un diseño nuevo o añadir una versión a uno existente.
- **Exportación a ZIP** desde la caja de versiones: cierra el ciclo sitio → IA y
  sitio → sitio.
- **Assets por versión** en `uploads/constructor-hub/packages/{diseño}/{versión}/`,
  con render desde esa carpeta y `<base>` inyectado, más limpieza al borrar el
  diseño.
- `scope` declarado en el manifest activa el aislamiento CSS automáticamente.
- El extractor seguro se generaliza (`extract_to`) y la capa de package lo
  reutiliza sin duplicar las validaciones de seguridad.

### Archivos principales

`includes/class-hub-package.php`, `class-hub-landing-zip-importer.php`,
`class-hub-render.php`, `class-hub-version-store.php`,
`docs/AI-PACKAGE-CONTRACT.md`, `examples/hub-package-hero/`,
`tests/test-package-manifest.php`, `tests/stubs-wp.php`.

### QA

PHPCS y PHPStan nivel 5 sin errores; 42 aserciones, incluidas 12 nuevas sobre la
validación del manifest y una que verifica que el package de ejemplo del
repositorio sigue siendo válido.

Pendiente: importar un proyecto real de Claude Design en un WordPress real.

---

## 2026-08-31 — Fase 3: landings y formularios

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Documento de contrato: [`FORMS-AND-TRACKING.md`](FORMS-AND-TRACKING.md).

### Objetivo

Convertir el pipeline de formularios en algo operable: antispam que no bloquee
a visitantes legítimos, correo que no cuelgue la respuesta ni falle en silencio,
y leads que se puedan exportar, borrar y convertir en conversiones de Ads.

### Cambios

- **`HUB_Tibox_Antispam`**: token firmado por HMAC embebido en cada formulario
  (compatible con caché de página, caduca a las 12 horas), tiempo mínimo de
  envío y filtro `constructor_hub_antispam_check` para conectar reCAPTCHA o
  Turnstile como adaptador. El honeypot sigue respondiendo éxito para no
  enseñarle al bot que fue detectado.
- **Idempotencia real (MED-01)**: el `submission_id` se guarda en
  `sessionStorage` y se reutiliza en los reintentos; solo se rota tras un éxito
  confirmado.
- **Colisión de clave única (MED-02)**: dos peticiones con el mismo
  `submission_id` devuelven el lead existente en lugar de un 500.
- **Correo encolado**: `wp_schedule_single_event` saca el SMTP del request del
  formulario, con envío en línea como respaldo si no hay cron. Nueva tabla
  `wp_hub_mail_log` y pantalla **Correo enviado** con el resultado de cada envío;
  se acabó el `error_log` como único canal.
- **Evidencia de consentimiento**: `consent_at`, `consent_url`,
  `consent_version` e `ip_hash` por lead.
- **Privacidad**: exportador y borrador registrados en las herramientas de
  WordPress, borrado individual desde el admin y retención automática
  configurable en meses.
- **Exportación**: CSV de leads y CSV de conversiones offline de Google Ads a
  partir de `gclid`/`gbraid`/`wbraid`, con marca de exportado para no contar dos
  veces la misma conversión. Los valores que empiezan por `=`, `+`, `-` o `@` se
  neutralizan para que un mensaje de un visitante no se ejecute como fórmula al
  abrir el CSV.
- **Estado comercial por lead**: nuevo, calificado, ganado, perdido, con valor.
- El runtime del formulario se encola solo en páginas que contienen un
  formulario HUB, venga de `{{HUB_FORM}}` o de markup escrito por una IA.

### Archivos principales

`includes/class-hub-antispam.php`, `class-hub-mail-log.php`,
`class-hub-lead-privacy.php`, `class-hub-leads-export.php`,
`class-hub-landing-lead-store.php`, `class-hub-landing-mailer.php`,
`class-hub-landing-forms.php`, `assets/js/landing-form.js`,
`docs/FORMS-AND-TRACKING.md`.

### Compatibilidad y riesgos

- La tabla de leads sube a la versión de esquema `3.0.0`; `dbDelta` añade las
  columnas nuevas sin tocar las existentes.
- El token de formulario se exige por defecto. Un formulario servido desde una
  caché de más de 12 horas devolverá "vuelve a cargar la página": el filtro
  `constructor_hub_enforce_form_token` permite desactivarlo mientras se ajusta
  la caché.

### QA

PHPCS y PHPStan nivel 5 sin errores. Pendiente: prueba de entrega real por
SendGrid, verificación del `dataLayer` en GTM y una importación de conversiones
en una cuenta de Google Ads real.

---

## 2026-08-31 — Fase 2: componentes y Design System

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Decisión: [`decisions/ADR-0004-insertion-api-y-regiones.md`](decisions/ADR-0004-insertion-api-y-regiones.md).

### Objetivo

Hacer posible la migración por piezas. Hasta aquí un componente solo podía
renderizarse sustituyendo la plantilla completa, y el modo híbrido exigía Header
y Footer HUB a la vez.

### Cambios

- **Insertion API**: shortcode `[hub_design slug="..."]`, bloque de Gutenberg con
  render en servidor, widget de Elementor y función de plantilla
  `constructor_hub_render()`. Las cuatro entradas comparten un único renderer.
- **Regiones independientes** con modos `theme`, `inject` y `replace`, alcance
  `all` / `selected` / `except` y selector de ocultación configurable.
- **Design System**: tokens `--hub-*` por sitio (color, tipografía, layout,
  forma), pantalla propia, CSS impreso una vez, export e import en JSON. Los
  valores por defecto son neutros; el core no contiene la identidad de ningún
  cliente.
- **Adaptador Elementor** en `includes/adapters/`: widget, soporte opt-in del CPT,
  detección de Elementor Pro y aviso de conflicto con Theme Builder, y el filtro
  `constructor_hub_elementor_needed` que responde si una página todavía necesita
  Elementor a partir de datos reales y no de nombres de handle.
- El soporte de Elementor para el CPT sale del core y pasa al adaptador.
- Tests del saneador de SVG del importador ZIP: 13 aserciones nuevas.

### Archivos principales

`includes/class-hub-insertion.php`, `class-hub-design-system.php`,
`includes/adapters/class-hub-elementor-adapter.php`,
`includes/adapters/class-hub-elementor-widget.php`,
`assets/js/block-hub-design.js`, `tests/test-svg-sanitizer.php`.

### Compatibilidad y riesgos

- El modo `inject` necesita un selector CSS específico del theme; sin él, el
  header del theme y el HUB conviven en la página.
- Con Elementor Pro y Theme Builder activo puede haber dos headers. El adaptador
  avisa en las pantallas de Constructor HUB.

### QA

PHPCS y PHPStan nivel 5 sin errores; 30 aserciones del arnés propio.
Pendiente QA funcional en WordPress real.

---

## 2026-08-31 — Fase 1: core sólido

Rama: `feat/hub-v0.5-refundacion`.
Versión: `0.5.0-dev`.
Estado: **implementado / QA WordPress pendiente**.

Decisión: [`decisions/ADR-0003-design-unificado-y-versionado.md`](decisions/ADR-0003-design-unificado-y-versionado.md).

### Objetivo

Refundar el modelo de datos antes de seguir añadiendo tipos de componente. La
auditoría concluyó que la ausencia de versionado y la duplicación entre
componentes y landings no eran bugs sino consecuencias del almacenamiento en
post meta.

### Antes

- Header/Footer en `hub_component`, Landings en `hub_landing`, con dos
  almacenamientos, dos resolvedores de variables y dos renderers.
- HTML/CSS/JS en post meta, sin historial: publicar destruía la versión previa.
- CSS/JS impresos inline y globales en cada request.
- Menú del producto colgando de `edit.php?post_type=hub_component`.
- `manage_options` para operar, `unfiltered_html` para editar código.
- Sin `uninstall.php`, sin i18n, sin WPCS, sin análisis estático, sin tests.

### Después

- **`hub_design`** unificado con `_hub_type`: header, footer, menu, hero,
  section, form, landing, page. Los tipos no visibles devuelven 404 y `noindex`.
- **`wp_hub_design_versions`**: versiones inmutables con estado
  draft/live/archived. Publicar mueve un puntero; rollback lo mueve atrás.
- **Preview firmado** por HMAC con caducidad, compartible sin cuenta WordPress.
- **`HUB_Tibox_Variables`**: registro único y versionado de `{{VARIABLES}}`, con
  detección de variables desconocidas para el importador.
- **`HUB_Tibox_Asset_Compiler`**: CSS/JS de la versión publicada a archivos en
  `uploads`, encolados y cacheables; fallback inline si el filesystem es de solo
  lectura.
- **`HUB_Tibox_Css_Scoper`**: aislamiento opcional del CSS por diseño, con
  soporte de `@media`, `@supports`, `@keyframes`, cadenas y comentarios. Cubierto
  por tests.
- **`HUB_Tibox_Regions`**: Header y Footer configurables por separado, en modo
  `theme`, `inject` (conserva la plantilla del theme) o `replace`. Resuelve la
  imposibilidad de migrar una sola región.
- **Capacidades propias** y menú de primer nivel `Constructor HUB`.
- **`HUB_Tibox_Upgrade`**: migración idempotente y reversible desde los post
  types históricos, conservando slugs, packages y configuración de Header/Footer.
- `uninstall.php` que **no** borra datos salvo petición explícita.
- i18n cargado, `composer.json`, `phpcs.xml.dist`, `phpstan.neon.dist`,
  `.gitignore`, `.distignore` y arnés de tests propio.

### Archivos principales

`includes/class-hub-design.php`, `class-hub-version-store.php`,
`class-hub-variables.php`, `class-hub-asset-compiler.php`,
`class-hub-css-scoper.php`, `class-hub-regions.php`, `class-hub-render.php`,
`class-hub-preview.php`, `class-hub-capabilities.php`, `class-hub-upgrade.php`,
`class-hub-admin-menu.php`, `class-hub-design-admin.php`,
`class-hub-settings-page.php`, `class-hub-form-config.php`,
`class-hub-filesystem.php`, `class-hub-legacy-types.php`,
`class-hub-plugin.php`, `templates/hub-shell.php`, `uninstall.php`.

### Compatibilidad y riesgos

- Los módulos históricos siguen en el repositorio y se arrancan si
  `hub_tibox_designs_unified` es `0`.
- La migración pasa los objetos históricos a borrador para evitar contenido
  duplicado; no borra nada.
- El aislamiento CSS queda desactivado en los diseños migrados: activarlo
  cambiaría cómo renderizan.
- Riesgo principal a validar en QA: reglas de reescritura y permalinks tras la
  migración de landings publicadas.

### QA

- `php -l` en todos los archivos.
- PHPCS con reglas de seguridad WordPress: sin errores.
- PHPStan nivel 5 con `phpstan-wordpress`: sin errores.
- 17 aserciones del arnés propio sobre el scoper CSS.
- Pendiente: QA funcional en WordPress real.

### Deuda

Insertion API, Design System y adaptador Elementor llegan en la Fase 2.

---

## 2026-08-31 — Fase 0: bloqueadores de producción

Rama: `feat/hub-v0.5-refundacion`.
Estado: **implementado / QA WordPress pendiente**.

Origen: auditoría integral del 2026-08-31 sobre `main` v0.4.0-beta.1.

### Objetivo

Cerrar los hallazgos que impiden instalar el plugin en un sitio productivo, sin
cambiar todavía el modelo de datos.

### Cambios

- **CRIT-01** `origin_is_allowed()` dejaba fuera los envíos desde `www.` y desde
  alias del sitio, y permitía las peticiones sin cabeceras. Ahora compara contra
  el conjunto de hosts del sitio normalizando `www.`, es filtrable mediante
  `constructor_hub_allowed_origins` y no se considera control de seguridad.
- **CRIT-02** el límite de envíos usaba `REMOTE_ADDR`, que detrás de un CDN es la
  IP del proxy. Se añade cabecera de IP configurable (`Constructor HUB →
  Configuración`), presupuestos separados para intentos y para leads creados, y
  un límite por dirección de correo que funciona aunque todos compartan IP.
- **CRIT-03** guardar una landing o un componente descartaba HTML/CSS/JS en
  silencio cuando faltaba `unfiltered_html`. Se introduce
  `HUB_Tibox_Capabilities` con capacidades propias y un aviso explícito; ya no se
  guarda parcialmente sin decirlo.
- **CRIT-04** el formulario del MVP `home-ai` apuntaba a `tibox/v1/lead`, un
  endpoint que vive en WPCode y no en el plugin. Se registra ese endpoint como
  alias del pipeline HUB, se envía `landing_id` y el endpoint acepta formularios
  de cualquier contenido publicado, no solo de landings.
- **ALTO-01** los modos HTML completo y Package ignoraban
  `post_password_required()`. Ambos renderers lo respetan.
- **ALTO-02** la extracción del ZIP confiaba en el tamaño declarado en la
  cabecera del archivo. La copia ahora está acotada y se verifica el tamaño real
  escrito contra el presupuesto total.
- **ALTO-03** los SVG del package se sanean en la importación (se eliminan
  `script`, `foreignObject`, manejadores `on*` y URLs `javascript:`).
- **ALTO-07** se elimina `maybe_seed_tibox_mail_recipients()` y con él los
  correos de empleados de Tibox codificados en el core.
- **ALTO-10** la cabecera `Reply-To` ya no interpola el nombre del lead.
- **MED-10** `docs/architecture.md` se elimina del índice de git: colisionaba con
  `docs/ARCHITECTURE.md` en macOS y Windows.
- `enable_elementor_support()` deja de escribir la opción global de Elementor sin
  autorización: ahora es una casilla en Configuración.
- Se eliminan `strip_php_tags()` y `sanitize_header_value()`, que daban una falsa
  sensación de control.
- Se añaden hooks de activación y desactivación: reparto de capacidades,
  instalación de la tabla de leads y `flush_rewrite_rules`.

### Compatibilidad

- `unfiltered_html` + `manage_options` sigue siendo válido para editar código de
  diseño en instalaciones que nunca ejecutaron el hook de activación.
- El endpoint histórico `tibox/v1/lead` queda servido por el plugin, de modo que
  desactivar el snippet WPCode ya no rompe el formulario de la home.

### QA/deuda

Pendiente QA funcional en WordPress real. La refundación del modelo de datos
llega en la Fase 1.

---

## 2026-08-31 — Módulo Landings HUB

Rama: `feat/landings-module`.  
Base: `feat/hybrid-header-footer`.  
Estado: **implementado en rama / QA WordPress pendiente**.

Documento detallado: [`changes/2026-08-31-landings-module.md`](changes/2026-08-31-landings-module.md).  
Decisión: [`decisions/ADR-0002-landings-cpt-native-forms.md`](decisions/ADR-0002-landings-cpt-native-forms.md).

### Objetivo

Añadir un apartado **Landings** dentro de Constructor HUB para crear páginas completas mediante HTML/CSS/JS generado por IA, sin renderizar Elementor, manteniendo WordPress como backend y con un formulario nativo reutilizable entre Tibox, Prodata y futuros sitios.

### Commits funcionales

- `6d479e49b824a4000a302c0e8b8d7d594b9ae05b` — CPT `hub_landing` + editor HTML/CSS/JS + configuración.
- `309e258fb3b3c500acad10111b7e049eac35f03a` — endpoint y almacenamiento inicial de formularios.
- `bc313f2fcfcc285590041eaa37978a6cf68ff921` — renderer completo de Landings.
- `dbcc8e40f4f167174ab96f519593ce568f7112aa` — template frontend independiente del theme.
- `d64fa2b32df465a252656a97184037899bb00d67` — frontend de formulario + tracking + `dataLayer`.
- `4aa06cd787586db23487fa06513db08dd28e4c93` — CSS estructural mínimo.
- `be3d9bed9096ade9016582989e9c4fa4c0196ec2` — carga CSS base desde el renderer.
- `338da903f738029171f5f5fc69aeb9e327c7c001` — integra módulo y versiona `0.4.0-dev`.
- `6ae3ef055b3c714f2c4c3556caeda817c4718663` — flush controlado de rewrite `/landing/`.
- `e347832dbbe253bdb69b89aa620dbabf6ed6a3fd` — vista de leads en admin, privacy URL filtrable y fallback sin mbstring.

### Ejemplo de QA

- `cedf7b56ef461e7322f76561044be220d68a539b` — Landing Starter HTML.
- `3e4a5bf4be8adf5a6ec0ec2137bc705e7cf10539` — Landing Starter CSS.
- `26d0d5897eec53fcc6e94785d4bf26522276e5fd` — Landing Starter JS.
- `212562e59e76d66ecf81605e225ed75f5db99b13` — instrucciones del ejemplo.

### Documentación

- `91a4a88fd0a21b4214aab2aa1997e0eb9c536ad4` — ADR-0002.
- `9779ef62148d6a9ff2176c6b4f4379190253a2f9` — documento detallado del cambio.

### Comportamiento

- nuevo menú `Constructor HUB → Landings`;
- nuevo menú `Constructor HUB → Envíos Landings`;
- URL inicial `/landing/<slug>/`;
- modo canvas independiente o Header/Footer HUB;
- variable `{{HUB_FORM}}`;
- formularios IA custom mediante `data-hub-landing-form`;
- endpoint `POST /wp-json/constructor-hub/v1/landing-submit`;
- honeypot, rate limit e idempotencia;
- `wp_mail` por landing;
- UTMs + GCLID/GBRAID/WBRAID;
- `dataLayer` `form_submit` tras creación correcta;
- hook `constructor_hub_landing_lead_created` para bridges futuros.

### QA/deuda

Pendiente CI de la rama y QA funcional en WordPress real. Debe validarse Rank Math, canonical, GTM, correo, registro de lead, responsive y ausencia de render Elementor antes de merge.

---

## 2026-08-28 — Header/Footer HUB híbridos

Rama: `feat/hybrid-header-footer`.
Baseline `main`: `3944fa9f37ee4fe897883abecae39dcc5656fea7`.
Estado: en desarrollo / QA WordPress pendiente.

Documento detallado: [`changes/2026-08-28-hybrid-header-footer.md`](changes/2026-08-28-hybrid-header-footer.md).

### `b18b77535349db25807840fba287b1d81c58e8fe` — component manager

- Añade CPT privado `hub_component`.
- Tipos iniciales: Header y Footer.
- Guarda HTML/CSS/JavaScript por separado.
- Añade variables dinámicas iniciales.
- Añade configuración de Header/Footer activos y alcance híbrido.

### `61e8251c637e5e8d13bdffaebf4a1b0e8ddb2ae9` — renderer híbrido

- Intercepta únicamente Pages configuradas.
- Mantiene prioridad del MVP full-page histórico cuando está activo.
- No descarga Elementor/theme assets en modo Híbrido.
- Imprime CSS/JS de componentes mediante hooks WordPress.

### `96c40fb0719648a07cb0de86d77d9b24b9f0d942` — template híbrido

- Estructura: `wp_head()` → Header HUB → `the_content()` → Footer HUB → `wp_footer()`.
- Permite que Elementor siga procesando el contenido central.

### `713fb0cb2a8d874445a813c096de90b8a78cbe16` — wiring v0.3-dev

- Integra Component Manager y Hybrid Renderer en bootstrap.
- Versión de desarrollo `0.3.0-dev`.
- Mantiene temporalmente core/nombres históricos del MVP.

### `b8432516566b4544125964cda2e403ebd0df70b2` — CI

- Añade GitHub Actions.
- PHP syntax: 8.0 y 8.3.
- JavaScript: `node --check`.

### `53fb1e4d1725ac7f16a625b078b2882e7b9dbc98` — registro detallado

- Documenta arquitectura del cambio, seguridad, compatibilidad, QA y pendientes.

### Compatibilidad/riesgo

- Modo desactivado por defecto.
- Requiere Header y Footer publicados/seleccionados.
- v0.3 inicial solo se aplica a Pages.
- No cambia theme.
- No desactiva Elementor.
- Falta validar en un WordPress/Elementor real antes de merge.

### Próximos pasos

- validar CI;
- crear PR draft;
- probar en WordPress real;
- agregar preview para administradores;
- adapter Elementor para Header/Footer de Theme Builder;
- primer Header/Footer Tibox como componentes de prueba.

---

## 2026-08-28 — Formalización como Constructor HUB Tibox

Rama: `feat/ai-frontend-mvp` (nombre histórico de la rama inicial).
PR: `#1`.
Merge a `main`: `3944fa9f37ee4fe897883abecae39dcc5656fea7`.

### `8c7c5ff4d391f16f24a93e4109b6e7232c1279d5` — identidad del plugin

- Tipo: refactor/branding compatible.
- Objetivo: renombrar públicamente `Tibox AI Frontend` a **Constructor HUB Tibox**.
- Archivo: `tibox-ai-frontend.php`.
- Cambios:
  - Plugin Name actualizado.
  - Plugin URI actualizado al repositorio renombrado.
  - versión pasa a `0.2.0`.
  - descripción cambia desde una home IA específica hacia constructor frontend progresivo.
- Compatibilidad:
  - se mantiene temporalmente el nombre histórico del archivo bootstrap, constantes y clase para no romper instalaciones del MVP.
- Deuda:
  - migrar namespaces/nombres internos de forma compatible en una fase posterior.

### `a34ffdeec0295431f8c04f6197d777b236cab470` — README como mapa del producto

- Tipo: documentación.
- Objetivo: redefinir alcance multi-sitio y flujo Legacy/Híbrido/HUB.
- Establece a Tibox y Prodata como primeras implementaciones del mismo core.
- Define relación conceptual con Cloud-tibox.
- Añade enlaces obligatorios a `/docs`.

### `62c80b90db4e5d3db5fd3b3e900c2e7e2a6525c2` — onboarding para IA

- Archivo: `docs/START-HERE-AI.md`.
- Objetivo: que una IA nueva entienda propósito, estado heredado, próximos pasos y reglas antes de modificar código.
- Documenta explícitamente nombres históricos `Tibox AI Frontend` que aún existen.

### `1c5f5615f191eafa75174bd0e38ae3577648ada8` — arquitectura objetivo

- Archivo: `docs/ARCHITECTURE.md`.
- Define:
  - core independiente;
  - modos Legacy/Híbrido/HUB;
  - Component Registry;
  - Design Packages;
  - variables dinámicas;
  - Design System;
  - adaptadores;
  - HUB Theme futuro opcional;
  - principios de SEO, seguridad y rendimiento.

### `ad7b04b38c965b2caceaca94ad69b25c5deb6149` — protocolo obligatorio

- Archivo: `docs/DEVELOPMENT-PROTOCOL.md`.
- Define reglas de ramas, commits, PR, QA, ADR y changelog.
- Desde esta fecha todo cambio funcional/arquitectónico debe registrar fecha, rama y commit.

### `7746b3077f56e17c26af73132e85c2c096f7bc15` — roadmap

- Archivo: `docs/ROADMAP.md`.
- Orden de trabajo establecido:
  1. fundación;
  2. Header/Footer;
  3. Design System;
  4. componentes;
  5. Design Packages;
  6. páginas híbridas/HUB;
  7. adaptadores;
  8. migración WPCode;
  9. HUB Theme opcional.

### `422257e3ad8319dcb0d147c7e788a1345be9d8c7` — relación con Cloud-tibox

- Archivo: `docs/CLOUD-TIBOX-RELATION.md`.
- Documenta qué conceptos reutilizar y qué acoplamientos no copiar.
- Design Packages, variables y contrato IA se consideran antecedentes directos.

### `82f0d1ee738b0bf6434594f8a84de84dc99cd434` — límites de adaptadores

- Archivo: `docs/SITE-ADAPTERS.md`.
- Separa core, adaptador Elementor, adaptador Tibox y futuro adaptador Prodata.
- Regla: diferencias visuales normales pertenecen al Design System, no a código específico del sitio.

### `eb6bd668bc5e4ea7c14fae7fa3b51a99ff5e0ef8` — ADR-0001

- Archivo: `docs/decisions/ADR-0001-core-independent-theme-elementor.md`.
- Decisión aceptada: el core no dependerá de Elementor ni de un theme específico.
- HUB Tibox Theme será opcional.

### `5f8ce97fa5d588fb34acaf78b17fd5be94112225` — deprecación del documento MVP

- Archivo: `docs/architecture.md`.
- El documento histórico queda como redirect hacia `docs/ARCHITECTURE.md` para evitar dos fuentes de verdad.

### `15d6c7c207801ddc78d8aa8299a94ffe447b43c9` — changelog base

- Reconstruye historial del MVP y formalización.
- Establece plantilla de trazabilidad futura.

### Resultado

- Repositorio renombrado verificado: `wladimick/plugin-constructor-hub-tiboxCL`.
- PR #1 mergeado sin squash para conservar commits individuales.
- `main` pasa a ser una baseline documentada de Constructor HUB Tibox v0.2.0.

---

## 2026-08-27 — MVP histórico: Tibox AI Frontend

Rama: `feat/ai-frontend-mvp`.

Base `main`: `ed08412188c4a047a66b9be2363e8695d2c3c8d3` (`chore: initialize Tibox AI Frontend plugin repository`).

Rango del MVP anterior a la formalización HUB:

`ed08412188c4a047a66b9be2363e8695d2c3c8d3..af3674ebfe270bf955b41e7abdc768c505af423a`

Commits conocidos del desarrollo inicial incluyen:

- `9db9649bcd03e6cf65e19d867b124db4601216a8`
- `c93fb3edb3a742a2ca0171644aa627974dda0743`
- `1140a526682684c8caf82cdf43b7002b9934d4ca`
- `d790c1ce72692080e79e03877613c80d97a9248e`
- `3212f531ab8b1ca8a0135bf7098d3533ae6486b7`
- `af157f2b89fd8ae4327549f4431a020f28ec5aa8`
- `6726df4772b240b99a17d1e66fe7259bf37dee2f` — interacciones nativas y formulario de leads.
- `1207c8947072496fef882e4c144e9d326e8e44ea` — documentación de instalación/rollout del MVP.
- `d84627115773202f2902f6bab665c2f56316065c` — notas de arquitectura del MVP.
- `af3674ebfe270bf955b41e7abdc768c505af423a` — requisito PHP 8.

### Resultado del MVP

Se construyó una prueba de frontend de página completa capaz de:

- reemplazar el template por página;
- conservar hooks WordPress;
- reducir assets Elementor;
- usar HTML/CSS/JS nativo;
- probar una nueva home Tibox;
- conectar formulario a REST.

### Limitación identificada

El enfoque estaba demasiado orientado a reemplazar páginas completas. La dirección aprobada el 2026-08-28 cambia hacia **migración por componentes**, comenzando por Header/Footer y coexistencia con Elementor.

---

## Cómo agregar futuras entradas

Copiar esta estructura:

```md
## YYYY-MM-DD — Nombre del cambio

Rama: `feat/...`
Commit: `SHA`
PR: `#N` si existe

### Objetivo
...

### Antes
...

### Después
...

### Archivos/componentes
...

### Compatibilidad y riesgos
...

### QA
...

### Pendiente
...
```

Los commits exclusivamente documentales asociados a una misma implementación quedan auditables mediante Git y no requieren crear una recursión infinita de entradas auto-referenciales.
