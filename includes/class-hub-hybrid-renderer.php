<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Hybrid renderer: HUB Header + existing WordPress/Elementor content + HUB Footer.
 *
 * This renderer intentionally does NOT dequeue Elementor or theme assets. The
 * content in the middle may still depend on them. Asset stripping belongs to
 * full HUB mode only.
 */
final class HUB_Tibox_Hybrid_Renderer
{
    private static ?self $instance = null;
    private HUB_Tibox_Component_Manager $components;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(HUB_Tibox_Component_Manager::instance());
        }

        return self::$instance;
    }

    private function __construct(HUB_Tibox_Component_Manager $components)
    {
        $this->components = $components;

        add_filter('template_include', [$this, 'template_include'], 90);
        add_filter('body_class', [$this, 'body_class']);
        add_action('wp_head', [$this, 'print_component_css'], 100);
        add_action('wp_footer', [$this, 'print_component_js'], 100);
    }

    public function template_include(string $template): string
    {
        if (!$this->is_hybrid_request()) {
            return $template;
        }

        $hybrid_template = TIBOX_AI_FRONTEND_DIR . 'templates/hybrid-page.php';
        return is_readable($hybrid_template) ? $hybrid_template : $template;
    }

    public function body_class(array $classes): array
    {
        if ($this->is_hybrid_request()) {
            $classes[] = 'constructor-hub-tibox';
            $classes[] = 'hub-render-mode-hybrid';
        }

        return $classes;
    }

    public function print_component_css(): void
    {
        if (!$this->is_hybrid_request()) {
            return;
        }

        $css = trim($this->components->get_active_css());
        if ($css === '') {
            return;
        }

        echo "\n<style id=\"constructor-hub-components-css\">\n";
        echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored CSS.
        echo "\n</style>\n";
    }

    public function print_component_js(): void
    {
        if (!$this->is_hybrid_request()) {
            return;
        }

        $js = trim($this->components->get_active_js());
        if ($js === '') {
            return;
        }

        echo "\n<script id=\"constructor-hub-components-js\">\n";
        echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored JS.
        echo "\n</script>\n";
    }

    public function is_hybrid_request(): bool
    {
        if (is_admin() || !is_page()) {
            return false;
        }

        $page_id = get_queried_object_id();
        if ($page_id <= 0) {
            return false;
        }

        // The historical full-page MVP keeps priority when explicitly enabled.
        if (get_post_meta($page_id, '_tibox_ai_frontend_enabled', true) === '1') {
            return false;
        }

        return $this->components->should_use_hybrid_for_page($page_id);
    }
}
