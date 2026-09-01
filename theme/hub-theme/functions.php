<?php
/**
 * HUB Theme — chasis mínimo.
 *
 * El theme no define identidad visual ni contiene componentes. Su única
 * responsabilidad es entregar un documento WordPress correcto y ceder Header,
 * Footer y contenido a Constructor HUB cuando el plugin está activo.
 *
 * Si el plugin se desactiva, el sitio sigue navegable: eso es lo que hace que
 * cambiar de theme deje de ser una decisión irreversible.
 */

if (!defined('ABSPATH')) {
    exit;
}

define('HUB_THEME_VERSION', '1.0.0');

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('Menú principal', 'hub-theme'),
        'footer' => __('Menú del pie', 'hub-theme'),
        'secondary' => __('Menú secundario', 'hub-theme'),
    ]);

    load_theme_textdomain('hub-theme', get_template_directory() . '/languages');
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style('hub-theme', get_stylesheet_uri(), [], HUB_THEME_VERSION);
});

/**
 * True when Constructor HUB can render a region for us.
 */
function hub_theme_has_constructor_hub(): bool {
    return class_exists('HUB_Tibox_Render') && class_exists('HUB_Tibox_Regions');
}

/**
 * Renders a HUB region, or nothing when the plugin is absent.
 *
 * The theme deliberately has no fallback markup for the header and footer: an
 * invented header would be one more visual decision living in the wrong layer.
 * What it does provide is a navigable fallback, in `hub_theme_fallback_header()`.
 */
function hub_theme_region(string $region): void {
    if (!hub_theme_has_constructor_hub()) {
        if ($region === 'header') {
            hub_theme_fallback_header();
        }

        return;
    }

    $design_id = HUB_Tibox_Regions::active_design($region);

    if ($design_id > 0) {
        HUB_Tibox_Render::instance()->output($design_id, [
            'class' => 'hub-region hub-region--' . sanitize_html_class($region),
        ]);

        return;
    }

    if ($region === 'header') {
        hub_theme_fallback_header();
    }
}

/**
 * Minimal navigation so the site is usable before any HUB header exists.
 */
function hub_theme_fallback_header(): void {
    ?>
    <header class="hub-theme-header" style="padding:20px var(--hub-gutter);display:flex;gap:24px;align-items:center;justify-content:space-between;flex-wrap:wrap;max-width:var(--hub-container);margin:0 auto;">
        <div>
            <?php if (has_custom_logo()) : ?>
                <?php the_custom_logo(); ?>
            <?php else : ?>
                <a href="<?php echo esc_url(home_url('/')); ?>" style="font-weight:700;text-decoration:none;color:inherit;">
                    <?php bloginfo('name'); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
        if (has_nav_menu('primary')) {
            wp_nav_menu([
                'theme_location' => 'primary',
                'container' => 'nav',
                'menu_class' => 'hub-theme-nav',
                'depth' => 2,
            ]);
        }
        ?>
    </header>
    <?php
}
