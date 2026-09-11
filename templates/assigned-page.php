<?php
/**
 * HUB shell for an existing WordPress Page assigned to a design.
 *
 * The queried object remains the original Page, so Rank Math, canonical, schema,
 * page-level plugins and WordPress metadata continue resolving against the same
 * post ID and permalink. Only the visual body is delegated to the assigned HUB
 * design.
 */

if (!defined('ABSPATH')) {
    exit;
}

$hub_assignment = HUB_Tibox_Page_Assignment::instance();
$hub_render = HUB_Tibox_Render::instance();
$hub_design_id = $hub_assignment->active_design_id();

if ($hub_design_id <= 0) {
    return;
}

$hub_use_chrome = HUB_Tibox_Design::uses_chrome($hub_design_id);

if (!$hub_use_chrome) {
    // Preserve wp_body_open()/wp_footer() for analytics and plugins while
    // preventing globally configured HUB regions from leaking into a canvas
    // design that explicitly opted out of chrome.
    remove_action('wp_body_open', [$hub_render, 'inject_header_region'], 5);
    remove_action('wp_footer', [$hub_render, 'inject_footer_region'], 5);
}

$hub_header = $hub_use_chrome && HUB_Tibox_Regions::mode('header') === HUB_Tibox_Regions::MODE_REPLACE
    ? $hub_render->render_region('header')
    : '';
$hub_footer = $hub_use_chrome && HUB_Tibox_Regions::mode('footer') === HUB_Tibox_Regions::MODE_REPLACE
    ? $hub_render->render_region('footer')
    : '';
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
    <?php $hub_render->output($hub_design_id); ?>
</main>

<?php
echo $hub_footer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
?>

<?php wp_footer(); ?>
</body>
</html>
