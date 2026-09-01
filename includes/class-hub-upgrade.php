<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Install and upgrade routine.
 *
 * Migrates `hub_component` and `hub_landing` into the unified `hub_design` type
 * and turns the code stored in post meta into the first row of the version
 * history. It never deletes the source: the old posts stay in the database.
 *
 * The migration runs in two phases, and nothing externally visible changes
 * until the whole batch is known to succeed:
 *
 *  - **Stage**: every not-yet-migrated legacy object gets copied into a new
 *    `hub_design` post, created as a draft. The legacy object is never
 *    touched here. Staging itself is a short pipeline — create the post, copy
 *    its metadata, create the version, publish the version, copy the package
 *    when one applies — and every indispensable step is verified: a design is
 *    only marked `created` once all of them have actually succeeded. A
 *    `wp_insert_post()` failure, a version the store could not create or
 *    publish, or a package that could not be copied, are all recorded as a
 *    failure for that item and do not stop the rest of the batch from
 *    staging. A design left incomplete by a failure is never mistaken for a
 *    finished one on retry — see `META_STAGED`.
 *  - **Cutover**: only when staging produced zero failures, each legacy
 *    object's original status is recorded on itself (so it can be restored
 *    later), the new design is published with that same status, and the
 *    legacy object is retired to draft. Only then is
 *    `hub_tibox_designs_unified` set to `1`.
 *
 * A partial result — one item failed to stage — never demotes a single legacy
 * object and never activates the unified layer: the site keeps rendering
 * exactly as before until an administrator fixes the failure and retries.
 * Retrying is safe to repeat: already-staged items are detected and skipped.
 */
final class HUB_Tibox_Upgrade
{
    public const OPTION_VERSION = 'hub_tibox_plugin_version';
    public const OPTION_UNIFIED = 'hub_tibox_designs_unified';
    public const OPTION_STATUS = 'hub_tibox_designs_unification_status';
    public const OPTION_RESULT = 'hub_tibox_designs_unification_result';
    public const OPTION_ROLLBACK_RESULT = 'hub_tibox_designs_rollback_result';
    public const OPTION_REDIRECT_MAP = 'hub_tibox_legacy_redirects';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    /** Recorded on the LEGACY post so a rollback knows what to restore. */
    private const META_PREVIOUS_STATUS = '_hub_migration_previous_status';

    /**
     * Recorded on the NEW design only once every indispensable staging step —
     * version creation, version publish, and the package copy when one applies
     * — has succeeded. A design row can exist without this flag: that is a
     * staged-but-incomplete row left over from a failed attempt, identifiable
     * by carrying `META_LEGACY_ID` without it. A retry resumes that same row
     * instead of creating a duplicate.
     */
    private const META_STAGED = '_hub_migration_staged';

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

