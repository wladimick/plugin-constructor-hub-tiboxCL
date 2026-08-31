<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration assistant for the historical Tibox WPCode snippets.
 *
 * It never disables WPCode and never deletes legacy posts or tables. The goal is
 * to copy data into Constructor HUB, run QA, and only then let an administrator
 * retire the snippets one by one.
 *
 * Two things changed after the 2026-08-31 audit:
 *
 *  - the work runs in batches with a cursor, because a single admin POST over
 *    thousands of historical leads times out and leaves a state nobody can
 *    inspect;
 *  - a migrated landing lands as a draft with the legacy one left published.
 *    Publishing the new URL while the old one is still live is duplicate
 *    content on a page that usually carries paid traffic, so the cutover is an
 *    explicit action, not a side effect of copying data.
 */
final class HUB_Tibox_Legacy_Migrator
{
    public const LEGACY_POST_TYPE = 'tibox_landing';
    private const META_LEGACY_ID = '_hub_legacy_landing_id';

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
        add_action('constructor_hub_admin_menu', [$this, 'register_page']);
        add_action('admin_post_hub_tibox_migrate_wpcode', [$this, 'run_migration']);
        add_action('admin_post_hub_tibox_cutover_landing', [$this, 'run_cutover']);
        add_action('template_redirect', [$this, 'redirect_retired_landings'], -90);
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_settings()
            ? HUB_Tibox_Capabilities::MANAGE_SETTINGS
            : 'manage_options';

