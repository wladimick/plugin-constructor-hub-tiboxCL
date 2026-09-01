<?php
/**
 * Document shell owned by Constructor HUB.
 *
 * Used when a viewable design renders in HUB mode, or when a region is
 * configured for full replacement. WordPress hooks are preserved so Rank Math,
 * GTM and any global snippet keep working without the theme markup.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hub_render = HUB_Tibox_Render::instance();
$hub_design_id = $hub_render->current_design_id();
$hub_header = HUB_Tibox_Regions::mode('header') === HUB_Tibox_Regions::MODE_REPLACE
    ? $hub_render->render_region('header')
    : '';
$hub_footer = HUB_Tibox_Regions::mode('footer') === HUB_Tibox_Regions::MODE_REPLACE
    ? $hub_render->render_region('footer')
    : '';

$hub_use_chrome = $hub_design_id > 0 && HUB_Tibox_Design::uses_chrome($hub_design_id);
if (!$hub_use_chrome && $hub_design_id > 0) {
    // A landing in canvas mode deliberately drops the global chrome.
    $hub_header = '';
    $hub_footer = '';
}
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

<?php
echo $hub_header; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
?>

<main id="hub-main-content" class="hub-main-content">
    <?php
    if ($hub_design_id > 0) {
        $hub_render->output($hub_design_id);
    } else {
        while (have_posts()) {
            the_post();
            the_content();
        }
    }
    ?>
</main>

<?php
echo $hub_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
?>

<?php wp_footer(); ?>
</body>
</html>
