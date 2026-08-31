<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The one place where a HUB design becomes HTML on a page.
 *
 * Shortcodes, blocks, regions, templates and the Elementor widget all end up
 * here, so isolation, variables, assets and preview behave identically wherever
 * a design is inserted.
 */
final class HUB_Tibox_Render
{
    private static ?self $instance = null;

    /** @var array<int,int> Designs already rendered on this request, and how often. */
    private array $rendered = [];

    private bool $form_runtime_enqueued = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_filter('template_include', [$this, 'template_include'], 95);
        add_action('template_redirect', [$this, 'maybe_render_standalone'], -10);
        add_filter('body_class', [$this, 'body_class']);

        add_action('wp', [$this, 'prepare_request_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_base_styles'], 5);
        add_action('wp_body_open', [$this, 'inject_header_region'], 5);
        add_action('wp_footer', [$this, 'inject_footer_region'], 5);
        add_action('wp_head', [$this, 'print_region_hide_rules'], 100);
        add_action('wp_footer', ['HUB_Tibox_Preview', 'render_notice'], 999);
    }

    // ------------------------------------------------------------ public API

    /**
     * Render one design as a string.
     *
     * @param array<string,mixed> $args
     */
    public function render(int $design_id, array $args = []): string
    {
        if ($design_id <= 0 || get_post_type($design_id) !== HUB_Tibox_Design::POST_TYPE) {
            return '';
        }

        $status = get_post_status($design_id);
        if ($status !== 'publish' && !HUB_Tibox_Preview::is_previewing($design_id) && !current_user_can('edit_post', $design_id)) {
            return '';
        }

        $version = HUB_Tibox_Preview::version_for($design_id);
        if ($version === null) {
            return '';
        }

        $html = trim((string) ($version['html'] ?? ''));
        if ($html === '') {
            return '';
        }

        $scope_class = HUB_Tibox_Design::scope_class($design_id);
        $html = HUB_Tibox_Variables::replace($html, $design_id, [
            'scope' => $scope_class,
            'form_target' => (int) ($args['form_target'] ?? $design_id),
        ]);

        $this->ensure_assets($design_id, $version);
        $this->maybe_enqueue_form_runtime($html, (int) ($args['form_target'] ?? $design_id));
        $this->rendered[$design_id] = ($this->rendered[$design_id] ?? 0) + 1;

        $type = HUB_Tibox_Design::get_type($design_id);
        $classes = ['hub-design', 'hub-design--' . sanitize_html_class($type)];
        if (HUB_Tibox_Design::uses_css_scope($design_id)) {
            $classes[] = $scope_class;
        }
        if (!empty($args['class'])) {
            $classes[] = sanitize_html_class((string) $args['class']);
        }

        if (!empty($args['bare'])) {
            return $html;
        }

        return sprintf(
            '<div class="%s" data-hub-design="%d" data-hub-version="%d">%s</div>',
            esc_attr(implode(' ', array_unique($classes))),
            $design_id,
            (int) $version['version'],
            $html
        );
    }

    /** Echoing helper used by templates and the region hooks. */
    public function output(int $design_id, array $args = []): void
    {
        echo $this->render($design_id, $args); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
    }

    public function render_region(string $region): string
    {
        $design_id = HUB_Tibox_Regions::active_design($region);
        if ($design_id <= 0) {
            return '';
        }

        return $this->render($design_id, ['class' => 'hub-region hub-region--' . sanitize_html_class($region)]);
    }

    // ------------------------------------------------------------- lifecycle

    /**
     * Enqueue everything the request already knows it needs, before wp_head
     * runs. Designs discovered later (a shortcode deep in the content) fall back
     * to the deferred inline path in the asset compiler.
     */
    public function prepare_request_assets(): void
    {
        if (is_admin()) {
            return;
        }

        foreach (HUB_Tibox_Regions::names() as $region) {
            $design_id = HUB_Tibox_Regions::active_design($region);
            if ($design_id > 0) {
                $this->preload_assets($design_id);
            }
        }

        $object_id = get_queried_object_id();
        if ($object_id <= 0) {
            return;
        }

        if (get_post_type($object_id) === HUB_Tibox_Design::POST_TYPE) {
            $this->preload_assets($object_id);
            return;
        }

        // Designs inserted through the shortcode in ordinary content.
        $post = get_post($object_id);
        if (!$post instanceof WP_Post || !str_contains((string) $post->post_content, 'hub_design')) {
            return;
        }

        if (!preg_match_all('/\[hub_design[^\]]*\]/', (string) $post->post_content, $matches)) {
            return;
        }

        foreach ($matches[0] as $shortcode) {
            $attributes = shortcode_parse_atts(trim($shortcode, '[]'));
            $reference = (string) ($attributes['slug'] ?? $attributes['id'] ?? '');
            $design_id = HUB_Tibox_Design::resolve($reference, (string) ($attributes['type'] ?? ''));
            if ($design_id > 0) {
                $this->preload_assets($design_id);
            }
        }
    }

    /** Structural styles only. Visual design belongs to each design package. */
    public function enqueue_base_styles(): void
    {
        if (is_admin() || (!$this->is_hub_document() && !is_singular(HUB_Tibox_Design::POST_TYPE))) {
            return;
        }

        wp_enqueue_style(
            'constructor-hub-base',
            TIBOX_AI_FRONTEND_URL . 'assets/css/landing-base.css',
            [],
            TIBOX_AI_FRONTEND_VERSION
        );
    }

    public function template_include(string $template): string
    {
        if (!$this->is_hub_document()) {
            return $template;
        }

        $shell = TIBOX_AI_FRONTEND_DIR . 'templates/hub-shell.php';
        return is_readable($shell) ? $shell : $template;
    }

    /**
     * Full HTML documents authored by an AI bypass the theme entirely. WordPress
     * hooks are injected back into the document so SEO and analytics survive.
     */
    public function maybe_render_standalone(): void
    {
        if (is_admin() || !is_singular(HUB_Tibox_Design::POST_TYPE)) {
            return;
        }

        $design_id = get_queried_object_id();
        if (!HUB_Tibox_Design::is_viewable_type(HUB_Tibox_Design::get_type($design_id))) {
            return;
        }

        if (post_password_required($design_id)) {
            return;
        }

        if (HUB_Tibox_Design::get_render_mode($design_id) !== HUB_Tibox_Design::MODE_STANDALONE) {
            return;
        }

        $version = HUB_Tibox_Preview::version_for($design_id);
        if ($version === null) {
            return;
        }

        $html = trim((string) ($version['html'] ?? ''));
        if ($html === '') {
            return;
        }

        HUB_Tibox_Landing_Document::render($html, $design_id);
    }

    public function body_class(array $classes): array
    {
        if (is_singular(HUB_Tibox_Design::POST_TYPE)) {
            $design_id = get_queried_object_id();
            $classes[] = 'constructor-hub-tibox';
            $classes[] = 'hub-design-type-' . sanitize_html_class(HUB_Tibox_Design::get_type($design_id));
            $classes[] = 'hub-render-mode-' . sanitize_html_class(HUB_Tibox_Design::get_render_mode($design_id));
        }

        foreach (HUB_Tibox_Regions::names() as $region) {
            if (HUB_Tibox_Regions::active_design($region) > 0) {
                $classes[] = 'hub-region-' . sanitize_html_class($region) . '-active';
            }
        }

        return array_values(array_unique($classes));
    }

    // ----------------------------------------------------------- region hooks

    public function inject_header_region(): void
    {
        if (HUB_Tibox_Regions::mode('header') !== HUB_Tibox_Regions::MODE_INJECT) {
            return;
        }

        echo $this->render_region('header'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
    }

    public function inject_footer_region(): void
    {
        if (HUB_Tibox_Regions::mode('footer') !== HUB_Tibox_Regions::MODE_INJECT) {
            return;
        }

        echo $this->render_region('footer'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design HTML authored by a user holding hub_edit_design_code.
    }

    /**
     * In inject mode the theme still prints its own header or footer. Hiding it
     * needs a selector, which is theme specific and therefore configured, never
     * guessed.
     */
    public function print_region_hide_rules(): void
    {
        $selectors = [];

        foreach (HUB_Tibox_Regions::names() as $region) {
            if (HUB_Tibox_Regions::mode($region) !== HUB_Tibox_Regions::MODE_INJECT) {
                continue;
            }

            $selector = trim(HUB_Tibox_Regions::config($region)['hide_selector']);
            if ($selector !== '') {
                $selectors[] = $selector;
            }
        }

        if ($selectors === []) {
            return;
        }

        printf(
            "\n<style id=\"constructor-hub-region-hide\">%s{display:none !important;}</style>\n",
            esc_html(implode(',', $selectors))
        );
    }

    // ---------------------------------------------------------------- helpers

    public function is_hub_document(): bool
    {
        if (is_admin()) {
            return false;
        }

        if (HUB_Tibox_Regions::owns_document()) {
            return true;
        }

        if (!is_singular(HUB_Tibox_Design::POST_TYPE)) {
            return false;
        }

        $design_id = get_queried_object_id();
        if (!HUB_Tibox_Design::is_viewable_type(HUB_Tibox_Design::get_type($design_id))) {
            return false;
        }

        return HUB_Tibox_Design::get_render_mode($design_id) === HUB_Tibox_Design::MODE_HUB;
    }

    /**
     * How many times a design was rendered on this request.
     *
     * A design inserted twice on the same page duplicates element ids, which is
     * the usual cause of a component that works in preview and breaks on the
     * page. The migration map reports it.
     */
    public function render_count(int $design_id): int
    {
        return (int) ($this->rendered[$design_id] ?? 0);
    }

    /** @return array<int,int> */
    public function rendered_designs(): array
    {
        return $this->rendered;
    }

    public function current_design_id(): int
    {
        return is_singular(HUB_Tibox_Design::POST_TYPE) ? get_queried_object_id() : 0;
    }

    /**
     * The form runtime only loads on pages that actually contain a HUB form,
     * whether it came from {{HUB_FORM}} or from markup an AI wrote by hand.
     */
    private function maybe_enqueue_form_runtime(string $html, int $host_id): void
    {
        if ($this->form_runtime_enqueued || !str_contains($html, 'data-hub-landing-form')) {
            return;
        }

        $this->form_runtime_enqueued = true;

        wp_enqueue_script(
            'constructor-hub-landing-form',
            TIBOX_AI_FRONTEND_URL . 'assets/js/landing-form.js',
            [],
            TIBOX_AI_FRONTEND_VERSION,
            true
        );

        wp_localize_script('constructor-hub-landing-form', 'HubLandingFormConfig', [
            'endpoint' => esc_url_raw(HUB_Tibox_Landing_Forms::instance()->endpoint_url()),
            'landingId' => $host_id,
            'landingUrl' => esc_url_raw((string) get_permalink($host_id)),
            'pageTitle' => sanitize_text_field(wp_get_document_title()),
            'successMessage' => HUB_Tibox_Form_Config::success_message($host_id),
            'formToken' => HUB_Tibox_Antispam::issue_token($host_id),
            'eventName' => 'form_submit',
        ]);
    }

    private function preload_assets(int $design_id): void
    {
        $version = HUB_Tibox_Preview::version_for($design_id);
        if ($version !== null) {
            $this->ensure_assets($design_id, $version);
        }
    }

    /**
     * @param array<string,mixed> $version
     */
    private function ensure_assets(int $design_id, array $version): void
    {
        // A previewed version is never compiled to disk: it would overwrite the
        // published files for every visitor.
        if (HUB_Tibox_Preview::is_previewing($design_id)) {
            HUB_Tibox_Asset_Compiler::instance()->defer_inline(
                $design_id,
                HUB_Tibox_Asset_Compiler::instance()->prepare_css($design_id, (string) ($version['css'] ?? '')),
                trim((string) ($version['js'] ?? ''))
            );
            return;
        }

        if (HUB_Tibox_Asset_Compiler::instance()->enqueue($design_id)) {
            return;
        }

        HUB_Tibox_Asset_Compiler::instance()->defer_inline(
            $design_id,
            HUB_Tibox_Asset_Compiler::instance()->prepare_css($design_id, (string) ($version['css'] ?? '')),
            trim((string) ($version['js'] ?? ''))
        );
    }
}
