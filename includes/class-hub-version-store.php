<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Immutable version history for HUB designs.
 *
 * The visual code used to live in post meta, which WordPress revisions do not
 * cover: publishing a change destroyed the previous one with no way back. That
 * is unacceptable on a URL with a live Google Ads campaign. Each save creates a
 * row here; publishing moves a pointer; rollback moves it back.
 */
final class HUB_Tibox_Version_Store
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_LIVE = 'live';
    public const STATUS_ARCHIVED = 'archived';

    private const DB_VERSION = '1.0.0';
    private const DB_VERSION_OPTION = 'hub_tibox_versions_db_version';

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
    }

    public function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'hub_design_versions';
    }

    public function install_table(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            design_id bigint(20) unsigned NOT NULL,
            version int(10) unsigned NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'draft',
            label varchar(190) NOT NULL DEFAULT '',
            source varchar(40) NOT NULL DEFAULT 'editor',
            html longtext NULL,
            css longtext NULL,
            js longtext NULL,
            manifest longtext NULL,
            asset_dir varchar(190) NOT NULL DEFAULT '',
            entry varchar(255) NOT NULL DEFAULT '',
            checksum char(64) NOT NULL DEFAULT '',
            author_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY design_version (design_id, version),
            KEY design_status (design_id, status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public function maybe_install_table(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        $this->install_table();
    }

    /**
     * Store a new immutable version. Never overwrites a published one.
     *
     * @param array<string,mixed> $data
     */
    public function create(int $design_id, array $data): int
    {
        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();

        $html = (string) ($data['html'] ?? '');
        $css = (string) ($data['css'] ?? '');
        $js = (string) ($data['js'] ?? '');
        $manifest = $data['manifest'] ?? null;
        $manifest_json = is_array($manifest) ? (string) wp_json_encode($manifest) : (string) ($manifest ?? '');

        $checksum = hash('sha256', $html . "\0" . $css . "\0" . $js . "\0" . $manifest_json);

        // An identical payload is not a new version: re-saving the editor
        // without touching the code should not grow the history.
        $latest = $this->latest($design_id);
        if ($latest !== null && $latest['checksum'] === $checksum && (string) $latest['entry'] === (string) ($data['entry'] ?? '')) {
            return (int) $latest['id'];
        }

        $version = $this->next_version_number($design_id);

        $inserted = $wpdb->insert($table, [
            'design_id' => $design_id,
            'version' => $version,
            'status' => self::STATUS_DRAFT,
            'label' => sanitize_text_field((string) ($data['label'] ?? '')),
            'source' => sanitize_key((string) ($data['source'] ?? 'editor')),
            'html' => $html,
            'css' => $css,
            'js' => $js,
            'manifest' => $manifest_json,
            'asset_dir' => sanitize_text_field((string) ($data['asset_dir'] ?? '')),
            'entry' => sanitize_text_field((string) ($data['entry'] ?? '')),
            'checksum' => $checksum,
            'author_id' => get_current_user_id(),
            'created_at' => current_time('mysql'),
        ]);

        if ($inserted === false) {
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Make one version the published one. Returns false when it does not belong
     * to the design.
     */
    public function publish(int $design_id, int $version_id): bool
    {
        $version = $this->get($version_id);
        if ($version === null || (int) $version['design_id'] !== $design_id) {
            return false;
        }

        global $wpdb;
        $table = $this->table_name();

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s WHERE design_id = %d AND status = %s",
            self::STATUS_ARCHIVED,
            $design_id,
            self::STATUS_LIVE
        ));

        $wpdb->update($table, ['status' => self::STATUS_LIVE], ['id' => $version_id]);
        update_post_meta($design_id, HUB_Tibox_Design::META_CURRENT_VERSION, $version_id);

        /**
         * Fired after a design version becomes the published one. The asset
         * compiler listens here to write the CSS/JS files.
         */
        do_action('constructor_hub_design_published', $design_id, $version_id, $version);

        return true;
    }

    /** Rollback is publishing an older version: the history is never rewritten. */
    public function rollback(int $design_id, int $version_id): bool
    {
        return $this->publish($design_id, $version_id);
    }

    /** @return array<string,mixed>|null */
    public function get(int $version_id): ?array
    {
        if ($version_id <= 0) {
            return null;
        }

        global $wpdb;
        $table = $this->table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $version_id),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function get_live(int $design_id): ?array
    {
        $pointer = (int) get_post_meta($design_id, HUB_Tibox_Design::META_CURRENT_VERSION, true);
        if ($pointer > 0) {
            $version = $this->get($pointer);
            if ($version !== null && (int) $version['design_id'] === $design_id) {
                return $version;
            }
        }

        global $wpdb;
        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE design_id = %d AND status = %s ORDER BY version DESC LIMIT 1",
            $design_id,
            self::STATUS_LIVE
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function latest(int $design_id): ?array
    {
        global $wpdb;
        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE design_id = %d ORDER BY version DESC LIMIT 1",
            $design_id
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * Version used by the editor: the newest draft, or the live one when the
     * design has never been edited since publishing.
     *
     * @return array<string,mixed>|null
     */
    public function get_working(int $design_id): ?array
    {
        $latest = $this->latest($design_id);
        return $latest ?? $this->get_live($design_id);
    }

    /** @return array<int,array<string,mixed>> */
    public function history(int $design_id, int $limit = 30): array
    {
        global $wpdb;
        $table = $this->table_name();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, design_id, version, status, label, source, entry, checksum, author_id, created_at,
                    CHAR_LENGTH(COALESCE(html, '')) AS html_length,
                    CHAR_LENGTH(COALESCE(css, '')) AS css_length,
                    CHAR_LENGTH(COALESCE(js, '')) AS js_length
             FROM {$table} WHERE design_id = %d ORDER BY version DESC LIMIT %d",
            $design_id,
            max(1, $limit)
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    public function delete_for_design(int $design_id): void
    {
        global $wpdb;
        $wpdb->delete($this->table_name(), ['design_id' => $design_id], ['%d']);
    }

    private function next_version_number(int $design_id): int
    {
        global $wpdb;
        $table = $this->table_name();
        $max = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version) FROM {$table} WHERE design_id = %d",
            $design_id
        ));

        return $max + 1;
    }
}
