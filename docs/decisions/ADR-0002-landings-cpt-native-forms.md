# ADR-0002 — Landings como CPT + renderer HUB + formularios nativos

Fecha: 2026-08-31  
Estado: **Aceptado para MVP / QA**  
Rama inicial: `feat/landings-module`

## Contexto

Constructor HUB necesita crear páginas de campaña completamente diseñadas con IA sin depender del editor visual de Elementor, pero manteniendo WordPress como backend, SEO y analítica.

El mismo módulo debe servir en Tibox, Prodata y futuros sitios, por lo que no puede depender del endpoint de leads específico de Tibox ni de WPForms.

## Decisión

Las landings se modelan como un Custom Post Type público `hub_landing` administrado desde el menú **Constructor HUB → Landings**.

Cada landing guarda:

- título, slug, estado y permalink mediante WordPress;
- HTML visual;
- CSS visual;
- JavaScript visual;
- opción de usar Header/Footer HUB globales;
- correo de notificación del formulario;
- mensaje de éxito.

El frontend utiliza un template propio que conserva:

- `wp_head()`;
- `wp_body_open()`;
- `wp_footer()`.

El template del theme y Elementor no renderizan el cuerpo de la landing.

## Formularios

Se crea un pipeline nativo y genérico:

- variable `{{HUB_FORM}}` para insertar un formulario estándar;
- soporte para formularios IA personalizados con `data-hub-landing-form`;
- endpoint `POST /wp-json/constructor-hub/v1/landing-submit`;
- validación de email y consentimiento;
- honeypot `website`;
- rate limit por IP hasheada;
- idempotencia mediante `submission_id`;
- almacenamiento en CPT privado `hub_landing_lead`;
- notificación mediante `wp_mail`;
- hook `constructor_hub_landing_lead_created` para integraciones futuras;
- tracking UTM/GCLID/GBRAID/WBRAID;
- evento `dataLayer` `form_submit` solo tras respuesta correcta.

## URL

El MVP publica bajo:

`/landing/<slug>/`

La base es modificable mediante el filtro:

`constructor_hub_landing_rewrite_slug`

No se implementan URLs raíz arbitrarias en este MVP para evitar colisiones con Pages y reglas existentes.

## SEO

El CPT sigue siendo un objeto WordPress público y el renderer conserva los hooks estándar. Rank Math debe validarse en QA real; el core no dependerá contractualmente de Rank Math.

## Consecuencias positivas

- IA puede entregar HTML/CSS/JS sin conocer Elementor.
- misma arquitectura para Tibox/Prodata;
- formularios no dependen de WPForms;
- leads quedan centralizados en WordPress;
- GTM/Rank Math pueden seguir funcionando;
- futuras integraciones pueden consumir el hook de lead creado.

## Riesgos / pendientes

- validar Rank Math sobre `hub_landing`;
- validar `wp_mail` en cada infraestructura;
- añadir reCAPTCHA/Turnstile como adaptador opcional si el volumen lo requiere;
- importación ZIP de Design Packages todavía no implementada;
- preview privado y versionado/rollback todavía pendientes;
- URLs raíz se evaluarán en una fase posterior.
