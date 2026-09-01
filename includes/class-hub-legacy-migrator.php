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
 * Three things changed after the 2026-08-31 audit:
 *
 *  - the work runs in batches with a cursor, because a single admin POST over
 *    thousands of historical leads times out and leaves a state nobody can
 *    inspect;
 *  - a migrated landing lands as a draft with the legacy one left published.
 *    Publishing the new URL while the old one is still live is duplicate
 *    content on a page that usually carries paid traffic, so the cutover is an
 *    explicit action, not a side effect of copying data;
 *  - `migrate_landing()` verifies every indispensable staging step —
 *    version creation, version publish, package copy when one applies —
 *    instead of returning the new design id regardless of whether they
 *    actually succeeded. A `Version_Store::create()` that returns `0`, a
 *    `publish()` that returns `false`, or a package copy that fails no longer
 *    get reported as a completed migration: the landing stays pending, and
 *    `run_cutover()` independently refuses to publish a URL on top of a
 *    design whose live version is not actually usable.
 */
final class HUB_Tibox_Legacy_Migrator
{
    public const LEGACY_POST_TYPE = 'tibox_landing';
    private const META_LEGACY_ID = '_hub_legacy_landing_id';

    /**
     * Recorded on the NEW design only once every indispensable staging step
     * has succeeded. A design row can exist without this flag: that is a
     * staged-but-incomplete row left over from a failed attempt, identifiable
     * by carrying `META_LEGACY_ID` without it. A retry resumes that same row
     * instead of creating a duplicate. Mirrors `HUB_Tibox_Upgrade::META_STAGED`
     * for this migrator's own legacy source.
     */
    private const META_STAGED = '_hub_wpcode_migration_staged';

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
     * Returns the new design id only once every indispensable staging step —
     * version creation, version publish, and the package copy when one
     * applies — has actually succeeded. Returns 0 when the landing is already
     * migrated, has disappeared, or when any of those steps failed; in the
     * failed case the landing is left exactly as it was (still pending, still
     * reported so from `pending_landing_ids()`), and a retry resumes the same
     * design row instead of inserting a duplicate.
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

        $legacy_mode = (string) get_post_meta($legacy_id, '_tibox_lp_mode', true);
        $html = '';
        $mode = HUB_Tibox_Design::MODE_HUB;

        if ($legacy_mode === 'wordpress') {
            $mode = HUB_Tibox_Design::MODE_LEGACY;
        } elseif ($legacy_mode === 'full_html') {
            $mode = HUB_Tibox_Design::MODE_STANDALONE;
            $html = (string) get_post_meta($legacy_id, '_tibox_lp_full_html', true);
        } else {
            // The historical Canvas mode rendered post_content without chrome.
            $html = (string) $legacy->post_content;
        }

        $entry = (string) get_post_meta($legacy_id, '_tibox_lp_zip_entry', true);
        if ($entry !== '') {
            $mode = HUB_Tibox_Design::MODE_PACKAGE;
        }

        // A row from a previous attempt that never reached META_STAGED is
        // resumed rather than recreated — otherwise every retry of a failure
        // that happened after the design was inserted would leave one more
        // orphaned draft behind, and `copy_elementor_meta()`/`copy_meta()`
        // would duplicate meta entries on top of it (both use `add_post_meta`,
        // which allows repeats).
        $design_id = $this->find_staged_design_id($legacy_id);