        add_submenu_page($parent, 'Migración WPCode', 'Migración WPCode', $capability, 'constructor-hub-migration', [$this, 'render_page']);
    }

    // ------------------------------------------------------------- inventory

    /** @return int[] */
    public function legacy_landing_ids(): array
    {
        global $wpdb;

        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash') ORDER BY ID ASC",
            self::LEGACY_POST_TYPE
        ));

        return array_values(array_map('absint', is_array($ids) ? $ids : []));
    }

    /** @return int[] */
    public function pending_landing_ids(): array
    {
        return array_values(array_filter(
            $this->legacy_landing_ids(),
            fn(int $legacy_id): bool => $this->find_migrated($legacy_id) === 0
        ));
    }

    public function pending_lead_count(): int
    {
        return HUB_Tibox_Landing_Lead_Store::instance()->pending_legacy_leads();
    }

    // ------------------------------------------------------------- migration

    /**
     * Copies one historical landing into a HUB design.
     *
     * Returns the new design id, or 0 when it was skipped.
     */
    public function migrate_landing(int $legacy_id): int
    {
        if ($this->find_migrated($legacy_id) > 0) {
            return 0;
        }

        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            return 0;
        }

        $design_id = wp_insert_post([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            // Always a draft: the cutover is a separate, deliberate step.
            'post_status' => 'draft',
            'post_title' => $legacy->post_title,
            'post_name' => $legacy->post_name . '-hub',
            'post_excerpt' => $legacy->post_excerpt,
            'post_author' => (int) $legacy->post_author,
        ], true);

        if (is_wp_error($design_id)) {
            return 0;
        }

        $design_id = (int) $design_id;

        $legacy_mode = (string) get_post_meta($legacy_id, '_tibox_lp_mode', true);
        $html = '';
        $mode = HUB_Tibox_Design::MODE_HUB;

        if ($legacy_mode === 'wordpress') {
            $mode = HUB_Tibox_Design::MODE_LEGACY;
            $this->copy_elementor_meta($legacy_id, $design_id);
        } elseif ($legacy_mode === 'full_html') {
            $mode = HUB_Tibox_Design::MODE_STANDALONE;
            $html = (string) get_post_meta($legacy_id, '_tibox_lp_full_html', true);
        } else {
            // The historical Canvas mode rendered post_content without chrome.
            $html = (string) $legacy->post_content;
        }

        update_post_meta($design_id, HUB_Tibox_Design::META_TYPE, 'landing');
        update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, $mode);
        update_post_meta($design_id, self::META_LEGACY_ID, $legacy_id);
        update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '0');

        // The historical Tibox endpoint enforced these three fields.
        update_post_meta($design_id, HUB_Tibox_Design::META_REQUIRED_FIELDS, ['name', 'company', 'rut']);

        $this->copy_meta($legacy_id, $design_id, [
            '_tibox_lp_ads_active' => HUB_Tibox_Design::META_ADS_ACTIVE,
            '_tibox_lp_ads_campaign_name' => HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME,
            '_tibox_lp_ads_campaign_id' => HUB_Tibox_Design::META_ADS_CAMPAIGN_ID,
            '_tibox_lp_ads_start_date' => HUB_Tibox_Design::META_ADS_START_DATE,
            '_tibox_lp_ads_end_date' => HUB_Tibox_Design::META_ADS_END_DATE,
            '_tibox_lp_ads_final_url' => HUB_Tibox_Design::META_ADS_FINAL_URL,
            '_tibox_lp_ads_notes' => HUB_Tibox_Design::META_ADS_NOTES,
        ]);

        $entry = (string) get_post_meta($legacy_id, '_tibox_lp_zip_entry', true);
        if ($entry !== '') {
            $mode = HUB_Tibox_Design::MODE_PACKAGE;
            update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, $mode);
        }

        $store = HUB_Tibox_Version_Store::instance();
        $version_id = $store->create($design_id, [
            'html' => $html,
            'css' => (string) get_post_meta($legacy_id, '_tibox_lp_custom_css', true),
            'js' => (string) get_post_meta($legacy_id, '_tibox_lp_custom_js', true),
            'entry' => $entry,
            'source' => 'wpcode',
            'label' => 'Migrada desde WPCode #' . $legacy_id,
        ]);

        if ($version_id > 0) {
            $store->publish($design_id, $version_id);
            $this->copy_package_directory($legacy_id, $design_id, $version_id, $entry);
        }

        $thumbnail = get_post_thumbnail_id($legacy_id);
        if ($thumbnail > 0) {
            set_post_thumbnail($design_id, $thumbnail);
        }

        return $design_id;
    }

    public function run_migration(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_migrate_wpcode', 'hub_tibox_migration_nonce');

        $batch = isset($_POST['hub_batch']) ? max(1, absint($_POST['hub_batch'])) : 25;

        $created = 0;
        foreach (array_slice($this->pending_landing_ids(), 0, $batch) as $legacy_id) {
            if ($this->migrate_landing($legacy_id) > 0) {
                $created++;
            }
        }

        $leads = HUB_Tibox_Landing_Lead_Store::instance()->migrate_legacy_leads($batch * 10);

        update_option('hub_tibox_legacy_last_migration', [
            'at' => current_time('mysql'),
            'landings_created' => $created,
            'leads_migrated' => $leads['migrated'],
            'leads_remaining' => $leads['remaining'],
        ], false);

        wp_safe_redirect(add_query_arg([
            'page' => 'constructor-hub-migration',
            'hub_notice' => 'migrated',
        ], admin_url('admin.php')));
        exit;
    }

    // --------------------------------------------------------------- cutover

    /**
     * Switches traffic from a historical landing to its HUB design.
     *
     * Publishes the design, retires the legacy post and records the redirect.
     * This is the only moment a public URL changes hands, and it is one click
     * that an administrator has to make on purpose.
     */
    public function run_cutover(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $legacy_id = isset($_GET['legacy_id']) ? absint($_GET['legacy_id']) : 0;
        check_admin_referer('hub_tibox_cutover_' . $legacy_id);

        $design_id = $this->find_migrated($legacy_id);
        $legacy = get_post($legacy_id);

        if ($design_id <= 0 || !$legacy instanceof WP_Post) {
            wp_die(esc_html__('No hay un diseño migrado para esa landing.', 'constructor-hub-tibox'));
        }

        // The design takes the historical slug so the URL keeps its history and
        // its Google Ads final URL, if the base path allows it.
        wp_update_post([
            'ID' => $design_id,
            'post_status' => 'publish',
            'post_name' => $legacy->post_name,
        ]);

        wp_update_post(['ID' => $legacy_id, 'post_status' => 'draft']);

        $redirects = (array) get_option('hub_tibox_wpcode_redirects', []);
        $redirects[$legacy_id] = $design_id;
        update_option('hub_tibox_wpcode_redirects', $redirects, false);

        wp_safe_redirect(add_query_arg([
            'page' => 'constructor-hub-migration',
            'hub_notice' => 'cutover',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * A retired landing that is still reachable 301s to its replacement, so no
     * inbound link or ad ever lands on a 404.
     */
    public function redirect_retired_landings(): void
    {
        if (is_admin() || !is_singular([self::LEGACY_POST_TYPE])) {
            return;
        }

        $redirects = (array) get_option('hub_tibox_wpcode_redirects', []);
        $design_id = absint($redirects[get_queried_object_id()] ?? 0);

        if ($design_id <= 0 || get_post_status($design_id) !== 'publish') {
            return;
        }

        wp_safe_redirect((string) get_permalink($design_id), 301);
        exit;
    }

    // ----------------------------------------------------------------- admin

    public function render_page(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $store = HUB_Tibox_Landing_Lead_Store::instance();
        $legacy_ids = $this->legacy_landing_ids();
        $pending = $this->pending_landing_ids();
        $last = get_option('hub_tibox_legacy_last_migration', []);
        $notice = isset($_GET['hub_notice']) ? sanitize_key(wp_unslash($_GET['hub_notice'])) : '';

        $snippets = [
            'Gestor de Landings' => class_exists('TIBOX_Landing_Manager'),
            'Endpoint / Leads' => class_exists('TIBOX_Landing_Form_API'),
            'Importador ZIP' => class_exists('TIBOX_Landing_Zip_Importer'),
        ];
        ?>
        <div class="wrap">
            <h1>Migración desde WPCode</h1>
            <p>
                Copia landings y leads históricos hacia Constructor HUB.
                <strong>No borra la fuente anterior y no desactiva WPCode.</strong>
            </p>

            <?php if ($notice === 'migrated') : ?>
                <div class="notice notice-success is-dismissible"><p>Lote migrado. Revisa las landings creadas antes de hacer el cambio de URL.</p></div>
            <?php elseif ($notice === 'cutover') : ?>
                <div class="notice notice-success is-dismissible"><p>URL traspasada. La landing histórica quedó en borrador y redirige con 301.</p></div>
            <?php endif; ?>

            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th style="width:280px;">Landings históricas detectadas</th><td><?php echo esc_html((string) count($legacy_ids)); ?></td></tr>
                    <tr><th>Pendientes de migrar</th><td><?php echo esc_html((string) count($pending)); ?></td></tr>
                    <tr><th>Leads pendientes</th><td><?php echo esc_html((string) $this->pending_lead_count()); ?></td></tr>
                    <tr>
                        <th>Tabla histórica</th>
                        <td><code><?php echo esc_html($store->legacy_table_name()); ?></code> — <?php echo $store->legacy_table_exists() ? 'detectada' : 'no detectada'; ?></td>
                    </tr>
                </tbody>
            </table>

            <h2>Snippets activos en esta petición</h2>
            <ul>
                <?php foreach ($snippets as $label => $active) : ?>
                    <li><?php echo esc_html($label); ?>: <strong><?php echo $active ? 'ACTIVO' : 'no detectado'; ?></strong></li>
                <?php endforeach; ?>
            </ul>

            <?php if (is_array($last) && $last !== []) : ?>
                <div class="notice notice-info inline"><p>
                    Último lote: <?php echo esc_html((string) ($last['at'] ?? '')); ?> —
                    landings nuevas: <?php echo esc_html((string) ($last['landings_created'] ?? 0)); ?>,
                    leads migrados: <?php echo esc_html((string) ($last['leads_migrated'] ?? 0)); ?>,
                    leads restantes: <?php echo esc_html((string) ($last['leads_remaining'] ?? 0)); ?>.
                </p></div>
            <?php endif; ?>

            <div class="notice notice-warning inline"><p>
                <strong>Orden seguro:</strong> migrar → revisar cada landing en preview → traspasar la URL una por una →
                validar formularios, correo y conversiones → recién entonces desactivar los snippets WPCode.
            </p></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_migrate_wpcode', 'hub_tibox_migration_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_migrate_wpcode">
                <p>
                    <label for="hub-batch">Landings por lote</label>
                    <input id="hub-batch" type="number" name="hub_batch" value="25" min="1" max="200" class="small-text">
                </p>
                <?php submit_button('Migrar un lote', 'primary'); ?>
            </form>

            <p class="description">
                Con volúmenes grandes conviene la línea de comandos, que no depende del tiempo máximo de ejecución
                de PHP: <code>wp hub migrate-wpcode --batch=200</code>.
            </p>

            <?php if ($legacy_ids !== []) : ?>
                <h2>Estado por landing</h2>
                <table class="widefat striped">
                    <thead>
                        <tr><th>Landing histórica</th><th style="width:200px;">Diseño HUB</th><th style="width:130px;">Estado</th><th style="width:220px;"></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($legacy_ids as $legacy_id) : ?>
                        <?php
                        $design_id = $this->find_migrated($legacy_id);
                        $legacy_post = get_post($legacy_id);
                        if (!$legacy_post instanceof WP_Post) {
                            continue;
                        }
                        $published = $design_id > 0 && get_post_status($design_id) === 'publish';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($legacy_post->post_title ?: ('#' . $legacy_id)); ?></strong><br>
                                <code><?php echo esc_html((string) $legacy_post->post_name); ?></code>
                            </td>
                            <td>
                                <?php if ($design_id > 0) : ?>
                                    <a href="<?php echo esc_url((string) get_edit_post_link($design_id)); ?>">#<?php echo esc_html((string) $design_id); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($published) : ?>
                                    <strong style="color:#00713c;">URL traspasada</strong>
                                <?php elseif ($design_id > 0) : ?>
                                    Migrada, sin publicar
                                <?php else : ?>
                                    Pendiente
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($design_id > 0 && !$published) : ?>
                                    <a class="button" target="_blank" rel="noopener"
                                       href="<?php echo esc_url((string) get_preview_post_link($design_id)); ?>">Preview</a>
                                    <a class="button button-primary"
                                       href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                           'action' => 'hub_tibox_cutover_landing',
                                           'legacy_id' => $legacy_id,
                                       ], admin_url('admin-post.php')), 'hub_tibox_cutover_' . $legacy_id)); ?>">Traspasar URL</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    // --------------------------------------------------------------- helpers

    public function find_migrated(int $legacy_id): int
    {
        $found = get_posts([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => self::META_LEGACY_ID,
            'meta_value' => (string) $legacy_id,
        ]);

        return $found === [] ? 0 : (int) $found[0];
    }

    private function copy_elementor_meta(int $source_id, int $target_id): void
    {
        foreach (get_post_meta($source_id) as $key => $values) {
            if (!str_starts_with((string) $key, '_elementor')) {
                continue;
            }

            foreach ($values as $value) {
                add_post_meta($target_id, (string) $key, maybe_unserialize($value));
            }
        }
    }

    private function copy_package_directory(int $legacy_id, int $design_id, int $version_id, string $entry): void
    {
        if ($entry === '') {
            return;
        }

        $upload = wp_upload_dir();
        $source = trailingslashit((string) $upload['basedir']) . 'tibox-landings/' . $legacy_id;

        if (!is_dir($source)) {
            return;
        }

        $target = HUB_Tibox_Package::instance()->package_dir($design_id, $version_id);
        HUB_Tibox_Filesystem::copy_directory($source, $target);
        HUB_Tibox_Version_Store::instance()->update_assets(
            $version_id,
            'constructor-hub/packages/' . $design_id . '/' . $version_id,
            $entry
        );
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
