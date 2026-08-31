<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Full-page renderer for HUB Landings.
 *
 * It bypasses the active theme template while preserving WordPress head/body/
 * footer hooks. Therefore Rank Math, GTM and other hook-based integrations can
 * continue working without Elementor rendering the visual page.
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

        add_filter('template_include', [$this, 'template_include'], 95);
        add_filter('body_class', [$this, 'body_class']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 30);
        add_action('wp_head', [$this, 'print_landing_css'], 110);
        add_action('wp_footer', [$this, 'print_landing_js'], 110);
    }

    public function template_include(string $template): string
    {
        if (!$this->is_landing_request()) {
            return $template;
        }

        $landing_template = TIBOX_AI_FRONTEND_DIR . 'templates/landing-page.php';
        return is_readable($landing_template) ? $landing_template : $template;
    }

    public function body_class(array $classes): array
    {
        if ($this->is_landing_request()) {
            $classes[] = 'constructor-hub-tibox';
            $classes[] = 'hub-render-mode-landing';
        }

        return $classes;
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_landing_request()) {
            return;
        }

        $landing_id = get_queried_object_id();

        wp_enqueue_style(
            'constructor-hub-landing-base',
            TIBOX_AI_FRONTEND_URL . 'assets/css/landing-base.css',
            [],
            TIBOX_AI_FRONTEND_VERSION
        );

        wp_enqueue_script(
            'constructor-hub-landing-form',
            TIBOX_AI_FRONTEND_URL . 'assets/js/landing-form.js',
            [],
            TIBOX_AI_FRONTEND_VERSION,
            true
        );

        wp_localize_script(
            'constructor-hub-landing-form',
            'HubLandingFormConfig',
            [
                'endpoint' => esc_url_raw($this->forms->endpoint_url()),
                'landingId' => $landing_id,
                'landingUrl' => esc_url_raw(get_permalink($landing_id)),
                'pageTitle' => sanitize_text_field(wp_get_document_title()),
                'successMessage' => $this->landings->get_success_message($landing_id),
                'eventName' => 'form_submit',
            ]
        );
    }

    public function print_landing_css(): void
    {
        if (!$this->is_landing_request()) {
            return;
        }

        $landing_id = get_queried_object_id();
        $chunks = [];

        if ($this->should_render_hub_chrome($landing_id)) {
            $component_css = trim(HUB_Tibox_Component_Manager::instance()->get_active_css());
            if ($component_css !== '') {
                $chunks[] = "/* HUB Header/Footer */\n" . $component_css;
            }
        }

        $landing_css = trim($this->landings->get_css($landing_id));
        if ($landing_css !== '') {
            $chunks[] = "/* HUB Landing */\n" . $landing_css;
        }

        if ($chunks === []) {
            return;
        }

        echo "\n<style id=\"constructor-hub-landing-css\">\n";
        echo implode("\n\n", $chunks); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored CSS.
        echo "\n</style>\n";
    }

    public function print_landing_js(): void
    {
        if (!$this->is_landing_request()) {
            return;
        }

        $landing_id = get_queried_object_id();
        $chunks = [];

        if ($this->should_render_hub_chrome($landing_id)) {
            $component_js = trim(HUB_Tibox_Component_Manager::instance()->get_active_js());
            if ($component_js !== '') {
                $chunks[] = "/* HUB Header/Footer */\n" . $component_js;
            }
        }

        $landing_js = trim($this->landings->get_js($landing_id));
        if ($landing_js !== '') {
            $chunks[] = "/* HUB Landing */\n" . $landing_js;
        }

        if ($chunks === []) {
            return;
        }

        echo "\n<script id=\"constructor-hub-landing-js\">\n";
        echo implode("\n\n", $chunks); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored JavaScript.
        echo "\n</script>\n";
    }

    public function render_landing_html(int $landing_id): void
    {
        $html = $this->landings->get_html($landing_id);
        echo $this->replace_variables($html, $landing_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted administrator-authored HTML.
    }

    public function should_render_hub_chrome(int $landing_id): bool
    {
        if (!$this->landings->uses_hub_chrome($landing_id)) {
            return false;
        }

        if (!class_exists('HUB_Tibox_Component_Manager')) {
            return false;
        }

        $components = HUB_Tibox_Component_Manager::instance();
        return $components->hybrid_is_configured();
    }

    private function is_landing_request(): bool
    {
        return !is_admin() && is_singular(HUB_Tibox_Landing_Manager::POST_TYPE);
    }

    private function replace_variables(string $html, int $landing_id): string
    {
        $logo_html = has_custom_logo() ? get_custom_logo() : esc_html(get_bloginfo('name'));
        $logo_id = (int) get_theme_mod('custom_logo', 0);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'full') : '';

        $variables = [
            '{{SITE_URL}}' => esc_url(home_url('/')),
            '{{HOME_URL}}' => esc_url(home_url('/')),
            '{{SITE_NAME}}' => esc_html(get_bloginfo('name')),
            '{{CURRENT_YEAR}}' => esc_html(wp_date('Y')),
            '{{CUSTOM_LOGO}}' => (string) $logo_html,
            '{{CUSTOM_LOGO_URL}}' => esc_url($logo_url),
            '{{LANDING_URL}}' => esc_url(get_permalink($landing_id)),
            '{{LANDING_TITLE}}' => esc_html(get_the_title($landing_id)),
            '{{HUB_FORM}}' => $this->forms->render_default_form($landing_id),
        ];

        return strtr($html, $variables);
    }
}
