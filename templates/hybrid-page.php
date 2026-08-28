<?php

if (!defined('ABSPATH')) {
    exit;
}

$hub_components = HUB_Tibox_Component_Manager::instance();
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

<a class="hub-skip-link screen-reader-text" href="#hub-main-content">Saltar al contenido</a>

<?php if ($hub_components->get_active_component_id('header') > 0) : ?>
    <?php $hub_components->render_active_component('header'); ?>
<?php endif; ?>

<main id="hub-main-content" class="hub-main-content">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</main>

<?php if ($hub_components->get_active_component_id('footer') > 0) : ?>
    <?php $hub_components->render_active_component('footer'); ?>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