        if ($design_id <= 0) {
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

            update_post_meta($design_id, HUB_Tibox_Design::META_TYPE, 'landing');
            update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, $mode);
            update_post_meta($design_id, self::META_LEGACY_ID, $legacy_id);
            update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '0');

            // The historical Tibox endpoint enforced these three fields.
            update_post_meta($design_id, HUB_Tibox_Design::META_REQUIRED_FIELDS, ['name', 'company', 'rut']);

            if ($legacy_mode === 'wordpress') {
                $this->copy_elementor_meta($legacy_id, $design_id);
            }

            $this->copy_meta($legacy_id, $design_id, [
                '_tibox_lp_ads_active' => HUB_Tibox_Design::META_ADS_ACTIVE,
                '_tibox_lp_ads_campaign_name' => HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME,
                '_tibox_lp_ads_campaign_id' => HUB_Tibox_Design::META_ADS_CAMPAIGN_ID,
                '_tibox_lp_ads_start_date' => HUB_Tibox_Design::META_ADS_START_DATE,
                '_tibox_lp_ads_end_date' => HUB_Tibox_Design::META_ADS_END_DATE,
                '_tibox_lp_ads_final_url' => HUB_Tibox_Design::META_ADS_FINAL_URL,
                '_tibox_lp_ads_notes' => HUB_Tibox_Design::META_ADS_NOTES,
            ]);

            $thumbnail = get_post_thumbnail_id($legacy_id);
            if ($thumbnail > 0) {
                set_post_thumbnail($design_id, $thumbnail);
            }
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

        $published = $version_id > 0 && $store->publish($design_id, $version_id);
        $version_result = HUB_Tibox_Upgrade::evaluate_version_write($design_id, $version_id, $published);

        $package_result = ['status' => 'skipped', 'error' => ''];
        if ($version_result['status'] !== 'failed') {
            $package_result = $this->copy_package_directory($legacy_id, $design_id, $version_id, $entry);
        }

        $stage = self::evaluate_stage_result($version_result, $package_result);
        if ($stage['status'] === 'failed') {
            // The design row and whatever metadata it already carries stay
            // exactly where they are — visible via META_LEGACY_ID, but never
            // counted as migrated because META_STAGED is never written. A
            // retry finds this same row through find_staged_design_id() and
            // only re-attempts the version/package steps.
            return 0;
        }

        update_post_meta($design_id, self::META_STAGED, '1');

        return $design_id;
    }

    /**
     * Pure decision for whether a WPCode landing's staging attempt is
     * complete, given the already-known outcome of its indispensable steps.
     * No WordPress calls — reuses the same classifiers
     * `HUB_Tibox_Upgrade` defined for its own two-phase migration: a version
     * the store could not create or publish, or a package that could not be
     * copied, are failures here exactly as they are there.
     *
     * @param array{status:string,error:string} $version_result
     * @param array{status:string,error:string} $package_result
     * @return array{status:string,error:string} status: 'ok'|'failed'
     */
    public static function evaluate_stage_result(array $version_result, array $package_result): array
    {
        if ($version_result['status'] === 'failed') {
            return ['status' => 'failed', 'error' => $version_result['error']];
        }

        if ($package_result['status'] === 'failed') {
            return ['status' => 'failed', 'error' => $package_result['error']];
        }

        return ['status' => 'ok', 'error' => ''];
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

        // Independent of META_STAGED: even a design the migration marked
        // complete could have had its live version altered or removed since.
        // Publishing a URL on top of one with nothing actually renderable
        // would trade a working legacy landing for a blank one.
        $blocking_reason = $this->cutover_readiness_error($design_id);
        if ($blocking_reason !== '') {
            wp_die(esc_html($blocking_reason));
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

    /**
     * A design counts as "already migrated" only once every indispensable
     * staging step succeeded. A row that exists but never reached
     * `META_STAGED` is a leftover from a failed attempt, not a finished
     * migration — see `find_staged_design_id()` for that case.
     */
    public function find_migrated(int $legacy_id): int
    {
        $found = get_posts([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => self::META_LEGACY_ID, 'value' => (string) $legacy_id],
                ['key' => self::META_STAGED, 'value' => '1'],
            ],
        ]);

        return $found === [] ? 0 : (int) $found[0];
    }

    /**
     * Any design row created for this legacy landing, complete or not. Used
     * to resume a staged-but-incomplete row on retry instead of inserting a
     * duplicate `hub_design` post every time the same failure is hit.
     */
    private function find_staged_design_id(int $legacy_id): int
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

    /**
     * @return array{status:string,error:string} status: 'ok'|'skipped'|'failed'
     */
    private function copy_package_directory(int $legacy_id, int $design_id, int $version_id, string $entry): array
    {
        if ($entry === '') {
            return ['status' => 'skipped', 'error' => ''];
        }

        $upload = wp_upload_dir();
        $source = trailingslashit((string) $upload['basedir']) . 'tibox-landings/' . $legacy_id;
        $source_exists = is_dir($source);

        $target = HUB_Tibox_Package::instance()->package_dir($design_id, $version_id);
        $copied = $source_exists ? HUB_Tibox_Filesystem::copy_directory($source, $target) : null;

        $result = HUB_Tibox_Upgrade::evaluate_package_copy($legacy_id, $design_id, $entry, $source, $source_exists, $copied);

        if ($result['status'] === 'ok') {
            HUB_Tibox_Version_Store::instance()->update_assets(
                $version_id,
                'constructor-hub/packages/' . $design_id . '/' . $version_id,
                $entry
            );
        }

        return $result;
    }

    /**
     * Returns an empty string when the design's live version is actually
     * renderable, or an explanation otherwise. Split into a pure verdict and a
     * thin WordPress-calling wrapper so the decision itself is unit-testable.
     */
    private function cutover_readiness_error(int $design_id): string
    {
        $live = HUB_Tibox_Version_Store::instance()->get_live($design_id);
        $mode = HUB_Tibox_Design::get_render_mode($design_id);

        $package_file_exists = null;
        if ($live !== null && $mode === HUB_Tibox_Design::MODE_PACKAGE) {
            $entry = trim((string) ($live['entry'] ?? ''));
            if ($entry !== '') {
                $file = trailingslashit(HUB_Tibox_Package::instance()->package_dir($design_id, (int) $live['id'])) . $entry;
                $package_file_exists = is_file($file);
            }
        }

        return self::evaluate_cutover_readiness($live, $mode, $package_file_exists);
    }

    /**
     * Pure verdict on whether a design's live version is something a cutover
     * can safely publish. No WordPress or filesystem calls.
     *
     * MODE_LEGACY carries no content of its own — the theme/Elementor renders
     * the post via `the_content()` — so an empty version there is expected,
     * not a failure.
     *
     * @param array<string,mixed>|null $live
     */
    public static function evaluate_cutover_readiness(?array $live, string $mode, ?bool $package_file_exists): string
    {
        if ($live === null) {
            return 'El diseño migrado no tiene una versión publicada. No se puede traspasar la URL todavía.';
        }

        if ($mode === HUB_Tibox_Design::MODE_LEGACY) {
            return '';
        }

        if ($mode === HUB_Tibox_Design::MODE_PACKAGE) {
            $entry = trim((string) ($live['entry'] ?? ''));
            if ($entry === '') {
                return 'La versión publicada no declara un archivo de entrada de package. No se puede traspasar la URL todavía.';
            }

            if ($package_file_exists !== true) {
                return 'El archivo de entrada del package no está disponible en disco. No se puede traspasar la URL todavía.';
            }

            return '';
        }

        if (trim((string) ($live['html'] ?? '')) === '') {
            return 'La versión publicada no tiene contenido HTML. No se puede traspasar la URL todavía.';
        }

        return '';
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
