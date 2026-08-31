<?php

if (!defined('ABSPATH')) {
    exit;
}

final class TIBOX_AI_Frontend
{
    private const META_ENABLED = '_tibox_ai_frontend_enabled';
    private const META_TEMPLATE = '_tibox_ai_frontend_template';
    private const META_PERFORMANCE = '_tibox_ai_frontend_performance';

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
        add_action('add_meta_boxes_page', [$this, 'add_meta_box']);
        add_action('save_post_page', [$this, 'save_meta_box']);

        add_filter('template_include', [$this, 'template_include'], 99);
        add_filter('body_class', [$this, 'body_class']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 20);
        add_action('wp_enqueue_scripts', [$this, 'strip_heavy_assets'], 999);
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'tibox-ai-frontend',
            'Tibox AI Frontend',
            [$this, 'render_meta_box'],
            'page',
            'side',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field('tibox_ai_frontend_save', 'tibox_ai_frontend_nonce');

        $enabled = get_post_meta($post->ID, self::META_ENABLED, true) === '1';
        $template = (string) get_post_meta($post->ID, self::META_TEMPLATE, true);
        $performance = (string) get_post_meta($post->ID, self::META_PERFORMANCE, true);

        if ($template === '') {
            $template = 'home-ai';
        }

        if (!in_array($performance, ['balanced', 'aggressive'], true)) {
            $performance = 'aggressive';
        }