    public static function status(): string
    {
        $status = (string) get_option(self::OPTION_STATUS, '');
        $known = [self::STATUS_PENDING, self::STATUS_PARTIAL, self::STATUS_COMPLETE, self::STATUS_ROLLED_BACK];

        return in_array($status, $known, true) ? $status : self::STATUS_PENDING;
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

    /**
     * Runs on every version bump. Table creation and capability grants are
     * idempotent and safe to repeat; the design migration only runs while the
     * site has not reached `complete`.
     */
    public function install(): void
    {
        HUB_Tibox_Capabilities::grant();
        HUB_Tibox_Version_Store::instance()->maybe_install_table();
        HUB_Tibox_Landing_Lead_Store::instance()->maybe_install_table();
        HUB_Tibox_Mail_Log::instance()->maybe_install_table();

        if (!self::is_unified()) {
            $this->run_and_record_migration();
        }

        // Post types change between versions; the rules must be rebuilt once.
        delete_option('hub_tibox_design_rewrite_version');
    }

    /**
     * Explicit, administrator-triggered retry after a partial migration was
     * fixed. A no-op once the site is already unified.
     *
     * @return array{status:string,created:int,existing:int,missing:int,failed:int,failures:array<int,array<string,mixed>>}
     */
    public function retry_migration(): array
    {
        if (self::is_unified()) {
            return $this->empty_result(self::STATUS_COMPLETE);
        }

        return $this->run_and_record_migration();
    }

    /**
     * @return array{status:string,created:int,existing:int,missing:int,failed:int,failures:array<int,array<string,mixed>>}
     */
    private function run_and_record_migration(): array
    {
        $result = $this->migrate_legacy_designs();

        update_option(self::OPTION_RESULT, $result, false);
        update_option(self::OPTION_STATUS, $result['status'], false);

        if ($result['status'] === self::STATUS_COMPLETE) {
            update_option(self::OPTION_UNIFIED, '1', true);
        }

        // Partial: OPTION_UNIFIED stays untouched. The site keeps rendering
        // through the historical modules — nothing a visitor sees changes —
        // and the failures are reported for an administrator to fix and
        // retry from Constructor HUB → Diagnóstico.
        return $result;
    }

    /**
     * @return array{status:string,created:int,existing:int,missing:int,failed:int,failures:array<int,array<string,mixed>>}
     */
    public function migrate_legacy_designs(): array
    {
        $items = [];

        foreach ($this->legacy_ids(self::LEGACY_COMPONENT) as $legacy_id) {
            $items[] = $this->stage_component($legacy_id);
        }

        foreach ($this->legacy_ids(self::LEGACY_LANDING) as $legacy_id) {
            $items[] = $this->stage_landing($legacy_id);
        }

        $summary = self::evaluate_migration_result($items);

        if ($summary['status'] === self::STATUS_COMPLETE) {
            $this->cutover($items);
            $this->migrate_region_settings();
        }

        return $summary;
    }

    /**
     * Pure summary of a staging pass. No WordPress calls, so this is the part
     * covered by unit tests: it is exactly where "activate Unified despite a
     * failure" would be decided.
     *
     * @param array<int,array{status:string}> $items
     * @return array{status:string,created:int,existing:int,missing:int,failed:int,failures:array<int,array<string,mixed>>}
     */
    public static function evaluate_migration_result(array $items): array
    {
        $counts = ['created' => 0, 'existing' => 0, 'missing' => 0, 'failed' => 0];
        $failures = [];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'failed');
            if (!isset($counts[$status])) {
                // An unrecognised status is treated as a failure rather than
                // silently ignored: activating Unified must never be the
                // default when something did not go as expected.
                $status = 'failed';
            }

            $counts[$status]++;

            if ($status === 'failed') {
                $failures[] = $item;
            }
        }

