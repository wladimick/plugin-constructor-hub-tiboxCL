<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The single design object of Constructor HUB.
 *
 * Header/Footer components and Landings used to be two post types with two
 * storage schemes, two variable resolvers and two renderers. They are the same
 * thing with a different render scope: a package of HTML, CSS, JS and assets.
 * One type keeps the contract for AI generated designs consistent and means a
 * new kind of component costs a row in `types()`, not another module.
 */
final class HUB_Tibox_Design
{
    public const POST_TYPE = 'hub_design';

    public const META_TYPE = '_hub_type';
    public const META_CURRENT_VERSION = '_hub_current_version';
    public const META_RENDER_MODE = '_hub_render_mode';
    public const META_USE_CHROME = '_hub_use_chrome';
    public const META_CSS_SCOPE = '_hub_css_scope';
    public const META_LEGACY_ID = '_hub_legacy_source_id';
    public const META_LEGACY_TYPE = '_hub_legacy_source_type';

    public const META_RECIPIENTS = '_hub_form_recipients';
    public const META_CONFIRMATION = '_hub_form_confirmation';
    public const META_SUCCESS_MESSAGE = '_hub_form_success_message';
    public const META_REQUIRED_FIELDS = '_hub_form_required_fields';

    public const META_ADS_ACTIVE = '_hub_ads_active';
    public const META_ADS_CAMPAIGN_NAME = '_hub_ads_campaign_name';
    public const META_ADS_CAMPAIGN_ID = '_hub_ads_campaign_id';
    public const META_ADS_START_DATE = '_hub_ads_start_date';
    public const META_ADS_END_DATE = '_hub_ads_end_date';
    public const META_ADS_FINAL_URL = '_hub_ads_final_url';
    public const META_ADS_NOTES = '_hub_ads_notes';

    public const MODE_HUB = 'hub';
    public const MODE_STANDALONE = 'standalone';
    public const MODE_PACKAGE = 'package';
    public const MODE_LEGACY = 'legacy';

