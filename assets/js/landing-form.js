(() => {
    'use strict';

    const config = window.HubLandingFormConfig || {};
    const forms = Array.from(document.querySelectorAll('[data-hub-landing-form]'));
    if (!forms.length || !config.endpoint) return;

    const query = new URLSearchParams(window.location.search);

    const submissionId = () => {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return `hub-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    const formToObject = (form) => {
        const result = {};
        const data = new FormData(form);

        for (const [key, value] of data.entries()) {
            if (Object.prototype.hasOwnProperty.call(result, key)) {
                result[key] = Array.isArray(result[key]) ? result[key] : [result[key]];
                result[key].push(value);
            } else {
                result[key] = value;
            }
        }

        return result;
    };

    const setStatus = (form, message, type) => {
        const status = form.querySelector('[data-hub-form-status]');
        if (!status) return;
        status.textContent = message || '';
        status.dataset.state = type || '';
    };

    forms.forEach((form) => {
        if (form.dataset.hubInitialized === '1') return;
        form.dataset.hubInitialized = '1';

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            if (!form.reportValidity()) return;

            const submit = form.querySelector('button[type="submit"], input[type="submit"]');
            const originalText = submit && submit.tagName === 'BUTTON' ? submit.innerHTML : '';
            const fields = formToObject(form);

            const payload = {
                ...fields,
                landing_id: Number(fields.landing_id || config.landingId || 0),
                submission_id: submissionId(),
                privacy: fields.privacy === '1' || fields.privacy === 'true' || fields.privacy === true,
                landing_url: config.landingUrl || window.location.href.split('#')[0],
                page_title: config.pageTitle || document.title,
                utm_source: query.get('utm_source') || '',
                utm_medium: query.get('utm_medium') || '',
                utm_campaign: query.get('utm_campaign') || '',
                utm_term: query.get('utm_term') || '',
                utm_content: query.get('utm_content') || '',
                gclid: query.get('gclid') || '',
                gbraid: query.get('gbraid') || '',
                wbraid: query.get('wbraid') || '',
            };

            setStatus(form, 'Enviando…', 'loading');
            form.setAttribute('aria-busy', 'true');
            if (submit) {
                submit.disabled = true;
                if (submit.tagName === 'BUTTON') submit.textContent = 'Enviando…';
            }

            try {
                const response = await fetch(config.endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const result = await response.json().catch(() => ({}));
                if (!response.ok || !result.success) {
                    const firstError = result.errors ? Object.values(result.errors)[0] : '';
                    throw new Error(firstError || result.message || 'No fue posible enviar el formulario.');
                }

                setStatus(
                    form,
                    result.message || config.successMessage || 'Gracias. Recibimos tus datos.',
                    'success'
                );
                form.reset();

                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: config.eventName || 'form_submit',
                    form_id: `hub-landing-${payload.landing_id}`,
                    landing_id: payload.landing_id,
                    submission_id: result.submission_id || payload.submission_id,
                    source: 'constructor_hub_landing',
                    lead_created: Boolean(result.lead_created),
                });

                form.dispatchEvent(new CustomEvent('hub:landing-form-success', {
                    bubbles: true,
                    detail: result,
                }));
            } catch (error) {
                setStatus(form, error && error.message ? error.message : 'Ocurrió un error al enviar.', 'error');
                form.dispatchEvent(new CustomEvent('hub:landing-form-error', {
                    bubbles: true,
                    detail: error,
                }));
            } finally {
                form.removeAttribute('aria-busy');
                if (submit) {
                    submit.disabled = false;
                    if (submit.tagName === 'BUTTON' && originalText) submit.innerHTML = originalText;
                }
            }
        });
    });
})();
