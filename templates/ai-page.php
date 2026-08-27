<?php

if (!defined('ABSPATH')) {
    exit;
}

$plugin = TIBOX_AI_Frontend::instance();
$page_template = $plugin->current_page_template_path();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="tbx-ai-skip" href="#tbx-main">Saltar al contenido</a>

<header class="tbx-ai-header" id="tbx-ai-header">
    <div class="tbx-ai-shell tbx-ai-header__inner">
        <div class="tbx-ai-brand">
            <?php if (has_custom_logo()) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a class="tbx-ai-brand__text" href="<?php echo esc_url(home_url('/')); ?>">
                    <?php echo esc_html(get_bloginfo('name')); ?>
                </a>
            <?php endif; ?>
        </div>

        <nav class="tbx-ai-nav" aria-label="Navegación principal">
            <a href="<?php echo esc_url(home_url('/')); ?>">Inicio</a>
            <a href="<?php echo esc_url(home_url('/nosotros/')); ?>">Nosotros</a>
            <a href="<?php echo esc_url(home_url('/eventos/')); ?>">Eventos</a>
            <a href="<?php echo esc_url(home_url('/blog/')); ?>">Blog</a>
            <a href="#contacto">Contáctanos</a>
        </nav>

        <a
            class="tbx-ai-services-button"
            href="<?php echo esc_url(home_url('/servicios-ti-empresas/')); ?>"
            data-open-tibox-mega-menu
            aria-label="Abrir áreas de servicios Tibox"
        >
            Servicios
            <span aria-hidden="true">☰</span>
        </a>
    </div>
</header>

<?php require $page_template; ?>

<footer class="tbx-ai-footer">
    <div class="tbx-ai-shell tbx-ai-footer__inner">
        <div>
            <strong>Tibox</strong>
            <p>Soluciones TI, cloud, ciberseguridad, datos y transformación digital.</p>
        </div>
        <div class="tbx-ai-footer__links">
            <a href="<?php echo esc_url(home_url('/aviso-de-privacidad/')); ?>">Aviso de Privacidad</a>
            <a href="https://soporte.tibox.cl/Login/LoginCliente">Portal cliente</a>
        </div>
        <small>© <?php echo esc_html(wp_date('Y')); ?> Tibox. Todos los derechos reservados.</small>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
