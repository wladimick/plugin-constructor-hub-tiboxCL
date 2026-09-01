<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ways to place a HUB design inside content the HUB does not own.
 *
 * This is the piece that makes a gradual migration possible at all. Without it
 * the only options are "the whole page is HUB" or "the whole page is Elementor",
 * and replacing a single hero on a live page has no path.
 *
 * All four entry points — shortcode, block, template tag and the Elementor
 * widget in the adapter — resolve to the same renderer.
 */
final class HUB_Tibox_Insertion
{
    public const SHORTCODE = 'hub_design';

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
        add_shortcode(self::SHORTCODE, [$this, 'shortcode']);
        add_action('init', [$this, 'register_block']);

        // Widgets and theme areas are common places to want a HUB fragment.
        add_filter('widget_text', 'do_shortcode');
    }

    /**
     * `[hub_design slug="tibox-hero-v4"]`
     *
     * @param array<string,string>|string $atts
     */
    public function shortcode($atts, ?string $content = null): string
    {
        $atts = shortcode_atts([
            'slug' => '',
            'id' => '',
            'type' => '',
            'class' => '',
            'bare' => '',
        ], is_array($atts) ? $atts : [], self::SHORTCODE);

        $reference = $atts['slug'] !== '' ? $atts['slug'] : $atts['id'];
        $design_id = HUB_Tibox_Design::resolve($reference, $atts['type']);

        if ($design_id <= 0) {
            return $this->missing_design_notice($reference);
        }

        return HUB_Tibox_Render::instance()->render($design_id, [
            'class' => $atts['class'],
            'bare' => $atts['bare'] === '1' || $atts['bare'] === 'true',
        ]);
    }

    /**
     * Server rendered block so the markup never diverges from the shortcode.
     */
    public function register_block(): void
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'hub-design-block',
            TIBOX_AI_FRONTEND_URL . 'assets/js/block-hub-design.js',
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n'],
            TIBOX_AI_FRONTEND_VERSION,
            true
        );

        wp_localize_script('hub-design-block', 'HubDesignBlockData', [
            'designs' => $this->design_choices(),
        ]);

        register_block_type('constructor-hub/design', [
            'api_version' => '2',
            'title' => 'Diseño HUB',
            'description' => 'Inserta un componente de Constructor HUB.',
            'category' => 'design',
            'icon' => 'layout',
            'editor_script' => 'hub-design-block',
            'attributes' => [
                'slug' => ['type' => 'string', 'default' => ''],
                'className' => ['type' => 'string', 'default' => ''],
            ],
            'render_callback' => [$this, 'render_block'],
        ]);
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render_block(array $attributes): string
    {
        $design_id = HUB_Tibox_Design::resolve((string) ($attributes['slug'] ?? ''));
        if ($design_id <= 0) {
            return $this->missing_design_notice((string) ($attributes['slug'] ?? ''));
        }

        return HUB_Tibox_Render::instance()->render($design_id, [
            'class' => (string) ($attributes['className'] ?? ''),
        ]);
    }

    /**
     * Designs offered in the editor, newest first within each type.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function design_choices(): array
    {
        $choices = [];

        foreach (HUB_Tibox_Design::types() as $type => $definition) {
            foreach (HUB_Tibox_Design::list_by_type($type) as $design) {
                if ($design->post_name === '') {
                    continue;
                }

                $choices[] = [
                    'value' => $design->post_name,
                    'label' => sprintf('%s — %s', $definition['label'], $design->post_title),
                ];
            }
        }

        return $choices;
    }

    /**
     * A missing design must be visible to an editor and invisible to a visitor:
     * a broken shortcode printed on a live landing is worse than nothing.
     */
    private function missing_design_notice(string $reference): string
    {
        if (!current_user_can(HUB_Tibox_Capabilities::MANAGE_DESIGNS) && !current_user_can('manage_options')) {
            return '';
        }

        return sprintf(
            '<div class="hub-design hub-design--missing" style="padding:12px;border:1px dashed #b32d2e;color:#b32d2e;font:13px/1.4 system-ui,sans-serif;">%s</div>',
            esc_html(sprintf(
                'Constructor HUB: no se encontró un diseño publicado con la referencia "%s". Este aviso solo lo ves porque puedes administrar diseños.',
                $reference
            ))
        );
    }
}

if (!function_exists('constructor_hub_render')) {
    /**
     * Template tag: `constructor_hub_render('tibox-header-v3')`.
     *
     * Available to themes, mu-plugins and the future HUB Theme.
     *
     * @param array<string,mixed> $args
     */
    function constructor_hub_render(string $reference, array $args = [], bool $echo = true): string
    {
        $design_id = HUB_Tibox_Design::resolve($reference, (string) ($args['type'] ?? ''));
        $html = $design_id > 0 ? HUB_Tibox_Render::instance()->render($design_id, $args) : '';

        if ($echo) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
        }

        return $html;
    }
}
