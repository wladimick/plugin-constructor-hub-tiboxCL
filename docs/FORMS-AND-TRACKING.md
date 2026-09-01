# Formularios, leads y tracking

Fecha base: 2026-08-31. Vigente desde la Fase 3 de `feat/hub-v0.5-refundacion`.

Este documento es el contrato del backend de formularios de Constructor HUB.
Sirve tanto para una IA que genera el HTML de una landing como para quien
configura GTM o Google Ads.

## Endpoint

```text
POST /wp-json/constructor-hub/v1/landing-submit
Content-Type: application/json
```

Alias histórico, servido por el mismo pipeline:

```text
POST /wp-json/tibox/v1/lead
```

El alias existe para que desactivar el snippet WPCode no rompa el formulario de
la home del MVP. No usarlo en desarrollos nuevos.

## Formulario mínimo que una IA debe producir

```html
<form data-hub-landing-form novalidate>
  <input type="email" name="email" required>
  <label>
    <input type="checkbox" name="privacy" value="1" required>
    Acepto el <a href="{{PRIVACY_URL}}">aviso de privacidad</a>
  </label>

  <!-- Honeypot: debe quedar oculto y fuera del orden de tabulación -->
  <div class="hub-landing-form__honeypot" aria-hidden="true">
    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  </div>

  <button type="submit">Enviar</button>
  <p data-hub-form-status aria-live="polite"></p>
</form>
```

Alternativa: insertar `{{HUB_FORM}}` y dejar que Constructor HUB genere el
formulario estándar con los campos obligatorios configurados en el diseño.

Reglas:

- `email` y `privacy` son siempre obligatorios;
- `data-hub-landing-form` es lo que activa el runtime; sin ese atributo el
  formulario no se envía por AJAX;
- `[data-hub-form-status]` recibe los mensajes de estado;
- no incluir tokens, claves ni endpoints de terceros en el HTML.

El runtime añade automáticamente `hub_token`, `submission_id`, UTMs y click IDs.

## Controles anti spam

| Control | Qué hace | Ajuste |
| --- | --- | --- |
| Honeypot `website` | Si viene con contenido, responde éxito y no guarda nada. El bot no aprende que fue detectado. | — |
| Token firmado `hub_token` | HMAC del id del contenido y del instante de render. Prueba que el formulario lo emitió este sitio y mide cuánto tardó el visitante. Caduca a las 12 horas, por lo que es compatible con caché de página. | `constructor_hub_enforce_form_token` |
| Tiempo mínimo | Rechaza envíos en menos de 3 segundos. | `constructor_hub_min_form_seconds` |
| Presupuesto de intentos | 60 por IP cada 10 minutos. Generoso a propósito: un visitante corrigiendo un RUT no puede quedar bloqueado. | `constructor_hub_max_attempts_per_window` |
| Presupuesto de leads creados | 12 por IP cada 10 minutos y 3 por dirección de correo cada hora. El límite por correo sigue funcionando cuando todos los visitantes comparten la IP de un proxy. | `constructor_hub_max_leads_per_ip`, `constructor_hub_max_leads_per_email` |
| CAPTCHA | No está implementado en el core. Se conecta como adaptador. | `constructor_hub_antispam_check` |

Si el sitio está detrás de Cloudflare o de un balanceador, hay que indicar la
cabecera de IP real en **Constructor HUB → Configuración**. Sin eso, el límite
cuenta a todos los visitantes como uno solo.

## Idempotencia

El runtime genera un `submission_id` y lo guarda en `sessionStorage`. Se reutiliza
en cada reintento y solo se rota tras un éxito confirmado. Del lado del servidor,
`submission_id` tiene índice único: si dos peticiones concurrentes traen el mismo
id, la segunda devuelve el lead existente con `lead_created: false` en lugar de un
error.

Esto es lo que evita el lead duplicado —y la conversión duplicada— cuando la
petición llega pero la respuesta se pierde.

## Respuesta

```json
{
  "success": true,
  "lead_created": true,
  "message": "Gracias. Recibimos tus datos y te contactaremos pronto.",
  "submission_id": "…",
  "lead_id": 123
}
```

`lead_created` distingue "se guardó ahora" de "ya estaba guardado". Es el campo
que decide si se dispara la conversión.

## Contrato de `dataLayer`

Un único evento, solo cuando el lead se creó realmente:

```js
window.dataLayer.push({
  event: 'form_submit',
  form_id: 'hub-landing-123',
  landing_id: 123,
  submission_id: '…',
  source: 'constructor_hub_landing',
  lead_created: true
});
```

En GTM, el trigger de la conversión de Google Ads debe condicionarse a
`lead_created` igual a `true`. Constructor HUB no dispara el evento en un
reintento ni cuando el honeypot lo absorbe, de modo que la deduplicación ocurre
antes de llegar a GTM.

**No** añadir un segundo tag que dispare en `Form Submission` nativo de GTM: el
envío es por `fetch`, así que ese trigger no corresponde y duplicaría la
conversión.

## Conversiones offline de Google Ads

Se almacenan `gclid`, `gbraid` y `wbraid` con cada lead. En
**Constructor HUB → Leads** cada lead puede marcarse como `Nuevo`, `Calificado`,
`Ganado` o `Perdido`, con un valor opcional.

El botón *Conversiones Google Ads* exporta el CSV con las columnas que espera la
importación offline y marca cada fila como exportada, de modo que una segunda
exportación no vuelve a contarla.

Esto permite que Ads optimice hacia leads que se convierten en clientes y no
hacia formularios enviados.

## Correo

```text
Constructor HUB → wp_mail() → WP Mail SMTP → SendGrid / M365 / SMTP
```

Constructor HUB no guarda credenciales de ningún proveedor. Los envíos se
encolan con `wp_schedule_single_event` para que la latencia del SMTP no forme
parte del tiempo de respuesta del formulario, y se registran en
**Constructor HUB → Correo enviado** con su resultado. Si WP-Cron está
deshabilitado y no hay cron externo declarado
(`constructor_hub_has_external_cron`), el envío se hace en línea para que nada
se pierda en silencio.

## Datos personales

Cada lead guarda, además de los campos del formulario:

- `consent_at`: instante del consentimiento;
- `consent_url` y `consent_version`: qué aviso se aceptó;
- `ip_hash`: HMAC de la IP, que prueba origen sin conservar el identificador.

Los leads están registrados en las herramientas de privacidad de WordPress, de
modo que una solicitud de acceso o de supresión se atiende desde
**Herramientas → Exportar/Borrar datos personales** sin tocar la base de datos.

La retención automática se configura en meses en
**Constructor HUB → Configuración**; `0` desactiva el borrado.

Referencia normativa relevante para Chile: Ley 21.719, exigible desde diciembre
de 2026. El RUT es un identificador nacional y debe tratarse como dato sensible
en cuanto a acceso y retención.

## Hooks de integración

```php
// Lead creado. Punto único de integración con CRM, WebOps o cualquier bridge.
do_action('constructor_hub_landing_lead_created', $lead_id, $host_id, $fields, $tracking);

// Alias histórico de la implementación WPCode de Tibox.
do_action('tibox_landing_lead_created', $payload);
```

Cualquier lógica específica de un sitio pertenece a un adaptador enganchado a
estos hooks, nunca al core.
