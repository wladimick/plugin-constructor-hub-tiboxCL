# 2026-08-31 — Módulo Landings HUB

Rama: `feat/landings-module`  
Base: `feat/hybrid-header-footer`  
Objetivo: añadir un apartado **Landings** a Constructor HUB para crear páginas completas diseñadas con IA y formularios nativos.

## Resultado funcional

El menú del plugin pasa a poder contener:

```text
Constructor HUB
├── Componentes HUB
├── Landings
├── Envíos Landings
└── Configuración
```

Una Landing HUB es un objeto WordPress independiente de Elementor. WordPress administra título, slug, publicación y hooks SEO/analítica; Constructor HUB renderiza el cuerpo HTML/CSS/JS.

## Flujo de autor

1. Crear `Constructor HUB → Landings → Nueva landing`.
2. Definir título/slug.
3. Pegar HTML, CSS y JavaScript generados por IA en campos separados.
4. Insertar `{{HUB_FORM}}` donde deba aparecer el formulario, o construir uno personalizado con `data-hub-landing-form`.
5. Definir correo de notificación y mensaje de éxito.
6. Elegir canvas independiente o Header/Footer HUB globales.
7. Publicar.

URL MVP: `/landing/<slug>/`.

## Contrato de variables

- `{{SITE_URL}}`
- `{{HOME_URL}}`
- `{{SITE_NAME}}`
- `{{CURRENT_YEAR}}`
- `{{CUSTOM_LOGO}}`
- `{{CUSTOM_LOGO_URL}}`
- `{{LANDING_URL}}`
- `{{LANDING_TITLE}}`
- `{{HUB_FORM}}`

## Formularios

Endpoint:

`POST /wp-json/constructor-hub/v1/landing-submit`

Características:

- formulario estándar generado por `{{HUB_FORM}}`;
- formulario IA personalizado compatible mediante `data-hub-landing-form`;
- email obligatorio;
- consentimiento `privacy` obligatorio;
- honeypot `website`;
- rate limit por hash de IP;
- idempotencia por `submission_id`;
- almacenamiento en `hub_landing_lead`;
- visualización en `Constructor HUB → Envíos Landings`;
- correo por `wp_mail`;
- UTMs + GCLID/GBRAID/WBRAID;
- evento `dataLayer` `form_submit` tras envío correcto;
- hook PHP `constructor_hub_landing_lead_created` para bridges futuros.

## Seguridad

- el HTML/CSS/JS visual solo se guarda sin filtrar para usuarios con `unfiltered_html`;
- no se evalúa PHP desde el contenido IA;
- el endpoint verifica que la landing exista y esté publicada;
- valida origen/referer cuando están disponibles;
- no almacena IP en claro;
- limita campos, tamaño de valores y frecuencia de envíos;
- no se guardan secretos en el diseño.

## SEO / analítica

El renderer conserva:

- `wp_head()`;
- `wp_body_open()`;
- `wp_footer()`.

Por eso Rank Math, GTM y otras integraciones basadas en hooks pueden seguir ejecutándose. Debe validarse en WordPress real antes de merge final.

## Commits de implementación

- `6d479e49b824a4000a302c0e8b8d7d594b9ae05b` — Landing Manager / CPT y editor HTML-CSS-JS.
- `309e258fb3b3c500acad10111b7e049eac35f03a` — pipeline inicial de formularios nativos.
- `bc313f2fcfcc285590041eaa37978a6cf68ff921` — renderer completo de Landings.
- `dbcc8e40f4f167174ab96f519593ce568f7112aa` — template frontend de Landing.
- `d64fa2b32df465a252656a97184037899bb00d67` — JavaScript de formularios y `dataLayer`.
- `4aa06cd787586db23487fa06513db08dd28e4c93` — estilos estructurales mínimos.
- `be3d9bed9096ade9016582989e9c4fa4c0196ec2` — carga del CSS base en el renderer.
- `338da903f738029171f5f5fc69aeb9e327c7c001` — wiring del módulo / versión `0.4.0-dev`.
- `6ae3ef055b3c714f2c4c3556caeda817c4718663` — flush controlado de reglas `/landing/`.
- `e347832dbbe253bdb69b89aa620dbabf6ed6a3fd` — vista de envíos en admin + compatibilidad sin mbstring.
- `cedf7b56ef461e7322f76561044be220d68a539b` — HTML de Landing Starter.
- `3e4a5bf4be8adf5a6ec0ec2137bc705e7cf10539` — CSS de Landing Starter.
- `26d0d5897eec53fcc6e94785d4bf26522276e5fd` — JS de Landing Starter.
- `212562e59e76d66ecf81605e225ed75f5db99b13` — instrucciones Landing Starter.
- `91a4a88fd0a21b4214aab2aa1997e0eb9c536ad4` — ADR-0002.

## QA requerido

Antes de merge/productivo:

- CI PHP 8.0 / 8.3;
- CI JavaScript;
- crear Landing Starter real;
- comprobar permalink `/landing/.../`;
- comprobar ausencia de template Elementor;
- comprobar `wp_head`, Rank Math y canonical;
- comprobar GTM/dataLayer;
- enviar formulario real;
- comprobar registro en Envíos Landings;
- comprobar correo;
- comprobar honeypot/rate limit de forma no destructiva;
- comprobar desktop/mobile;
- comprobar Header/Footer HUB opcionales.

## Pendiente posterior

- importar ZIP directamente desde Claude/ChatGPT;
- selector de campos del formulario;
- preview privado;
- versionado/rollback de Landings;
- mapa de performance y asset stripping agresivo;
- publicación en URL raíz como Page cuando sea necesario;
- bridges específicos Tibox/WebOps o Prodata mediante el hook de lead creado.
