<?php
/**
 * Cabecera del documento.
 *
 * Los hooks de WordPress se conservan intactos para que Rank Math, GTM y
 * cualquier snippet global sigan funcionando exactamente igual que con el theme
 * anterior.
 */

if (!defined('ABSPATH')) {
    exit;
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

<a class="screen-reader-text" href="#hub-theme-content"><?php esc_html_e('Saltar al contenido', 'hub-theme'); ?></a>

<?php hub_theme_region('header'); ?>
