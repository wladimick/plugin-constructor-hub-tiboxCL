(() => {
    'use strict';

    const root = document.querySelector('.tbx-home-ai');
    if (!root || root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const hero = root.querySelector('.tbx-hero');
    const slides = [...root.querySelectorAll('.tbx-hero__slide')];
    const dots = [...root.querySelectorAll('.tbx-hero__dots button')];
    let current = 0;
    let timer = 0;
    let pointerStart = null;

    const loadSlide = (slide) => {
        const img = slide?.querySelector('img[data-src]');
        if (!img) return;
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
    };

    const showSlide = (index) => {
        if (!slides.length) return;
        current = (index + slides.length) % slides.length;
        loadSlide(slides[current]);
        loadSlide(slides[(current + 1) % slides.length]);

        slides.forEach((slide, i) => {
            const active = i === current;
            slide.classList.toggle('is-active', active);
            slide.setAttribute('aria-hidden', String(!active));
        });

        dots.forEach((dot, i) => {
            const active = i === current;
            dot.classList.toggle('is-active', active);
            active ? dot.setAttribute('aria-current', 'true') : dot.removeAttribute('aria-current');
        });
    };

    const stop = () => window.clearInterval(timer);
    const start = () => {
        stop();
        if (!reduceMotion && !document.hidden && slides.length > 1) {
            timer = window.setInterval(() => showSlide(current + 1), 6000);
        }
    };

    root.querySelector('.tbx-hero__arrow--prev')?.addEventListener('click', () => { showSlide(current - 1); start(); });
    root.querySelector('.tbx-hero__arrow--next')?.addEventListener('click', () => { showSlide(current + 1); start(); });
    dots.forEach((dot) => dot.addEventListener('click', () => { showSlide(Number(dot.dataset.go || 0)); start(); }));

    hero?.addEventListener('mouseenter', stop);
    hero?.addEventListener('mouseleave', start);
    hero?.addEventListener('focusin', stop);
    hero?.addEventListener('focusout', start);
    hero?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') showSlide(current - 1);
        if (event.key === 'ArrowRight') showSlide(current + 1);
    });
    hero?.addEventListener('pointerdown', (event) => { pointerStart = event.clientX; });
    hero?.addEventListener('pointerup', (event) => {
        if (pointerStart === null) return;
        const distance = event.clientX - pointerStart;
        if (Math.abs(distance) > 55) showSlide(current + (distance < 0 ? 1 : -1));
        pointerStart = null;
        start();
    });
    document.addEventListener('visibilitychange', () => document.hidden ? stop() : start());
    showSlide(0);
    start();

    const count = root.querySelector('[data-count]');
    let counted = false;
    const animateCount = () => {
        if (!count || counted || reduceMotion) return;
        counted = true;
        const target = Number(count.dataset.count || 0);
        const started = performance.now();
        const tick = (now) => {
            const progress = Math.min((now - started) / 900, 1);
            count.textContent = String(Math.round(target * (1 - Math.pow(1 - progress, 3))));
            if (progress < 1) requestAnimationFrame(tick);
        };
        count.textContent = '0';
        requestAnimationFrame(tick);
    };

    const reveals = root.querySelectorAll('[data-reveal]');
    if ('IntersectionObserver' in window && !reduceMotion) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                if (entry.target.classList.contains('tbx-stats')) animateCount();
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -35px' });
        reveals.forEach((element) => observer.observe(element));
    } else {
        reveals.forEach((element) => element.classList.add('is-visible'));
        animateCount();
    }

    const form = root.querySelector('[data-tibox-lead-form]');
    if (!form) return;

    const status = form.querySelector('[data-form-status]');
    const submit = form.querySelector('button[type="submit"]');

    const setStatus = (message, type) => {
        if (!status) return;
        status.textContent = message;
        status.className = `tbx-form__status is-visible ${type === 'success' ? 'is-success' : 'is-error'}`;
    };

    const makeSubmissionId = () => {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return `tbx-${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    const query = new URLSearchParams(window.location.search);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!form.reportValidity()) return;

        const data = new FormData(form);
        const payload = {
            submission_id: makeSubmissionId(),
            form_id: window.TiboxAIFrontend?.formId || 'tibox-ai-home',
            name: String(data.get('name') || '').trim(),
            email: String(data.get('email') || '').trim(),
            phone: String(data.get('phone') || '').trim(),
            company: String(data.get('company') || '').trim(),
            rut: String(data.get('rut') || '').trim(),
            area: String(data.get('area') || '').trim(),
            users: '',
            message: String(data.get('message') || '').trim(),
            privacy: data.get('privacy') === '1',
            website: String(data.get('website') || '').trim(),
            landing_url: window.TiboxAIFrontend?.pageUrl || window.location.href.split('#')[0],
            landing_path: window.TiboxAIFrontend?.pagePath || window.location.pathname,
            page_title: window.TiboxAIFrontend?.pageTitle || document.title,
            utm_source: query.get('utm_source') || '',
            utm_medium: query.get('utm_medium') || '',
            utm_campaign: query.get('utm_campaign') || '',
            utm_term: query.get('utm_term') || '',
            utm_content: query.get('utm_content') || '',
            gclid: query.get('gclid') || '',
            gbraid: query.get('gbraid') || '',
            wbraid: query.get('wbraid') || '',
        };

        const endpoint = window.TiboxAIFrontend?.restEndpoint;
        if (!endpoint) {
            setStatus('No fue posible encontrar el endpoint del formulario.', 'error');
            return;
        }

        if (submit) {
            submit.disabled = true;
            submit.dataset.originalText = submit.innerHTML;
            submit.textContent = 'Enviando…';
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            const result = await response.json().catch(() => ({}));

            if (!response.ok || !result.success) {
                const firstError = result.errors ? Object.values(result.errors)[0] : '';
                throw new Error(firstError || result.message || 'No fue posible enviar tu solicitud.');
            }

            setStatus(result.message || 'Recibimos tu solicitud. Te contactaremos pronto.', 'success');
            form.reset();

            if (result.lead_created && Array.isArray(window.dataLayer)) {
                window.dataLayer.push({
                    event: 'form_submit',
                    form_id: payload.form_id,
                    submission_id: result.submission_id || payload.submission_id,
                    source: 'tibox_ai_frontend',
                });
            }
        } catch (error) {
            setStatus(error?.message || 'Ocurrió un error al enviar el formulario.', 'error');
        } finally {
            if (submit) {
                submit.disabled = false;
                submit.innerHTML = submit.dataset.originalText || 'Enviar consulta →';
            }
        }
    });
})();
