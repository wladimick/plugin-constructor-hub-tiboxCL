<?php

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main id="hub-theme-content" class="hub-theme-main">
    <h1 class="hub-theme-entry__title"><?php esc_html_e('No encontramos esta página', 'hub-theme'); ?></h1>
    <p><?php esc_html_e('El enlace puede estar desactualizado o la página pudo haberse movido.', 'hub-theme'); ?></p>
    <?php get_search_form(); ?>
    <p><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Volver al inicio', 'hub-theme'); ?></a></p>
</main>

<?php
get_footer();