        return [
            'status' => $failures === [] ? self::STATUS_COMPLETE : self::STATUS_PARTIAL,
            'created' => $counts['created'],
            'existing' => $counts['existing'],
            'missing' => $counts['missing'],
            'failed' => $counts['failed'],
            'failures' => $failures,
        ];
    }

    // ------------------------------------------------------------- staging

    /**
     * @return array{type:string,legacy_id:int,status:string,design_id:int,error:string}
     */
    private function stage_component(int $legacy_id): array
    {
        $existing = $this->find_migrated($legacy_id, self::LEGACY_COMPONENT);
        if ($existing > 0) {
            return $this->item(self::LEGACY_COMPONENT, $legacy_id, 'existing', $existing);
        }

        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            // The row disappeared between the inventory scan and this attempt.
            // Nothing to migrate, and not a reason to block the rest of the
            // batch: it carries no content that could be lost.
            return $this->item(self::LEGACY_COMPONENT, $legacy_id, 'missing');
        }

        // A row from a previous attempt that never reached META_STAGED is
        // resumed rather than recreated — otherwise every retry of a failure
        // that happened after the design was inserted would leave one more
        // orphaned draft behind.
        $design_id = $this->find_staged_design_id($legacy_id, self::LEGACY_COMPONENT);

        if ($design_id <= 0) {
            $type = (string) get_post_meta($legacy_id, '_hub_component_type', true);
            if (!in_array($type, ['header', 'footer'], true)) {
                $type = 'header';
            }

            $design_id = $this->stage_design($legacy, $type);
            if (is_wp_error($design_id)) {
                return $this->item(self::LEGACY_COMPONENT, $legacy_id, 'failed', 0, $design_id->get_error_message());
            }

            update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_ID, $legacy_id);
            update_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_TYPE, self::LEGACY_COMPONENT);
            update_post_meta($design_id, HUB_Tibox_Design::META_RENDER_MODE, HUB_Tibox_Design::MODE_HUB);
            // Existing components were authored without isolation and may rely
            // on styling the theme's markup. Turning scoping on silently would
            // change how they render.
            update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '0');
        }

        $version = $this->seed_version($design_id, [
            'html' => (string) get_post_meta($legacy_id, '_hub_component_html', true),
            'css' => (string) get_post_meta($legacy_id, '_hub_component_css', true),
            'js' => (string) get_post_meta($legacy_id, '_hub_component_js', true),
            'label' => 'Migrada desde hub_component #' . $legacy_id,
        ]);

        if ($version['status'] === 'failed') {
            return $this->item(self::LEGACY_COMPONENT, $legacy_id, 'failed', $design_id, $version['error']);
        }

        update_post_meta($design_id, self::META_STAGED, '1');

        return $this->item(self::LEGACY_COMPONENT, $legacy_id, 'created', $design_id);
    }

    /**
     * @return array{type:string,legacy_id:int,status:string,design_id:int,error:string}
     */
    private function stage_landing(int $legacy_id): array
    {
        $existing = $this->find_migrated($legacy_id, self::LEGACY_LANDING);
        if ($existing > 0) {
            return $this->item(self::LEGACY_LANDING, $legacy_id, 'existing', $existing);
        }

        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            return $this->item(self::LEGACY_LANDING, $legacy_id, 'missing');
        }

        $mode = (string) get_post_meta($legacy_id, '_hub_landing_mode', true);
        $modes = [
            'legacy' => HUB_Tibox_Design::MODE_LEGACY,
            'hub' => HUB_Tibox_Design::MODE_HUB,
            'standalone' => HUB_Tibox_Design::MODE_STANDALONE,
            'package' => HUB_Tibox_Design::MODE_PACKAGE,
        ];
        $mode = $modes[$mode] ?? HUB_Tibox_Design::MODE_HUB;

        // A row from a previous attempt that never reached META_STAGED is
        // resumed rather than recreated — otherwise every retry of a failure
        // that happened after the design was inserted would leave one more
        // orphaned draft behind.
        $design_id = $this->find_staged_design_id($legacy_id, self::LEGACY_LANDING);

        if ($design_id <= 0) {
            $design_id = $this->stage_design($legacy, 'landing');
            if (is_wp_error($design_id)) {
                return $this->item(self::LEGACY_LANDING, $legacy_id, 'failed', 0, $design_id->get_error_message());
            }

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
        }

        // The version is the design's content; without it the object is
        // useless even if a package eventually copies fine, so it is created
        // — and verified — before the package step, matching the order a
        // failure should be reported in.
        $html = $mode === HUB_Tibox_Design::MODE_STANDALONE
            ? (string) get_post_meta($legacy_id, '_hub_landing_full_html', true)
            : (string) get_post_meta($legacy_id, '_hub_landing_html', true);

        $version = $this->seed_version($design_id, [
            'html' => $html,
            'css' => (string) get_post_meta($legacy_id, '_hub_landing_css', true),
            'js' => (string) get_post_meta($legacy_id, '_hub_landing_js', true),
            'entry' => (string) get_post_meta($legacy_id, '_hub_landing_zip_entry', true),
            'label' => 'Migrada desde hub_landing #' . $legacy_id,
        ]);

        if ($version['status'] === 'failed') {
            return $this->item(self::LEGACY_LANDING, $legacy_id, 'failed', $design_id, $version['error']);
        }

        $package = $this->move_package_directory($legacy_id, $design_id);
        if ($package['status'] === 'failed') {
            return $this->item(self::LEGACY_LANDING, $legacy_id, 'failed', $design_id, $package['error']);
        }

        update_post_meta($design_id, self::META_STAGED, '1');

        return $this->item(self::LEGACY_LANDING, $legacy_id, 'created', $design_id);
    }

    /**
     * Creates the new design as a draft, regardless of the legacy object's own
     * status. The public status is only ever applied during cutover, once the
     * whole batch is known to have succeeded.
     *
     * @return int|WP_Error
     */
    private function stage_design(WP_Post $legacy, string $type)
    {
        $design_id = wp_insert_post([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => $legacy->post_title,
            'post_name' => $legacy->post_name,
            'post_excerpt' => $legacy->post_excerpt,
            'post_author' => (int) $legacy->post_author,
            'post_date' => $legacy->post_date,
            'menu_order' => $legacy->menu_order,
        ], true);

        if (is_wp_error($design_id)) {
            return $design_id;
        }

        $design_id = (int) $design_id;
        update_post_meta($design_id, HUB_Tibox_Design::META_TYPE, $type);

        $thumbnail = get_post_thumbnail_id($legacy->ID);
        if ($thumbnail > 0) {
            set_post_thumbnail($design_id, $thumbnail);
        }

        return $design_id;
    }

    /** @return array{type:string,legacy_id:int,status:string,design_id:int,error:string} */
    private function item(string $type, int $legacy_id, string $status, int $design_id = 0, string $error = ''): array
    {
        return ['type' => $type, 'legacy_id' => $legacy_id, 'status' => $status, 'design_id' => $design_id, 'error' => $error];
    }

    // ------------------------------------------------------------- cutover

    /**
     * Only reached once staging produced zero failures. Retires each legacy
     * object and publishes its replacement with the same status it had.
     *
     * @param array<int,array{type:string,legacy_id:int,status:string,design_id:int}> $items
     */
    private function cutover(array $items): void
    {
        $redirects = (array) get_option(self::OPTION_REDIRECT_MAP, []);

        foreach ($items as $item) {
            if (!in_array($item['status'], ['created', 'existing'], true) || (int) $item['design_id'] <= 0) {
                continue;
            }

            $this->cutover_one((int) $item['legacy_id'], (int) $item['design_id']);

            if ($item['type'] === self::LEGACY_LANDING) {
                $redirects[(int) $item['legacy_id']] = (int) $item['design_id'];
            }
        }

        update_option(self::OPTION_REDIRECT_MAP, $redirects, false);
    }

    private function cutover_one(int $legacy_id, int $design_id): void
    {
        $legacy = get_post($legacy_id);
        if (!$legacy instanceof WP_Post) {
            return;
        }

        // Already cut over on a previous run: never overwrite the recorded
        // previous status, or a rollback after a second migration pass would
        // restore the wrong state.
        if (get_post_meta($legacy_id, self::META_PREVIOUS_STATUS, true) !== '') {
            return;
        }

        $previous_status = $legacy->post_status;
        update_post_meta($legacy_id, self::META_PREVIOUS_STATUS, $previous_status);

        $public_status = in_array($previous_status, ['publish', 'draft', 'pending', 'private'], true)
            ? $previous_status
            : 'draft';

        wp_update_post(['ID' => $design_id, 'post_status' => $public_status]);

        if ($previous_status === 'publish') {
            wp_update_post(['ID' => $legacy_id, 'post_status' => 'draft']);
        }
    }

    /**
     * Attach extracted package assets to an existing version.
     *
     * The directory is named after the version id, which only exists once the
     * row has been written, so this is a second step rather than a parameter.
     *
     * A ZIP entry declared on the legacy object with no backing directory on
     * disk is not silently skipped: the design's `entry` meta would point at
     * files that do not exist, which breaks rendering the moment it goes live.
     *
     * @return array{status:string,error:string} status: 'ok'|'skipped'|'failed'
     */
    private function move_package_directory(int $legacy_id, int $design_id): array
    {
        $entry = (string) get_post_meta($legacy_id, '_hub_landing_zip_entry', true);
        if ($entry === '') {
            return self::evaluate_package_copy($legacy_id, $design_id, $entry, '', true, null);
        }

        $importer = HUB_Tibox_Landing_Zip_Importer::instance();
        $source = $importer->get_extract_dir($legacy_id);
        $source_exists = is_dir($source);

        $copied = $source_exists
            ? HUB_Tibox_Filesystem::copy_directory($source, $importer->get_extract_dir($design_id))
            : null;

        $result = self::evaluate_package_copy($legacy_id, $design_id, $entry, $source, $source_exists, $copied);

        if ($result['status'] === 'ok') {
            update_post_meta($design_id, '_hub_landing_zip_folder', (string) $design_id);
        }

        return $result;
    }

    /**
     * Pure classification of a package copy attempt, given its already-known
     * outcome. No WordPress or filesystem calls — this is what "fallo
     * copiando package" is unit tested against.
     *
     * A ZIP entry declared on the legacy object with no backing directory on
     * disk is not silently skipped: the design's `entry` meta would point at
     * files that do not exist, which breaks rendering the moment it goes live.
     *
     * @return array{status:string,error:string} status: 'ok'|'skipped'|'failed'
     */
    public static function evaluate_package_copy(
        int $legacy_id,
        int $design_id,
        string $entry,
        string $source_dir,
        bool $source_exists,
        ?bool $copied
    ): array {
        if ($entry === '') {
            return ['status' => 'skipped', 'error' => ''];
        }

        if (!$source_exists) {
            return [
                'status' => 'failed',
                'error' => sprintf('El package del legacy #%d no se encontró en disco (%s).', $legacy_id, $source_dir),
            ];
        }

        if ($copied !== true) {
            return [
                'status' => 'failed',
                'error' => sprintf('No fue posible copiar el package del legacy #%d al diseño #%d.', $legacy_id, $design_id),
            ];
        }

        return ['status' => 'ok', 'error' => ''];
    }

    /**
     * Creates and publishes the first version for a staged design.
     *
     * Content-free objects are a legitimate migration source (a component
     * nobody ever filled in), so having nothing to seed is not a failure —
     * only a `Version_Store` write that was attempted and did not succeed is.
     *
     * @param array<string,mixed> $data
     * @return array{status:string,error:string} status: 'ok'|'skipped'|'failed'
     */
    private function seed_version(int $design_id, array $data): array
    {
        $html = (string) ($data['html'] ?? '');
        $css = (string) ($data['css'] ?? '');
        $js = (string) ($data['js'] ?? '');
        $entry = (string) ($data['entry'] ?? '');

        if (trim($html . $css . $js) === '' && $entry === '') {
            return ['status' => 'skipped', 'error' => ''];
        }

        $store = HUB_Tibox_Version_Store::instance();
        $version_id = $store->create($design_id, [
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'entry' => $entry,
            'source' => 'migration',
            'label' => (string) ($data['label'] ?? ''),
        ]);

        // Never call publish() when there is nothing to publish: create()
        // returning 0 already means the write failed, and publish() would
        // otherwise be asked to promote a version id that does not exist.
        $published = $version_id > 0 && $store->publish($design_id, $version_id);

        return self::evaluate_version_write($design_id, $version_id, $published);
    }

    /**
     * Pure classification of a `Version_Store` write, given its already-known
     * outcome. No WordPress calls — this is what "fallo creando versión" and
     * "fallo publicando versión" are unit tested against, as the two distinct
     * failure modes they are: `create()` returning `0` is a different problem
     * from `publish()` returning `false` for a version that does exist.
     *
     * @return array{status:string,error:string} status: 'ok'|'failed'
     */
    public static function evaluate_version_write(int $design_id, int $version_id, bool $published): array
    {
        if ($version_id <= 0) {
            return [
                'status' => 'failed',
                'error' => sprintf('No fue posible crear la versión migrada para el diseño #%d.', $design_id),
            ];
        }

        if (!$published) {
            return [
                'status' => 'failed',
                'error' => sprintf('La versión #%d del diseño #%d se creó pero no pudo publicarse.', $version_id, $design_id),
            ];
        }

        return ['status' => 'ok', 'error' => ''];
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

    // -------------------------------------------------------------- rollback

    /**
     * Restores every migrated legacy object to the status it had before
     * cutover, and retires its `hub_design` replacement. Idempotent: called a
     * second time on an already-rolled-back site, it is a no-op because
     * `is_unified()` is already false.
     *
     * @return array{status:string,restored:int,warnings:string[]}
     */
    public function rollback_to_legacy(): array
    {
        if (!self::is_unified()) {
            return ['status' => 'not_unified', 'restored' => 0, 'warnings' => []];
        }

        $restored = 0;
        $warnings = [];

        foreach ($this->migrated_design_ids() as $design_id) {
            $legacy_id = absint(get_post_meta($design_id, HUB_Tibox_Design::META_LEGACY_ID, true));
            if ($legacy_id <= 0) {
                continue;
            }

            $legacy = get_post($legacy_id);
            if (!$legacy instanceof WP_Post) {
                $warnings[] = sprintf('La landing histórica #%d ya no existe; no se pudo restaurar.', $legacy_id);
                continue;
            }

            $previous_status = (string) get_post_meta($legacy_id, self::META_PREVIOUS_STATUS, true);

            if ($previous_status === '') {
                // Never cut over: this design came from a partial migration.
                // There is nothing to restore on the legacy side, but the
                // design must not stay live once the site is back on the
                // historical renderer.
                wp_update_post(['ID' => $design_id, 'post_status' => 'draft']);
                continue;
            }

            wp_update_post(['ID' => $legacy_id, 'post_status' => $previous_status]);
            wp_update_post(['ID' => $design_id, 'post_status' => 'draft']);
            delete_post_meta($legacy_id, self::META_PREVIOUS_STATUS);
            $restored++;
        }

        update_option(self::OPTION_UNIFIED, '0', true);
        update_option(self::OPTION_STATUS, self::STATUS_ROLLED_BACK, true);

        $result = [
            'at' => current_time('mysql'),
            'restored' => $restored,
            'warnings' => $warnings,
        ];
        update_option(self::OPTION_ROLLBACK_RESULT, $result, false);

        return ['status' => self::STATUS_ROLLED_BACK, 'restored' => $restored, 'warnings' => $warnings];
    }

    /** @return int[] */
    private function migrated_design_ids(): array
    {
        $found = get_posts([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_key' => HUB_Tibox_Design::META_LEGACY_ID,
        ]);

        return array_values(array_map('absint', is_array($found) ? $found : []));
    }

    /**
     * Historical landing URLs keep working: the slug moved to the design, so
     * the old permalink 301s to the new object instead of 404ing.
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

    /**
     * A design counts as "already migrated" only once every indispensable
     * staging step succeeded. A row that exists but never reached
     * `META_STAGED` is a leftover from a failed attempt, not a finished
     * migration — see `find_staged_design_id()` for that case.
     */
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
                ['key' => self::META_STAGED, 'value' => '1'],
            ],
        ]);

        return $found === [] ? 0 : (int) $found[0];
    }

    /**
     * Any design row created for this legacy object, complete or not. Used to
     * resume a staged-but-incomplete row on retry instead of inserting a
     * duplicate `hub_design` post every time the same failure is hit.
     */
    private function find_staged_design_id(int $legacy_id, string $legacy_type): int
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

    /**
     * @return array{status:string,created:int,existing:int,missing:int,failed:int,failures:array<int,array<string,mixed>>}
     */
    private function empty_result(string $status): array
    {
        return ['status' => $status, 'created' => 0, 'existing' => 0, 'missing' => 0, 'failed' => 0, 'failures' => []];
    }
}
