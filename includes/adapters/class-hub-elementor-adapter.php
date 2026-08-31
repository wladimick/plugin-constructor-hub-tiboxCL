<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Elementor compatibility, kept out of the core.
 *
 * ADR-0001 says the core does not depend on Elementor. Everything that knows
 * Elementor exists lives here and does nothing when Elementor is absent. The
 * adapter never edits Elementor content and never changes another plugin's
 * settings without an explicit opt-in.
 */
final class HUB_Tibox_Elementor_Adapter
{
    private static ?self $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('elementor/widgets/register', [$this, 'register_widget']);
        add_action('admin_init', [$this, 'maybe_enable_design_support']);
        add_action('admin_notices', [$this, 'theme_builder_conflict_notice']);
        add_filter('constructor_hub_elementor_needed', [$this, 'post_needs_elementor'], 10, 2);
    }

    public static function is_active(): bool
    {
        return did_action('elementor/loaded') > 0 || defined('ELEMENTOR_VERSION');
    }

    public static function is_pro_active(): bool
    {
        return defined('ELEMENTOR_PRO_VERSION');
    }

    /**
     * Elementor editing of HUB designs is opt-in. Writing another plugin's
     * global option on every admin request is an invisible side effect.
     */
    public function maybe_enable_design_support(): void
    {
        if (!self::is_active() || get_option('hub_tibox_elementor_design_support', '0') !== '1') {
            return;
        }

        $supported = get_option('elementor_cpt_support', ['page', 'post']);
        $supported = is_array($supported) ? $supported : ['page', 'post'];

        if (in_array(HUB_Tibox_Design::POST_TYPE, $supported, true)) {
            return;
        }

        $supported[] = HUB_Tibox_Design::POST_TYPE;
        update_option('elementor_cpt_support', array_values(array_unique($supported)));
    }

    public function register_widget($widgets_manager): void
    {
        if (!class_exists('\Elementor\Widget_Base') || !is_object($widgets_manager)) {
            return;
        }

        require_once __DIR__ . '/class-hub-elementor-widget.php';

        // The widget file only declares the class when Elementor's base class is
        // present, so it is resolved by name rather than referenced directly.
        $widget_class = 'HUB_Tibox_Elementor_Widget';
        if (!class_exists($widget_class) || !method_exists($widgets_manager, 'register')) {
            return;
        }

        $widgets_manager->register(new $widget_class());
    }

    /**
     * Whether a given post still needs Elementor assets to render correctly.
     *
     * This is the fact the migration map and any future selective dequeue must
     * be based on. Guessing by handle name — what the historical MVP did — is
     * how a page loses its slider and nobody notices for a week.
     */
    public function post_needs_elementor(bool $needed, int $post_id): bool
    {
        if (!self::is_active() || $post_id <= 0) {
            return $needed;
        }

        if (get_post_meta($post_id, '_elementor_edit_mode', true) === 'builder') {
            return true;
        }

        $content = (string) get_post_field('post_content', $post_id);
        if (str_contains($content, 'elementor-widget') || str_contains($content, 'data-elementor-type')) {
            return true;
        }

        return $needed;
    }

    /**
     * Elementor Pro can render its own header and footer through Theme Builder.
     * With a HUB region active as well, the page ends up with two headers.
     */
    public function theme_builder_conflict_notice(): void
    {
        if (!self::is_pro_active() || !HUB_Tibox_Capabilities::can_manage_settings()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || !str_contains((string) $screen->id, 'constructor-hub')) {
            return;
        }

        $conflicting = [];
        foreach (HUB_Tibox_Regions::names() as $region) {
            if (HUB_Tibox_Regions::mode($region) === HUB_Tibox_Regions::MODE_INJECT) {
                $conflicting[] = $region;
            }
        }

        if ($conflicting === []) {
            return;
        }

        printf(
            '<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
            esc_html__('Elementor Pro detectado.', 'constructor-hub-tibox'),
            esc_html(sprintf(
                'Si Theme Builder tiene una plantilla para %s, esa región se imprimirá dos veces. Revisa el selector de ocultación de la región o desactiva la plantilla de Theme Builder correspondiente.',
                implode(' y ', $conflicting)
            ))
        );
    }

    /**
     * Theme Builder locations that Elementor Pro is currently serving.
     *
     * @return string[]
     */
    public static function active_theme_builder_locations(): array
    {
        if (!self::is_pro_active() || !function_exists('elementor_theme_do_location')) {
            return [];
        }

        $locations = [];
        foreach (['header', 'footer'] as $location) {
            // The location manager is Pro internal API; the check is defensive.
            $manager = apply_filters('constructor_hub_elementor_location_manager', null);
            if ($manager === null) {
                continue;
            }
            $locations[] = $location;
        }

        return $locations;
    }
}