        $templates = $this->templates();
        ?>
        <p>
            <label>
                <input type="checkbox" name="tibox_ai_frontend_enabled" value="1" <?php checked($enabled); ?>>
                <strong>Usar frontend liviano Tibox AI</strong>
            </label>
        </p>
        <p style="color:#646970;">
            Solo afecta esta página. El resto del sitio continúa usando el tema/Elementor normalmente.
        </p>
        <p>
            <label for="tibox-ai-template"><strong>Plantilla</strong></label><br>
            <select id="tibox-ai-template" name="tibox_ai_frontend_template" style="width:100%;margin-top:6px;">
                <?php foreach ($templates as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($template, $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="tibox-ai-performance"><strong>Optimización</strong></label><br>
            <select id="tibox-ai-performance" name="tibox_ai_frontend_performance" style="width:100%;margin-top:6px;">
                <option value="aggressive" <?php selected($performance, 'aggressive'); ?>>Agresiva (sin Elementor/jQuery)</option>
                <option value="balanced" <?php selected($performance, 'balanced'); ?>>Balanceada</option>
            </select>
        </p>
        <p style="font-size:12px;color:#646970;">
            Rank Math, wp_head(), wp_body_open() y wp_footer() se mantienen para conservar SEO, GTM y compatibilidad con los snippets globales.
        </p>
        <?php
    }

    public function save_meta_box(int $post_id): void
    {
        if (!isset($_POST['tibox_ai_frontend_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['tibox_ai_frontend_nonce']));

        if (!wp_verify_nonce($nonce, 'tibox_ai_frontend_save')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        update_post_meta(
            $post_id,
            self::META_ENABLED,
            isset($_POST['tibox_ai_frontend_enabled']) ? '1' : '0'
        );

        $templates = $this->templates();
        $template = isset($_POST['tibox_ai_frontend_template'])
            ? sanitize_key(wp_unslash($_POST['tibox_ai_frontend_template']))
            : 'home-ai';

        if (!isset($templates[$template])) {
            $template = 'home-ai';
        }

        update_post_meta($post_id, self::META_TEMPLATE, $template);

        $performance = isset($_POST['tibox_ai_frontend_performance'])
            ? sanitize_key(wp_unslash($_POST['tibox_ai_frontend_performance']))
            : 'aggressive';

        if (!in_array($performance, ['balanced', 'aggressive'], true)) {
            $performance = 'aggressive';
        }

        update_post_meta($post_id, self::META_PERFORMANCE, $performance);
    }

    public function template_include(string $template): string
    {
        if (!$this->is_ai_page()) {
            return $template;
        }

        $ai_template = TIBOX_AI_FRONTEND_DIR . 'templates/ai-page.php';

        return is_readable($ai_template) ? $ai_template : $template;
    }

    public function body_class(array $classes): array
    {
        if ($this->is_ai_page()) {
            $classes[] = 'tibox-ai-frontend';
            $classes[] = 'tibox-ai-template-' . sanitize_html_class($this->current_template_key());
        }

        return $classes;
    }

    public function enqueue_assets(): void
    {
        if (!$this->is_ai_page()) {
            return;
        }

        wp_enqueue_style(
            'tibox-ai-shell',
            TIBOX_AI_FRONTEND_URL . 'assets/css/ai-shell.css',
            [],
            TIBOX_AI_FRONTEND_VERSION
        );

        $template = $this->current_template_key();
        $css_file = TIBOX_AI_FRONTEND_DIR . 'pages/' . $template . '/style.css';
        $js_file = TIBOX_AI_FRONTEND_DIR . 'pages/' . $template . '/script.js';

        if (is_readable($css_file)) {
            wp_enqueue_style(
                'tibox-ai-page-' . $template,
                TIBOX_AI_FRONTEND_URL . 'pages/' . $template . '/style.css',
                ['tibox-ai-shell'],
                TIBOX_AI_FRONTEND_VERSION
            );
        }

        if (is_readable($js_file)) {
            wp_enqueue_script(
                'tibox-ai-page-' . $template,
                TIBOX_AI_FRONTEND_URL . 'pages/' . $template . '/script.js',
                [],
                TIBOX_AI_FRONTEND_VERSION,
                true
            );

            wp_localize_script(
                'tibox-ai-page-' . $template,
                'TiboxAIFrontend',
                [
                    'restEndpoint' => esc_url_raw(rest_url('constructor-hub/v1/landing-submit')),
                    'pageId' => get_queried_object_id(),
                    'pageUrl' => esc_url_raw(get_permalink()),
                    'pagePath' => sanitize_text_field((string) wp_parse_url(get_permalink(), PHP_URL_PATH)),
                    'pageTitle' => sanitize_text_field(wp_get_document_title()),
                    'privacyUrl' => esc_url_raw(home_url('/aviso-de-privacidad/')),
                    'formId' => 'tibox-ai-home',
                ]
            );
        }
    }

    public function strip_heavy_assets(): void
    {
        if (!$this->is_ai_page()) {
            return;
        }

        $performance = (string) get_post_meta(get_queried_object_id(), self::META_PERFORMANCE, true);

        $style_patterns = [
            'elementor',
            'hello-elementor',
            'eael',
            'essential-addons',
            'bdt-',
            'prime-slider',
            'element-pack',
            'swiper',
            'font-awesome',
            'wp-block-library',
            'global-styles',
            'classic-theme-styles',
        ];

        $script_patterns = [
            'elementor',
            'eael',
            'essential-addons',
            'bdt-',
            'prime-slider',
            'element-pack',
            'swiper',
            'imagesloaded',
            'masonry',
        ];

        if ($performance === 'aggressive') {
            $script_patterns = array_merge($script_patterns, [
                'jquery',
                'underscore',
                'backbone',
                'marionette',
            ]);
        }

        $this->dequeue_matching_styles($style_patterns);
        $this->dequeue_matching_scripts($script_patterns);
    }

    public function current_page_template_path(): string
    {
        $key = $this->current_template_key();
        $path = TIBOX_AI_FRONTEND_DIR . 'pages/' . $key . '/template.php';

        if (!is_readable($path)) {
            $path = TIBOX_AI_FRONTEND_DIR . 'pages/home-ai/template.php';
        }

        return $path;
    }

    private function templates(): array
    {
        return [
            'home-ai' => 'Inicio IA — MVP',
        ];
    }

    private function current_template_key(): string
    {
        $post_id = get_queried_object_id();
        $key = (string) get_post_meta($post_id, self::META_TEMPLATE, true);

        return isset($this->templates()[$key]) ? $key : 'home-ai';
    }

    private function is_ai_page(?int $post_id = null): bool
    {
        if (is_admin() || !is_page()) {
            return false;
        }

        $post_id = $post_id ?: get_queried_object_id();

        return $post_id > 0 && get_post_meta($post_id, self::META_ENABLED, true) === '1';
    }

    private function dequeue_matching_styles(array $patterns): void
    {
        global $wp_styles;

        if (!($wp_styles instanceof WP_Styles)) {
            return;
        }

        foreach ((array) $wp_styles->queue as $handle) {
            $registered = $wp_styles->registered[$handle] ?? null;
            $haystack = strtolower($handle . ' ' . ($registered->src ?? ''));

            if ($this->contains_pattern($haystack, $patterns)) {
                wp_dequeue_style($handle);
                wp_deregister_style($handle);
            }
        }
    }

    private function dequeue_matching_scripts(array $patterns): void
    {
        global $wp_scripts;

        if (!($wp_scripts instanceof WP_Scripts)) {
            return;
        }

        foreach ((array) $wp_scripts->queue as $handle) {
            if (str_starts_with($handle, 'tibox-ai-')) {
                continue;
            }

            $registered = $wp_scripts->registered[$handle] ?? null;
            $haystack = strtolower($handle . ' ' . ($registered->src ?? ''));

            if ($this->contains_pattern($haystack, $patterns)) {
                wp_dequeue_script($handle);
                wp_deregister_script($handle);
            }
        }
    }

    private function contains_pattern(string $haystack, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_contains($haystack, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }
}
