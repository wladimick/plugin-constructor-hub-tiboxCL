<?php

if (!defined('ABSPATH')) {
    exit;
}

$landing_id = get_queried_object_id();
$landing_renderer = HUB_Tibox_Landing_Renderer::instance();
$use_hub_chrome = $landing_renderer->should_render_hub_chrome($landing_id);
$hub_components = $use_hub_chrome ? HUB_Tibox_Component_Manager::instance() : null;
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

<a class="hub-skip-link screen-reader-text" href="#hub-landing-content">Saltar al contenido</a>

<?php if ($hub_components !== null) : ?>
    <?php $hub_components->render_active_component('header'); ?>
<?php endif; ?>

<div id="hub-landing-content" class="hub-landing-root" data-hub-landing-id="<?php echo esc_attr((string) $landing_id); ?>">
    <?php $landing_renderer->render_landing_html($landing_id); ?>
</div>

<?php if ($hub_components !== null) : ?>
    <?php $hub_components->render_active_component('footer'); ?>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
