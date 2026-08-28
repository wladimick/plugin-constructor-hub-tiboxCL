<main id="tbx-main" class="tbx-home-ai">
    <section class="tbx-hero" aria-label="Presentación principal" tabindex="0">
        <article class="tbx-hero__slide is-active" data-slide="0" aria-hidden="false">
            <img src="<?php echo esc_url(home_url('/wp-content/uploads/2026/03/slide1.webp')); ?>" alt="Soluciones tecnológicas para empresas" width="1920" height="1080" fetchpriority="high">
            <div class="tbx-hero__shade"></div>
            <div class="tbx-ai-shell tbx-hero__inner">
                <div class="tbx-hero__copy">
                    <p class="tbx-kicker tbx-kicker--light">Tecnología para empresas</p>
                    <h1>Impulsa tu negocio<br>con tecnología</h1>
                    <p>Fortalecemos, optimizamos y digitalizamos tu empresa con soluciones TI a la medida de tu negocio.</p>
                    <div class="tbx-hero__actions">
                        <a class="tbx-button tbx-button--primary" href="#servicios">Conocer soluciones <span aria-hidden="true">→</span></a>
                        <a class="tbx-button tbx-button--ghost" href="#contacto">Hablar con Tibox</a>
                    </div>
                </div>
            </div>
        </article>

        <article class="tbx-hero__slide" data-slide="1" aria-hidden="true">
            <img data-src="<?php echo esc_url(home_url('/wp-content/uploads/2026/03/slide2.webp')); ?>" alt="Diseño de entornos digitales" width="1920" height="1080" loading="lazy">
            <div class="tbx-hero__shade"></div>
            <div class="tbx-ai-shell tbx-hero__inner"><div class="tbx-hero__copy"><p class="tbx-kicker tbx-kicker--light">Transformación digital</p><h2>Diseñamos tu<br>entorno digital</h2><p>Conectamos tu negocio con su mejor versión. Integramos tecnología, personas y procesos.</p></div></div>
        </article>

        <article class="tbx-hero__slide" data-slide="2" aria-hidden="true">
            <img data-src="<?php echo esc_url(home_url('/wp-content/uploads/2026/03/slide3.webp')); ?>" alt="Partner integral de soluciones TI" width="1920" height="1080" loading="lazy">
            <div class="tbx-hero__shade"></div>
            <div class="tbx-ai-shell tbx-hero__inner"><div class="tbx-hero__copy"><p class="tbx-kicker tbx-kicker--light">Un ecosistema, un partner</p><h2>Un solo partner,<br>todas tus soluciones TI</h2><p>Innovamos contigo para lograr resultados reales con soluciones tecnológicas de alto impacto.</p></div></div>
        </article>

        <button class="tbx-hero__arrow tbx-hero__arrow--prev" type="button" aria-label="Ver diapositiva anterior">‹</button>
        <button class="tbx-hero__arrow tbx-hero__arrow--next" type="button" aria-label="Ver diapositiva siguiente">›</button>
        <div class="tbx-hero__dots" aria-label="Seleccionar diapositiva">
            <button class="is-active" type="button" data-go="0" aria-label="Ir a diapositiva 1" aria-current="true"></button>
            <button type="button" data-go="1" aria-label="Ir a diapositiva 2"></button>
            <button type="button" data-go="2" aria-label="Ir a diapositiva 3"></button>
        </div>
    </section>

    <section class="tbx-section tbx-why" aria-labelledby="tbx-why-title">
        <div class="tbx-ai-shell tbx-why__grid">
            <div data-reveal>
                <p class="tbx-kicker">Conoce Tibox</p>
                <h2 id="tbx-why-title">Tecnología que entiende <span>tu negocio</span></h2>
                <p class="tbx-lead">Empoderamos a las organizaciones para impulsar su crecimiento a través de la transformación digital.</p>
                <p class="tbx-statement">Integramos soluciones TI innovadoras, simplificamos la complejidad tecnológica, mejoramos la eficiencia y fomentamos un entorno colaborativo y altamente productivo.</p>
            </div>
            <div class="tbx-stats" data-reveal>
                <div class="tbx-stats__number"><span aria-hidden="true">+</span><strong data-count="21">21</strong></div>
                <p>Años acompañando a empresas en sus desafíos tecnológicos.</p>
                <div class="tbx-stats__tags"><span>Soluciones integrales</span><span>Presencia nacional e internacional</span><span>Profesionales certificados</span></div>
            </div>
        </div>
    </section>

    <section class="tbx-section tbx-services" id="servicios" aria-labelledby="tbx-services-title">
        <div class="tbx-ai-shell">
            <div class="tbx-heading" data-reveal>
                <p class="tbx-kicker">Soluciones de punta a punta</p>
                <h2 id="tbx-services-title">Seis áreas. <span>Un solo partner.</span></h2>
                <p>Diseñamos un ecosistema de servicios para responder a los desafíos TI que enfrentan hoy las empresas.</p>
            </div>

            <div class="tbx-services__grid">
                <?php
                $services = [
                    ['01', 'Infraestructura TI & NOC', '/servicios-ti-empresas/infraestructura-ti-noc/', 'Continuidad, soporte y monitoreo proactivo.'],
                    ['02', 'Ciberseguridad & SOC', '/servicios-ti-empresas/ciberseguridad-soc/', 'Prevención, monitoreo y respuesta ante amenazas.'],
                    ['03', 'Soluciones Cloud', '/servicios-ti-empresas/soluciones-cloud/', 'Nube segura, escalable y alineada al negocio.'],
                    ['04', 'Soluciones Inteligentes & Automatización', '/soluciones-automatizacion/', 'Procesos más simples con automatización e IA.'],
                    ['05', 'Analítica de Datos & Inteligencia Artificial', '/servicios-ti-empresas/analitica-de-datos-inteligencia-artificial/', 'Convierte datos en decisiones y capacidades inteligentes.'],
                    ['06', 'Consultoría TI & Transformación Digital', '/servicios-ti-empresas/consultoria-ti-transformacion-digital/', 'Estrategia y acompañamiento para evolucionar con foco.'],
                ];
                foreach ($services as $service) :
                ?>
                    <article class="tbx-service" data-reveal>
                        <span class="tbx-service__number"><?php echo esc_html($service[0]); ?></span>
                        <h3><?php echo esc_html($service[1]); ?></h3>
                        <p><?php echo esc_html($service[3]); ?></p>
                        <a href="<?php echo esc_url(home_url($service[2])); ?>">Saber más <span aria-hidden="true">→</span></a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="tbx-section tbx-ecosystem" aria-labelledby="tbx-ecosystem-title">
        <div class="tbx-ai-shell tbx-ecosystem__grid">
            <div data-reveal>
                <p class="tbx-kicker">Ecosistema tecnológico</p>
                <h2 id="tbx-ecosystem-title">Trabajamos con <span>líderes</span></h2>
                <p>Integramos plataformas y tecnologías líderes para diseñar soluciones confiables, seguras y preparadas para crecer.</p>
            </div>
            <div class="tbx-tech-grid" data-reveal aria-label="Tecnologías y partners">
                <span>Microsoft 365</span><span>Azure</span><span>AWS</span><span>Google Cloud</span><span>Fortinet</span><span>Cisco</span><span>Veeam</span><span>OpenAI</span>
            </div>
        </div>
    </section>

    <section class="tbx-section tbx-content" aria-label="Contenido y eventos">
        <div class="tbx-ai-shell tbx-content__grid">
            <a class="tbx-content__card" href="<?php echo esc_url(home_url('/blog/')); ?>" data-reveal>
                <span class="tbx-kicker">Ideas para avanzar</span>
                <h2>Blog TI</h2>
                <p>Tendencias, análisis y recomendaciones para tomar mejores decisiones tecnológicas.</p>
                <strong>Explorar artículos <span aria-hidden="true">→</span></strong>
            </a>
            <a class="tbx-content__card tbx-content__card--dark" href="<?php echo esc_url(home_url('/eventos/')); ?>" data-reveal>
                <span class="tbx-kicker tbx-kicker--light">Aprendamos juntos</span>
                <h2>Eventos</h2>
                <p>Espacios para compartir conocimiento, conversar sobre desafíos reales y acercar la tecnología a tu organización.</p>
                <strong>Ver eventos <span aria-hidden="true">→</span></strong>
            </a>
        </div>
    </section>

    <section class="tbx-section tbx-contact" id="contacto" aria-labelledby="tbx-contact-title">
        <div class="tbx-ai-shell tbx-contact__grid">
            <div class="tbx-contact__intro" data-reveal>
                <p class="tbx-kicker tbx-kicker--light">Hablemos de tu próximo desafío</p>
                <h2 id="tbx-contact-title">Conversemos</h2>
                <p>Cuéntanos qué necesitas y nuestro equipo te ayudará a encontrar el mejor camino tecnológico.</p>
                <div class="tbx-contact__detail"><strong>Teléfono</strong><a href="tel:+56752600330">+56 75 260 0330</a><span>Opción 3 · Área Comercial</span></div>
                <div class="tbx-contact__detail"><strong>Horario</strong><span>Lunes a viernes · 8:30 a 18:00 hrs.</span></div>
            </div>

            <form class="tbx-form" data-tibox-lead-form novalidate data-reveal>
                <div class="tbx-form__status" data-form-status role="status" aria-live="polite"></div>
                <div class="tbx-form__row">
                    <label>Nombre <span aria-hidden="true">*</span><input type="text" name="name" autocomplete="name" required></label>
                    <label>Email corporativo <span aria-hidden="true">*</span><input type="email" name="email" autocomplete="email" required></label>
                </div>
                <div class="tbx-form__row">
                    <label>Teléfono<input type="tel" name="phone" autocomplete="tel" inputmode="tel"></label>
                    <label>Empresa <span aria-hidden="true">*</span><input type="text" name="company" autocomplete="organization" required></label>
                </div>
                <div class="tbx-form__row">
                    <label>RUT empresa <span aria-hidden="true">*</span><input type="text" name="rut" inputmode="text" placeholder="76.123.456-7" required></label>
                    <label>Área de interés<select name="area"><option value="">Selecciona un área</option><option>Infraestructura TI & NOC</option><option>Ciberseguridad & SOC</option><option>Soluciones Cloud</option><option>Soluciones Inteligentes & Automatización</option><option>Analítica de Datos & Inteligencia Artificial</option><option>Consultoría TI & Transformación Digital</option></select></label>
                </div>
                <label>Mensaje<textarea name="message" rows="4" placeholder="Cuéntanos brevemente qué desafío necesitas resolver."></textarea></label>
                <label class="tbx-form__privacy"><input type="checkbox" name="privacy" value="1" required><span>He leído y acepto el <a href="<?php echo esc_url(home_url('/aviso-de-privacidad/')); ?>" target="_blank" rel="noopener noreferrer">Aviso de Privacidad</a>.</span></label>
                <label class="tbx-honeypot" aria-hidden="true">Sitio web<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                <button class="tbx-button tbx-button--primary tbx-form__submit" type="submit">Enviar consulta <span aria-hidden="true">→</span></button>
                <p class="tbx-form__note">Tus datos se envían al endpoint seguro de Tibox y quedan registrados en WordPress.</p>
            </form>
        </div>
    </section>
</main>
