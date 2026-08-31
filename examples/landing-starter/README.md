# Landing Starter

Ejemplo mínimo para probar el módulo **Landings** de Constructor HUB Tibox.

## Uso

1. WordPress → Constructor HUB → Landings → Nueva landing.
2. Definir título y slug.
3. Copiar `index.html` al campo HTML.
4. Copiar `style.css` al campo CSS.
5. Copiar `script.js` al campo JavaScript.
6. Definir correo de notificación.
7. Publicar.

La URL inicial queda bajo `/landing/<slug>/` salvo que el sitio modifique la base mediante el filtro `constructor_hub_landing_rewrite_slug`.

## Formulario

`{{HUB_FORM}}` inserta el formulario nativo del plugin.

El frontend captura automáticamente:

- datos del formulario;
- `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`;
- `gclid`, `gbraid`, `wbraid`;
- URL y título de la landing;
- `submission_id` para idempotencia.

Si el envío se crea correctamente, se dispara a `dataLayer` el evento `form_submit` con `source = constructor_hub_landing`.
