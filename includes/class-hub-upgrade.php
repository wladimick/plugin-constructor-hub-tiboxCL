<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Install and upgrade routine.
 *
 * Migrates `hub_component` and `hub_landing` into the unified `hub_design` type
 * and turns the code stored in post meta into the first row of the version
 * history. It runs once, is idempotent, and never deletes the source: the old
 * posts stay in the database so a rollback is a matter of flipping one option.
 */
final class HUB_Tibox_Upgrade
{
    public const OPTION_VERSION = 'hub_tibox_plugin_version';
    public const OPTION_UNIFIED = 'hub_tibox_designs_unified';
    public const OPTION_REDIRECT_MAP = 'hub_tibox_legacy_redirects';

    private const LEGACY_COMPONENT = 'hub_component';
    private const LEGACY_LANDING = 'hub_landing';

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
        add_action('plugins_loaded', [$this, 'maybe_upgrade'], 5);
        add_action('template_redirect', [$this, 'redirect_legacy_urls'], -100);
    }

    public static function is_unified(): bool
    {
        return get_option(self::OPTION_UNIFIED, '0') === '1';
    }

    public function maybe_upgrade(): void
    {
        $installed = (string) get_option(self::OPTION_VERSION, '');
        if ($installed === TIBOX_AI_FRONTEND_VERSION) {
            return;
        }

        $this->install();
        update_option(self::OPTION_VERSION, TIBOX_AI_FRONTEND_VERSION, true);
    }

    public function install(): void
    {
        HUB_Tibox_Capabilities::grant();
        HUB_Tibox_Version_Store::instance()->maybe_install_table();
        HUB_Tibox_Landing_Lead_Store::instance()->maybe_install_table();
        HUB_Tibox_Mail_Log::instance()->maybe_install_table();

        if (!self::is_unified()) {
            $result = $this->migrate_legacy_designs();
            update_option(self::OPTION_UNIFIED, '1', true);
            update_option('hub_tibox_designs_unification_result', $result, false);
        }

        // Post types change between versions; the rules must be rebuilt once.
        delete_option('hub_tibox_design_rewrite_version');
    }

    /**
     * @return array{components:int,landings:int,skipped:int}
     */
    public function migrate_legacy_designs(): array
    {
        $components = 0;
        $landings = 0;
        $skipped = 0;
        $redirects = (array) get_option(self::OPTION_REDIRECT_MAP, []);

        foreach ($this->legacy_ids(self::LEGACY_COMPONENT) as $legacy_id) {
            $design_id = $this->migrate_component($legacy_id);
            if ($design_id > 0) {
                $components++;
                continue;
            }
            $skipped++;
        }

        foreach ($this->legacy_ids(self::LEGACY_LANDING) as $legacy_id) {
            $design_id = $this->migrate_landing($legacy_id);
            if ($design_id > 0) {
                $landings++;
                $redirects[(int) $legacy_id] = $design_id;
                continue;
            }
            $skipped++;
        }

        update_option(self::OPTION_REDIRECT_MAP, $redirects, false);
        $this->migrate_region_settings();

        return ['components' => $components, 'landings' => $landings, 'skipped' => $skipped];
    }

    private function migrate_component(int $legacy_id): int
    {
        $existing = $this->find_migrated($legacy_id, self::LEGACY_COMPONENT);
        if ($existing > 0) {
            return $existing;
        }

        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            return 0;
        }

        $type = (string) get_post_meta($legacy_id, '_hub_component_type', true);
        if (!in_array($type, ['header', 'footer'], true)) {
            $type = 'header';
        }

        $design_id = $this->insert_design($legacy, $type);
        if ($design_id <= 0) {
            return 0;
        }

        update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_ID, $legacy_id);
        update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_TYPE, self::LEGACY_COMPONENT);
        update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, HUB_Tibox_Design::MODE_HUB);
        // Existing components were authored without isolation and may rely on
        // styling the theme's markup. Turning scoping on silently would change
        // how they render.
        update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '0');

        $this->seed_version($design_id, [
            'html' => (string) get_post_meta($legacy_id, '_hub_component_html', true),
            'css' => (string) get_post_meta($legacy_id, '_hub_component_css', true),
            'js' => (string) get_post_meta($legacy_id, '_hub_component_js', true),
            'label' => 'Migrada desde hub_component #' . $legacy_id,
        ]);

        return $design_id;
    }

    private function migrate_landing(int $legacy_id): int
    {
        $existing = $this->find_migrated($legacy_id, self::LEGACY_LANDING);
        if ($existing > 0) {
            return $existing;
        }

        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            return 0;
        }

        $design_id = $this->insert_design($legacy, 'landing');
        if ($design_id <= 0) {
            return 0;
        }

        $mode = (string) get_post_meta($legacy_id, '_hub_landing_mode', true);
        $modes = [
            'legacy' => HUB_Tibox_Design::MODE_LEGACY,
            'hub' => HUB_Tibox_Design::MODE_HUB,
            'standalone' => HUB_Tibox_Design::MODE_STANDALONE,
            'package' => HUB_Tibox_Design::MODE_PACKAGE,
        ];
        $mode = $modes[$mode] ?? HUB_Tibox_Design::MODE_HUB;

        update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_ID, $legacy_id);
        update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_TYPE, self::LEGACY_LANDING);
        update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, $mode);
        update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '0');
        update_post_meta(
            $design_id,
            HUB_Tibox_Design::META_USE_CHROME,
            get_post_meta($legacy_id, '_hub_landing_use_hub_chrome', true) === '1' ? '1' : '0'
        );

        $this->copy_meta($legacy_id, $design_id, [
            '_hub_landing_recipient_emails' => HUB_Tibox_Design::META_RECIPIENTS,
            '_hub_landing_confirmation' => HUB_Tibox_Design::META_CONFIRMATION,
            '_hub_landing_success_message' => HUB_Tibox_Design::META_SUCCESS_MESSAGE,
            '_hub_landing_required_fields' => HUB_Tibox_Design::META_REQUIRED_FIELDS,
            '_hub_landing_ads_active' => HUB_Tibox_Design::META_ADS_ACTIVE,
            '_hub_landing_ads_campaign_name' => HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME,
            '_hub_landing_ads_campaign_id' => HUB_Tibox_Design::META_ADS_CAMPAIGN_ID,
            '_hub_landing_ads_start_date' => HUB_Tibox_Design::META_ADS_START_DATE,
            '_hub_landing_ads_end_date' => HUB_Tibox_Design::META_ADS_END_DATE,
            '_hub_landing_ads_final_url' => HUB_Tibox_Design::META_ADS_FINAL_URL,
            '_hub_landing_ads_notes' => HUB_Tibox_Design::META_ADS_NOTES,
            '_hub_legacy_landing_id' => '_hub_legacy_landing_id',
            '_hub_landing_zip_folder' => '_hub_landing_zip_folder',
            '_hub_landing_zip_entry' => '_hub_landing_zip_entry',
            '_hub_landing_zip_original_name' => '_hub_landing_zip_original_name',
        ]);

        $this->move_package_directory($legacy_id, $design_id);

        $html = $mode === HUB_Tibox_Design::MODE_STANDALONE
            ? (string) get_post_meta($legacy_id, '_hub_landing_full_html', true)
            : (string) get_post_meta($legacy_id, '_hub_landing_html', true);

        $this->seed_version($design_id, [
            'html' => $html,
            'css' => (string) get_post_meta($legacy_id, '_hub_landing_css', true),
            'js' => (string) get_post_meta($legacy_id, '_hub_landing_js', true),
            'entry' => (string) get_post_meta($legacy_id, '_hub_landing_zip_entry', true),
            'label' => 'Migrada desde hub_landing #' . $legacy_id,
        ]);

        return $design_id;
    }

    /**
     * Extracted packages are stored under the post id, so a migrated landing
     * would point at a directory that belongs to the retired object.
     */
    private function move_package_directory(int $legacy_id, int $design_id): void
    {
        if ((string) get_post_meta($legacy_id, '_hub_landing_zip_entry', true) === '') {
            return;
        }

        $importer = HUB_Tibox_Landing_Zip_Importer::instance();
        $source = $importer->get_extract_dir($legacy_id);
        if (!is_dir($source)) {
            return;
        }

        HUB_Tibox_Filesystem::copy_directory($source, $importer->get_extract_dir($design_id));
        update_post_meta($design_id, '_hub_landing_zip_folder', (string) $design_id);
    }

    /**
     * The new design takes over the URL, so the historical object must stop
     * being reachable: two published objects with the same content is duplicate
     * content on a page that usually carries paid traffic.
     */
    private function insert_design(WP_Post $legacy, string $type): int
    {
        $status = in_array($legacy->post_status, ['publish', 'draft', 'pending', 'private'], true)
            ? $legacy->post_status
            : 'draft';

        $design_id = wp_insert_post([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => $status,
            'post_title' => $legacy->post_title,
            'post_name' => $legacy->post_name,
            'post_excerpt' => $legacy->post_excerpt,
            'post_author' => (int) $legacy->post_author,
            'post_date' => $legacy->post_date,
            'menu_order' => $legacy->menu_order,
        ], true);

        if (is_wp_error($design_id)) {
            return 0;
        }

        update_post_meta((int) $design_id, HUB_Tibox_Design::META_TYPE, $type);

        $thumbnail = get_post_thumbnail_id($legacy->ID);
        if ($thumbnail > 0) {
            set_post_thumbnail((int) $design_id, $thumbnail);
        }

        // Retire the historical object without deleting it.
        if ($legacy->post_status === 'publish') {
            wp_update_post(['ID' => $legacy->ID, 'post_status' => 'draft']);
        }

        return (int) $design_id;
    }

    /**
     * @param array<string,mixed> $data
     */
    private function seed_version(int $design_id, array $data): void
    {
        $html = (string) ($data['html'] ?? '');
        $css = (string) ($data['css'] ?? '');
        $js = (string) ($data['js'] ?? '');

        if (trim($html . $css . $js) === '' && (string) ($data['entry'] ?? '') === '') {
            return;
        }

        $store = HUB_Tibox_Version_Store::instance();
        $version_id = $store->create($design_id, [
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'entry' => (string) ($data['entry'] ?? ''),
            'source' => 'migration',
            'label' => (string) ($data['label'] ?? ''),
        ]);

        if ($version_id > 0) {
            $store->publish($design_id, $version_id);
        }
    }

    /** Carries the previous Header/Footer selection into the region model. */
    private function migrate_region_settings(): void
    {
        if (get_option(HUB_Tibox_Regions::OPTION, null) !== null) {
            return;
        }

        $enabled = get_option('hub_tibox_hybrid_enabled', '0') === '1';
        $scope = (string) get_option('hub_tibox_hybrid_scope', 'selected');
        $targets = array_map('absint', (array) get_option('hub_tibox_hybrid_pages', []));

        foreach (['header' => 'hub_tibox_active_header', 'footer' => 'hub_tibox_active_footer'] as $region => $option) {
            $legacy_component = absint(get_option($option, 0));
            $design_id = $legacy_component > 0 ? $this->find_migrated($legacy_component, self::LEGACY_COMPONENT) : 0;

            HUB_Tibox_Regions::save($region, [
                // The historical hybrid renderer owned the whole template.
                'mode' => $enabled && $design_id > 0 ? HUB_Tibox_Regions::MODE_REPLACE : HUB_Tibox_Regions::MODE_THEME,
                'design' => $design_id,
                'scope' => $scope === 'all_pages' ? HUB_Tibox_Regions::SCOPE_ALL : HUB_Tibox_Regions::SCOPE_SELECTED,
                'targets' => $targets,
                'hide_selector' => '',
            ]);
        }
    }

    /**
     * Historical landing URLs keep working: the slug moved to the design, so the
     * old permalink 301s to the new object instead of 404ing.
     */
    public function redirect_legacy_urls(): void
    {
        if (is_admin() || !is_singular([self::LEGACY_LANDING])) {
            return;
        }

        $map = (array) get_option(self::OPTION_REDIRECT_MAP, []);
        $legacy_id = get_queried_object_id();
        $design_id = absint($map[$legacy_id] ?? 0);

        if ($design_id <= 0 || get_post_status($design_id) !== 'publish') {
            return;
        }

        wp_safe_redirect((string) get_permalink($design_id), 301);
        exit;
    }

    private function find_migrated(int $legacy_id, string $legacy_type): int
    {
        $found = get_posts([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => HUB_Tibox_Design::META_LEGACY_ID, 'value' => (string) $legacy_id],
                ['key' => HUB_Tibox_Design::META_LEGACY_TYPE, 'value' => $legacy_type],
            ],
        ]);

        return $found === [] ? 0 : (int) $found[0];
    }

    /** @return int[] */
    private function legacy_ids(string $post_type): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash') ORDER BY ID ASC",
            $post_type
        ));

        return array_values(array_map('absint', is_array($ids) ? $ids : []));
    }

    /** @param array<string,string> $map */
    private function copy_meta(int $source_id, int $target_id, array $map): void
    {
        foreach ($map as $source_key => $target_key) {
            $value = get_post_meta($source_id, $source_key, true);
            if ($value === '' || $value === false || $value === []) {
                continue;
            }
            update_post_meta($target_id, $target_key, $value);
        }
    }
}