    private const OPTION_REWRITE_VERSION = 'hub_tibox_design_rewrite_version';
    private const REWRITE_VERSION = '1';

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
        add_action('init', [$this, 'register_post_type'], 5);
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99);
        add_action('template_redirect', [$this, 'block_non_viewable_types'], -50);
        add_filter('wp_sitemaps_post_types', [$this, 'filter_sitemap_post_types']);
        add_action('before_delete_post', [$this, 'cleanup_design'], 10, 2);
        add_filter('wp_robots', [$this, 'filter_robots']);
    }

    /**
     * Every design kind the HUB knows about.
     *
     * `viewable` decides whether the object owns a public URL. A Header has no
     * page of its own; a Landing does.
     *
     * @return array<string,array{label:string,plural:string,group:string,viewable:bool,region:bool}>
     */
    public static function types(): array
    {
        return (array) apply_filters('constructor_hub_design_types', [
            'header' => ['label' => 'Header', 'plural' => 'Headers', 'group' => 'chrome', 'viewable' => false, 'region' => true],
            'footer' => ['label' => 'Footer', 'plural' => 'Footers', 'group' => 'chrome', 'viewable' => false, 'region' => true],
            'menu' => ['label' => 'Mega Menu', 'plural' => 'Mega Menus', 'group' => 'chrome', 'viewable' => false, 'region' => false],
            'hero' => ['label' => 'Hero', 'plural' => 'Heroes', 'group' => 'bloques', 'viewable' => false, 'region' => false],
            'section' => ['label' => 'Sección', 'plural' => 'Secciones', 'group' => 'bloques', 'viewable' => false, 'region' => false],
            'form' => ['label' => 'Formulario', 'plural' => 'Formularios', 'group' => 'bloques', 'viewable' => false, 'region' => false],
            'landing' => ['label' => 'Landing', 'plural' => 'Landings', 'group' => 'paginas', 'viewable' => true, 'region' => false],
            'page' => ['label' => 'Página', 'plural' => 'Páginas', 'group' => 'paginas', 'viewable' => true, 'region' => false],
        ]);
    }

    /** @return string[] */
    public static function type_keys(): array
    {
        return array_keys(self::types());
    }

    public static function is_valid_type(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public static function type_label(string $type): string
    {
        return (string) (self::types()[$type]['label'] ?? $type);
    }

    public static function is_viewable_type(string $type): bool
    {
        return (bool) (self::types()[$type]['viewable'] ?? false);
    }

    public function register_post_type(): void
    {
        $slug = (string) apply_filters('constructor_hub_design_rewrite_slug', 'landing');
        $slug = trim(sanitize_title($slug), '/');
        if ($slug === '') {
            $slug = 'landing';
        }

        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Diseños HUB',
                'singular_name' => 'Diseño HUB',
                'add_new' => 'Nuevo diseño',
                'add_new_item' => 'Crear diseño HUB',
                'edit_item' => 'Editar diseño HUB',
                'new_item' => 'Nuevo diseño HUB',
                'view_item' => 'Ver diseño HUB',
                'search_items' => 'Buscar diseños HUB',
                'not_found' => 'No hay diseños HUB.',
                'all_items' => 'Todos los diseños',
                'menu_name' => 'Constructor HUB',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => $slug, 'with_front' => false],
            'supports' => ['title', 'thumbnail', 'revisions', 'page-attributes', 'author'],
            'capability_type' => ['hub_design', 'hub_designs'],
            'map_meta_cap' => true,
        ]);
    }

    public function maybe_flush_rewrite_rules(): void
    {
        if ((string) get_option(self::OPTION_REWRITE_VERSION, '') === self::REWRITE_VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE_VERSION, self::REWRITE_VERSION, false);
    }

    /**
     * A Header has no page of its own. Without this, `/landing/header-tibox/`
     * would serve a fragment as a document and get indexed.
     */
    public function block_non_viewable_types(): void
    {
        if (is_admin() || !is_singular(self::POST_TYPE)) {
            return;
        }

        $design_id = get_queried_object_id();
        if (self::is_viewable_type(self::get_type($design_id))) {
            return;
        }

        if (HUB_Tibox_Preview::is_previewing($design_id)) {
            return;
        }

        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }

    /** @param string[] $post_types */
    public function filter_sitemap_post_types(array $post_types): array
    {
        unset($post_types[self::POST_TYPE]);
        return $post_types;
    }

    /** @param array<string,mixed> $robots */
    public function filter_robots(array $robots): array
    {
        if (!is_singular(self::POST_TYPE)) {
            return $robots;
        }

        $design_id = get_queried_object_id();
        if (HUB_Tibox_Preview::is_previewing($design_id) || !self::is_viewable_type(self::get_type($design_id))) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public function cleanup_design(int $post_id, $post = null): void
    {
        if (get_post_type($post_id) !== self::POST_TYPE) {
            return;
        }

        HUB_Tibox_Version_Store::instance()->delete_for_design($post_id);
        HUB_Tibox_Asset_Compiler::instance()->delete_design_assets($post_id);

        /** Packages keep their extracted files next to the compiled assets. */
        do_action('constructor_hub_design_deleted', $post_id);
    }

    // ---------------------------------------------------------------- reading

    public static function get_type(int $design_id): string
    {
        $type = (string) get_post_meta($design_id, self::META_TYPE, true);
        return self::is_valid_type($type) ? $type : 'section';
    }

    public static function get_render_mode(int $design_id): string
    {
        $mode = (string) get_post_meta($design_id, self::META_RENDER_MODE, true);
        $allowed = [self::MODE_HUB, self::MODE_STANDALONE, self::MODE_PACKAGE, self::MODE_LEGACY];

        return in_array($mode, $allowed, true) ? $mode : self::MODE_HUB;
    }

    public static function uses_chrome(int $design_id): bool
    {
        return get_post_meta($design_id, self::META_USE_CHROME, true) === '1';
    }

    public static function uses_css_scope(int $design_id): bool
    {
        return get_post_meta($design_id, self::META_CSS_SCOPE, true) === '1';
    }

    /** Stable CSS class used to isolate a design from the theme. */
    public static function scope_class(int $design_id): string
    {
        $post = get_post($design_id);
        $slug = $post instanceof WP_Post && $post->post_name !== '' ? $post->post_name : (string) $design_id;

        return sanitize_html_class('hub-scope-' . $slug . '-' . $design_id);
    }

    public static function get_recipient_emails(int $design_id): string
    {
        return (string) get_post_meta($design_id, self::META_RECIPIENTS, true);
    }

    public static function get_confirmation_override(int $design_id): ?bool
    {
        $value = (string) get_post_meta($design_id, self::META_CONFIRMATION, true);
        if ($value === 'yes') {
            return true;
        }
        if ($value === 'no') {
            return false;
        }

        return null;
    }

    /** @return string[] */
    public static function get_required_fields(int $design_id): array
    {
        $value = get_post_meta($design_id, self::META_REQUIRED_FIELDS, true);
        return is_array($value) ? array_values(array_map('sanitize_key', $value)) : [];
    }

    public static function get_success_message(int $design_id): string
    {
        $message = trim((string) get_post_meta($design_id, self::META_SUCCESS_MESSAGE, true));

        return $message !== ''
            ? $message
            : 'Gracias. Recibimos tus datos y te contactaremos pronto.';
    }

    public static function has_active_campaign(int $design_id): bool
    {
        return get_post_meta($design_id, self::META_ADS_ACTIVE, true) === '1';
    }

    /** @return string[] */
    public static function allowed_required_fields(): array
    {
        return ['name', 'phone', 'company', 'rut', 'area', 'users', 'message'];
    }

    /**
     * Find a design by slug or id, restricted to published ones.
     */
    public static function resolve(string $reference, string $type = ''): int
    {
        $reference = trim($reference);
        if ($reference === '') {
            return 0;
        }

        if (ctype_digit($reference)) {
            $id = (int) $reference;
            return get_post_type($id) === self::POST_TYPE ? $id : 0;
        }

        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'name' => sanitize_title($reference),
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ];

        if ($type !== '' && self::is_valid_type($type)) {
            $args['meta_key'] = self::META_TYPE;
            $args['meta_value'] = $type;
        }

        $found = get_posts($args);
        return $found === [] ? 0 : (int) $found[0];
    }

    /**
     * @return WP_Post[]
     */
    public static function list_by_type(string $type, string $status = 'publish'): array
    {
        if (!self::is_valid_type($type)) {
            return [];
        }

        return get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => $status === 'any' ? 'any' : $status,
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_key' => self::META_TYPE,
            'meta_value' => $type,
            'no_found_rows' => true,
        ]);
    }
}
