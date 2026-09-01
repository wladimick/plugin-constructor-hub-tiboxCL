<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Writes the CSS and JS of a published version to disk and enqueues them.
 *
 * Printing every stylesheet inline on each request meant no browser cache, no
 * Content-Security-Policy without `unsafe-inline`, and no way to tell which
 * version a visitor actually received. Files are written once per published
 * version and served by the web server. When the filesystem is not writable the
 * renderer falls back to inline output, so a read-only install still works.
 */
final class HUB_Tibox_Asset_Compiler
{
    private const SUBDIR = 'constructor-hub/designs';

    private static ?self $instance = null;

    /** @var array<int,bool> */
    private array $enqueued = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('constructor_hub_design_published', [$this, 'compile_published'], 10, 3);
        add_action('wp_footer', [$this, 'print_deferred_inline'], 99);
    }

    /**
     * @param array<string,mixed> $version
     */
    public function compile_published(int $design_id, int $version_id, array $version): void
    {
        $this->compile($design_id, $version_id);
    }

    /**
     * @return array{css:string,js:string,css_path:string,js_path:string}
     */
    public function compile(int $design_id, int $version_id): array
    {
        $empty = ['css' => '', 'js' => '', 'css_path' => '', 'js_path' => ''];

        $version = HUB_Tibox_Version_Store::instance()->get($version_id);
        if ($version === null || (int) $version['design_id'] !== $design_id) {
            return $empty;
        }

        $css = $this->prepare_css($design_id, (string) ($version['css'] ?? ''));
        $js = trim((string) ($version['js'] ?? ''));

        $dir = $this->version_dir($design_id, $version_id);
        if (!wp_mkdir_p($dir)) {
            return $empty;
        }

        $result = $empty;

        if ($css !== '') {
            $path = trailingslashit($dir) . 'style.css';
            if (file_put_contents($path, $css) !== false) {
                $result['css'] = $this->version_url($design_id, $version_id) . 'style.css';
                $result['css_path'] = $path;
            }
        }

        if ($js !== '') {
            $path = trailingslashit($dir) . 'script.js';
            if (file_put_contents($path, $js) !== false) {
                $result['js'] = $this->version_url($design_id, $version_id) . 'script.js';
                $result['js_path'] = $path;
            }
        }

        return $result;
    }

    /**
     * Enqueue the compiled assets of a design, compiling on demand the first
     * time. Returns false when the design has to fall back to inline output.
     */
    public function enqueue(int $design_id): bool
    {
        if (isset($this->enqueued[$design_id])) {
            return $this->enqueued[$design_id];
        }

        $version = HUB_Tibox_Version_Store::instance()->get_live($design_id);
        if ($version === null) {
            $this->enqueued[$design_id] = false;
            return false;
        }

        $version_id = (int) $version['id'];
        $css_path = trailingslashit($this->version_dir($design_id, $version_id)) . 'style.css';
        $js_path = trailingslashit($this->version_dir($design_id, $version_id)) . 'script.js';

        $has_css = trim((string) ($version['css'] ?? '')) !== '';
        $has_js = trim((string) ($version['js'] ?? '')) !== '';

        if (($has_css && !is_readable($css_path)) || ($has_js && !is_readable($js_path))) {
            $compiled = $this->compile($design_id, $version_id);
            if (($has_css && $compiled['css'] === '') || ($has_js && $compiled['js'] === '')) {
                $this->enqueued[$design_id] = false;
                return false;
            }
        }

        $base = $this->version_url($design_id, $version_id);
        $handle = 'hub-design-' . $design_id;
        $ver = (string) $version['version'];

        if ($has_css) {
            wp_enqueue_style($handle, $base . 'style.css', [], $ver);
        }

        if ($has_js) {
            wp_enqueue_script($handle, $base . 'script.js', [], $ver, true);
        }

        $this->enqueued[$design_id] = true;
        return true;
    }

    /**
     * Designs that could not be compiled still have to reach the page. They are
     * collected and printed once, late, instead of once per insertion.
     *
     * @var array<int,array{css:string,js:string}>
     */
    private array $deferred = [];

    public function defer_inline(int $design_id, string $css, string $js): void
    {
        if ($css === '' && $js === '') {
            return;
        }

        $this->deferred[$design_id] = ['css' => $css, 'js' => $js];
    }

    public function print_deferred_inline(): void
    {
        if ($this->deferred === []) {
            return;
        }

        $css = '';
        $js = '';
        foreach ($this->deferred as $chunk) {
            $css .= $chunk['css'] === '' ? '' : $chunk['css'] . "\n";
            $js .= $chunk['js'] === '' ? '' : $chunk['js'] . "\n";
        }
        $this->deferred = [];

        if (trim($css) !== '') {
            echo "\n<style id=\"constructor-hub-inline-css\">\n";
            echo $css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design CSS authored by a user holding hub_edit_design_code.
            echo "\n</style>\n";
        }

        if (trim($js) !== '') {
            echo "\n<script id=\"constructor-hub-inline-js\">\n";
            echo $js; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- design JS authored by a user holding hub_edit_design_code.
            echo "\n</script>\n";
        }
    }

    public function prepare_css(int $design_id, string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }

        if (!HUB_Tibox_Design::uses_css_scope($design_id)) {
            return $css;
        }

        return HUB_Tibox_Css_Scoper::scope($css, '.' . HUB_Tibox_Design::scope_class($design_id));
    }

    public function version_dir(int $design_id, int $version_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . self::SUBDIR . '/' . $design_id . '/' . $version_id;
    }

    public function version_url(int $design_id, int $version_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit(trailingslashit($upload['baseurl']) . self::SUBDIR . '/' . $design_id . '/' . $version_id);
    }

    public function design_dir(int $design_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . self::SUBDIR . '/' . $design_id;
    }

    /** Removes every compiled asset of a design. */
    public function delete_design_assets(int $design_id): void
    {
        HUB_Tibox_Filesystem::delete_directory($this->design_dir($design_id));
    }
}
