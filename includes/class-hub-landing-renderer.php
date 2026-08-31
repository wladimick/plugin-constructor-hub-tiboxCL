<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render modes for Constructor HUB landings.
 */
final class HUB_Tibox_Landing_Renderer
{
    private static ?self $instance = null;
    private HUB_Tibox_Landing_Manager $landings;
    private HUB_Tibox_Landing_Forms $forms;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                HUB_Tibox_Landing_Manager::instance(),
                HUB_Tibox_Landing_Forms::instance()
            );
        }
        return self::$instance;
    }

    private function __construct(HUB_Tibox_Landing_Manager $landings, HUB_Tibox_Landing_Forms $forms)
    {
        $this->landings = $landings;
        $this->forms = $forms;

        add_action('template_redirect', [$this, 'maybe_render_standalone'], -10);
        add_filter('template_include', [$this, 'template_include'], 95);
        add_filter('body_class', [$this, 'body_class']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 30);
        add_action('wp_head', [$this, 'print_landing_css'], 110);
        add_action('wp_footer', [$this, 'print_landing_js'], 110);
    }

    public function maybe_render_standalone(): void
    {
        if (!$this->is_landing_request()) {
            return;
        }
        $landing_id = get_queried_object_id();
        if ($this->landings->get_mode($landing_id) !== HUB_Tibox_Landing_Manager::MODE_STANDALONE) {
            return;
        }
        $html = trim($this->landings->get_full_html($landing_id));
        if ($html === '') {
            return;
        }
        HUB_Tibox_Landing_Document::render($html, $landing_id);
    }

    public function template_include(string $template): string
    {
        if (!$this->is_hub_request()) {
            return $template;
        }
        $landing_template = TIBOX_AI_FRONTEND_DIR . 'templates/landing-page.php';
        return is_readable($landing_template) ? $landing_template : $template;
    }

    public function body_class(array $classes): array
    {
        if ($this->is_landing_request()) {
            $classes[] = 'constructor-hub-tibox';
            $classes[] = 'hub-render-mode-' . sanitize_html_class($this->landings->get_mode(get_queried_object_id()));
        }
        return $classes;
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_landing_request()) {
            return;
        }

        $landing_id = get_queried_object_id();
        $mode = $this->landings->get_mode($landing_id);
        if ($mode === HUB_Tibox_Landing_Manager::MODE_LEGACY) {
            return;
        }

        if ($mode === HUB_Tibox_Landing_Manager::MODE_HUB) {
            wp_enqueue_style(
                'constructor-hub-landing-base',
                TIBOX_AI_FRONTEND_URL . 'assets/css/landing-base.css',
                [],
                TIBOX_AI_FRONTEND_VERSION
            );
        }

        wp_enqueue_script(
            'constructor-hub-landing-form',
            TIBOX_AI_FRONTEND_URL . 'assets/js/landing-form.js',
            [],
            TIBOX_AI_FRONTEND_VERSION,
            true
        );
        wp_localize_script('constructor-hub-landing-form', 'HubLandingFormConfig', [
            'endpoint' => esc_url_raw($this->forms->endpoint_url()),
            'landingId' => $landing_id,
            'landingUrl' => esc_url_raw(get_permalink($landing_id)),
            'pageTitle' => sanitize_text_field(wp_get_document_title()),
            'successMessage' => $this->landings->get_success_message($landing_id),
            'eventName' => 'form_submit',
        ]);
    }

    public function print_landing_css(): void
    {
        if (!$this->is_hub_request()) return;
        $css = trim($this->landings->get_css(get_queried_object_id()));
        if ($css !== '') {
            echo "\n<style id=\"constructor-hub-landing-css\">\n";
            echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored CSS.
            echo "\n</style>\n";
        }

        if ($this->should_render_hub_chrome(get_queried_object_id())) {
            $component_css = trim(HUB_Tibox_Component_Manager::instance()->get_active_css());
            if ($component_css !== '') {
                echo "\n<style id=\"constructor-hub-components-css\">\n";
                echo $component_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-authored CSS.
                echo "\n</style>\n";
            }
        }
    }

    public function print_landing_js(): void
    {
        if (!$this->is_hub_request()) return;
        $js = trim($this->landings->get_js(get_queried_object_id()));
        if ($js !== '') {
            echo "\n<script id=\"constructor-hub-landing-js\">\n";
            echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored JavaScript.
            echo "\n</script>\n";
        }

        if ($this->should_render_hub_chrome(get_queried_object_id())) {
            $component_js = trim(HUB_Tibox_Component_Manager::instance()->get_active_js());
            if ($component_js !== '') {
                echo "\n<script id=\"constructor-hub-components-js\">\n";
                echo $component_js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-authored JS.
                echo "\n</script>\n";
            }
        }
    }

    public function render_landing_html(int $landing_id): void
    {
        $html = HUB_Tibox_Landing_Document::replace_variables($this->landings->get_html($landing_id), $landing_id);
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored HTML.
    }

    public function should_render_hub_chrome(int $landing_id): bool
    {
        if (!$this->landings->uses_hub_chrome($landing_id) || !class_exists('HUB_Tibox_Component_Manager')) {
            return false;
        }
        return HUB_Tibox_Component_Manager::instance()->hybrid_is_configured();
    }

    private function is_landing_request(): bool
    {
        return !is_admin() && is_singular(HUB_Tibox_Landing_Manager::POST_TYPE);
    }

    private function is_hub_request(): bool
    {
        return $this->is_landing_request()
            && $this->landings->get_mode(get_queried_object_id()) === HUB_Tibox_Landing_Manager::MODE_HUB;
    }
}
