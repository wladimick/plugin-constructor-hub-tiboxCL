<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Assigns a HUB page design to an existing WordPress Page without changing its
 * post ID, permalink, SEO object or backend content.
 *
 * The assignment is opt-in and fail-safe: Theme/Elementor remains the default,
 * and HUB only owns the request when the selected design has a usable live
 * version. A signed HUB preview may temporarily take over the original URL
 * without changing the saved render mode.
 */
final class HUB_Tibox_Page_Assignment
{
    public const META_DESIGN = '_hub_assigned_design_id';
    public const META_MODE = '_hub_page_render_mode';

    public const MODE_THEME = 'theme';
    public const MODE_HUB = 'hub';

    private const NONCE_ACTION = 'hub_tibox_save_page_assignment';
    private const NONCE_FIELD = 'hub_tibox_page_assignment_nonce';

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
        add_action('save_post_page', [$this, 'save'], 20, 2);

        add_filter('template_include', [$this, 'template_include'], 94);
        add_action('wp', [$this, 'prepare_assets'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_base_styles'], 4);
        add_filter('body_class', [$this, 'body_class']);
        add_filter('wp_robots', [$this, 'filter_robots']);
    }

    /**
     * Pure activation rule kept separate so the risky routing decision is unit
     * testable without a WordPress database.
     */
    public static function should_take_over(
        string $mode,
        bool $has_assignment,
        bool $has_live_version,
        bool $matching_preview
    ): bool {
        if (!$has_assignment) {
            return false;
        }

        if ($matching_preview) {
            return true;
        }

        return $mode === self::MODE_HUB && $has_live_version;
    }

    public function add_meta_box(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            return;
        }

        add_meta_box(
            'hub-page-assignment',
            'Constructor HUB',
            [$this, 'render_meta_box'],
            'page',
            'side',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $assigned = $this->assigned_design_id($post->ID);
        $mode = self::mode($post->ID);
        $designs = HUB_Tibox_Design::list_by_type('page', 'any');
        $can_activate = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        ?>
        <p>
            <label for="hub-assigned-design"><strong>Diseño HUB</strong></label>
            <select id="hub-assigned-design" name="hub_assigned_design_id" style="width:100%;margin-top:6px;">
                <option value="0">— Sin asignar —</option>
                <?php foreach ($designs as $design) : ?>
                    <option value="<?php echo esc_attr((string) $design->ID); ?>" <?php selected($assigned, $design->ID); ?>>
                        <?php
                        echo esc_html(sprintf(
                            '%s%s',
                            $design->post_title !== '' ? $design->post_title : ('#' . $design->ID),
                            $design->post_status === 'publish' ? '' : ' (' . $design->post_status . ')'
                        ));
                        ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="hub-page-render-mode"><strong>Quién renderiza esta URL</strong></label>
            <select id="hub-page-render-mode" name="hub_page_render_mode" style="width:100%;margin-top:6px;" <?php disabled(!$can_activate); ?>>
                <option value="<?php echo esc_attr(self::MODE_THEME); ?>" <?php selected($mode, self::MODE_THEME); ?>>
                    Theme / Elementor
                </option>
                <option value="<?php echo esc_attr(self::MODE_HUB); ?>" <?php selected($mode, self::MODE_HUB); ?>>
                    Constructor HUB
                </option>
            </select>
            <?php if (!$can_activate) : ?>
                <input type="hidden" name="hub_page_render_mode" value="<?php echo esc_attr($mode); ?>">
                <span class="description">Tu rol puede preparar la asignación, pero no activar HUB en una URL pública.</span>
            <?php endif; ?>
        </p>

        <p class="description">
            La URL, el ID de esta página, Rank Math y sus campos de WordPress no cambian. Solo cambia la capa que entrega el frontend.
        </p>
        <?php

        if ($assigned <= 0) {
            return;
        }

        $live = HUB_Tibox_Version_Store::instance()->get_live($assigned);
        if (get_post_status($assigned) !== 'publish' || $live === null) {
            echo '<p style="color:#b32d2e;"><strong>Sin versión publicada.</strong> Aunque selecciones HUB, esta URL seguirá usando Theme/Elementor hasta que el diseño tenga una versión live.</p>';
        } else {
            echo '<p style="color:#00713c;"><strong>Listo para activar.</strong> Hay una versión live utilizable.</p>';
        }

        $working = HUB_Tibox_Version_Store::instance()->get_working($assigned);
        if ($working !== null) {
            $preview_url = $this->preview_url($post->ID, (int) $working['id']);
            ?>
            <p>
                <a class="button button-secondary" href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener">
                    Previsualizar en esta URL
                </a>
            </p>
            <?php
        }
    }

    public function save(int $post_id, WP_Post $post): void
    {
        if (
            !isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD])),
                self::NONCE_ACTION
            )
        ) {
            return;
        }

        if (
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post_id)
            || $post->post_type !== 'page'
            || !current_user_can('edit_post', $post_id)
            || !HUB_Tibox_Capabilities::can_manage_designs()
        ) {
            return;
        }

        $design_id = isset($_POST['hub_assigned_design_id'])
            ? absint($_POST['hub_assigned_design_id'])
            : 0;

        if ($design_id > 0 && !$this->is_page_design($design_id)) {
            $design_id = 0;
        }

        $mode = isset($_POST['hub_page_render_mode'])
            ? sanitize_key(wp_unslash((string) $_POST['hub_page_render_mode']))
            : self::MODE_THEME;

        if (!in_array($mode, [self::MODE_THEME, self::MODE_HUB], true)) {
            $mode = self::MODE_THEME;
        }

        $can_activate = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        if ($mode === self::MODE_HUB && !$can_activate) {
            $mode = self::MODE_THEME;
        }

        if ($design_id <= 0) {
            delete_post_meta($post_id, self::META_DESIGN);
            update_post_meta($post_id, self::META_MODE, self::MODE_THEME);
            return;
        }

        update_post_meta($post_id, self::META_DESIGN, $design_id);
        update_post_meta($post_id, self::META_MODE, $mode);
    }

    public static function mode(int $page_id): string
    {
        $mode = (string) get_post_meta($page_id, self::META_MODE, true);

        return $mode === self::MODE_HUB ? self::MODE_HUB : self::MODE_THEME;
    }

    public function assigned_design_id(int $page_id): int
    {
        $design_id = (int) get_post_meta($page_id, self::META_DESIGN, true);

        return $this->is_page_design($design_id) ? $design_id : 0;
    }

    /**
     * Whether this saved page configuration is currently capable of replacing
     * Elementor/Theme in production. Preview is intentionally not counted here.
     */
    public function owns_page(int $page_id): bool
    {
        if ($page_id <= 0 || get_post_type($page_id) !== 'page' || self::mode($page_id) !== self::MODE_HUB) {
            return false;
        }

        $design_id = $this->assigned_design_id($page_id);
        if ($design_id <= 0 || get_post_status($design_id) !== 'publish') {
            return false;
        }

        return HUB_Tibox_Version_Store::instance()->get_live($design_id) !== null;
    }

    public function owns_current_request(): bool
    {
        return $this->active_design_id() > 0;
    }

    /**
     * Design that should render on the current Page request.
     *
     * A signed preview matching the assigned design is allowed even when the
     * saved page mode remains Theme/Elementor, which provides a safe review path
     * before the cutover.
     */
    public function active_design_id(int $page_id = 0): int
    {
        if ($page_id <= 0) {
            if (is_admin() || !is_singular('page')) {
                return 0;
            }
            $page_id = get_queried_object_id();
        }

        if ($page_id <= 0 || get_post_type($page_id) !== 'page') {
            return 0;
        }

        $design_id = $this->assigned_design_id($page_id);
        $has_assignment = $design_id > 0;
        $matching_preview = $has_assignment && HUB_Tibox_Preview::is_previewing($design_id);
        $has_live = $has_assignment
            && get_post_status($design_id) === 'publish'
            && HUB_Tibox_Version_Store::instance()->get_live($design_id) !== null;

        return self::should_take_over(
            self::mode($page_id),
            $has_assignment,
            $has_live,
            $matching_preview
        ) ? $design_id : 0;
    }

    public function template_include(string $template): string
    {
        if ($this->active_design_id() <= 0) {
            return $template;
        }

        $assigned = TIBOX_AI_FRONTEND_DIR . 'templates/assigned-page.php';

        return is_readable($assigned) ? $assigned : $template;
    }

    public function prepare_assets(): void
    {
        $design_id = $this->active_design_id();
        if ($design_id <= 0 || HUB_Tibox_Preview::is_previewing($design_id)) {
            return;
        }

        HUB_Tibox_Asset_Compiler::instance()->enqueue($design_id);
    }

    public function enqueue_base_styles(): void
    {
        if ($this->active_design_id() <= 0) {
            return;
        }

        wp_enqueue_style(
            'constructor-hub-base',
            TIBOX_AI_FRONTEND_URL . 'assets/css/landing-base.css',
            [],
            TIBOX_AI_FRONTEND_VERSION
        );
    }

    /** @param string[] $classes */
    public function body_class(array $classes): array
    {
        if ($this->active_design_id() <= 0) {
            return $classes;
        }

        $classes[] = 'constructor-hub-tibox';
        $classes[] = 'hub-assigned-page';
        $classes[] = 'hub-design-type-page';
        $classes[] = 'hub-render-mode-assigned';

        return array_values(array_unique($classes));
    }

    /** @param array<string,mixed> $robots */
    public function filter_robots(array $robots): array
    {
        $design_id = $this->active_design_id();
        if ($design_id > 0 && HUB_Tibox_Preview::is_previewing($design_id)) {
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public function preview_url(int $page_id, int $version_id, int $ttl = DAY_IN_SECONDS): string
    {
        $expires = time() + max(60, $ttl);

        return add_query_arg([
            HUB_Tibox_Preview::QUERY_VERSION => $version_id,
            HUB_Tibox_Preview::QUERY_EXPIRES => $expires,
            HUB_Tibox_Preview::QUERY_TOKEN => HUB_Tibox_Preview::token($version_id, $expires),
        ], (string) get_permalink($page_id));
    }

    private function is_page_design(int $design_id): bool
    {
        return $design_id > 0
            && get_post_type($design_id) === HUB_Tibox_Design::POST_TYPE
            && HUB_Tibox_Design::get_type($design_id) === 'page';
    }
}
