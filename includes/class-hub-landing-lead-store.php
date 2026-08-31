<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Dedicated storage for Constructor HUB landing leads.
 *
 * A custom table keeps campaign/form submissions out of wp_posts/wp_postmeta and
 * makes filtering/exporting predictable. The legacy WPCode table is never
 * deleted by this class; migration is explicit and idempotent.
 */
final class HUB_Tibox_Landing_Lead_Store
{
    private const DB_VERSION = '3.0.0';
    private const DB_VERSION_OPTION = 'hub_tibox_landing_leads_db_version';

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
        add_action('init', [$this, 'maybe_install_table'], 20);
    }

    public function table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'hub_landing_leads';
    }

    public function legacy_table_name(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'tibox_landing_leads';
    }

    public function maybe_install_table(): void
    {
        if (
            get_option(self::DB_VERSION_OPTION) === self::DB_VERSION &&
            $this->table_exists($this->table_name())
        ) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            legacy_id bigint(20) unsigned NOT NULL DEFAULT 0,
            submission_id varchar(100) NOT NULL,
            landing_id bigint(20) unsigned NOT NULL DEFAULT 0,
            legacy_landing_id bigint(20) unsigned NOT NULL DEFAULT 0,
            form_id varchar(190) NOT NULL DEFAULT '',
            source_key varchar(100) NOT NULL DEFAULT 'constructor_hub',
            created_at datetime NOT NULL,
            name varchar(190) NOT NULL DEFAULT '',
            email varchar(190) NOT NULL DEFAULT '',
            phone varchar(100) NOT NULL DEFAULT '',
            company varchar(190) NOT NULL DEFAULT '',
            rut varchar(60) NOT NULL DEFAULT '',
            area varchar(190) NOT NULL DEFAULT '',
            users varchar(100) NOT NULL DEFAULT '',
            message text NULL,
            privacy tinyint(1) unsigned NOT NULL DEFAULT 0,
            landing_url text NULL,
            landing_path varchar(255) NOT NULL DEFAULT '',
            page_title text NULL,
            utm_source varchar(190) NOT NULL DEFAULT '',
            utm_medium varchar(190) NOT NULL DEFAULT '',
            utm_campaign varchar(190) NOT NULL DEFAULT '',
            utm_term varchar(255) NOT NULL DEFAULT '',
            utm_content varchar(255) NOT NULL DEFAULT '',
            gclid varchar(255) NOT NULL DEFAULT '',
            gbraid varchar(255) NOT NULL DEFAULT '',
            wbraid varchar(255) NOT NULL DEFAULT '',
            fields_json longtext NULL,
            tracking_json longtext NULL,
            consent_at datetime NULL,
            consent_url varchar(255) NOT NULL DEFAULT '',
            consent_version varchar(60) NOT NULL DEFAULT '',
            ip_hash char(64) NOT NULL DEFAULT '',
            conversion_status varchar(30) NOT NULL DEFAULT 'new',
            conversion_value decimal(12,2) NULL,
            conversion_exported_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY submission_id (submission_id),
            KEY landing_id (landing_id),
            KEY legacy_landing_id (legacy_landing_id),
            KEY email (email),
            KEY created_at (created_at),
            KEY conversion_status (conversion_status)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    public function table_exists(string $table): bool
    {
        global $wpdb;
        $found = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))
        );
        return $found === $table;
    }

    public function legacy_table_exists(): bool
    {
        return $this->table_exists($this->legacy_table_name());
    }

    public function find_by_submission_id(string $submission_id): int
    {
        global $wpdb;
        $table = $this->table_name();
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE submission_id = %s LIMIT 1",
                $submission_id
            )
        );
        return $id === null ? 0 : (int) $id;
    }

    /**
     * @param array<string,mixed> $data
     */
    public function insert(array $data): int
    {
        $this->maybe_install_table();

        $submission_id = sanitize_text_field((string) ($data['submission_id'] ?? ''));
        if ($submission_id === '') {
            $submission_id = wp_generate_uuid4();
        }

        $existing = $this->find_by_submission_id($submission_id);
        if ($existing > 0) {
            return $existing;
        }

        global $wpdb;
        $table = $this->table_name();

        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        $tracking = is_array($data['tracking'] ?? null) ? $data['tracking'] : [];

        $row = [
            'legacy_id' => absint($data['legacy_id'] ?? 0),
            'submission_id' => $submission_id,
            'landing_id' => absint($data['landing_id'] ?? 0),
            'legacy_landing_id' => absint($data['legacy_landing_id'] ?? 0),
            'form_id' => sanitize_key((string) ($data['form_id'] ?? 'hub-landing-form')),
            'source_key' => sanitize_key((string) ($data['source_key'] ?? 'constructor_hub')),
            'created_at' => sanitize_text_field((string) ($data['created_at'] ?? current_time('mysql'))),
            'name' => sanitize_text_field((string) ($fields['name'] ?? $data['name'] ?? '')),
            'email' => sanitize_email((string) ($fields['email'] ?? $data['email'] ?? '')),
            'phone' => sanitize_text_field((string) ($fields['phone'] ?? $data['phone'] ?? '')),
            'company' => sanitize_text_field((string) ($fields['company'] ?? $data['company'] ?? '')),
            'rut' => sanitize_text_field((string) ($fields['rut'] ?? $data['rut'] ?? '')),
            'area' => sanitize_text_field((string) ($fields['area'] ?? $data['area'] ?? '')),
            'users' => sanitize_text_field((string) ($fields['users'] ?? $data['users'] ?? '')),
            'message' => sanitize_textarea_field((string) ($fields['message'] ?? $data['message'] ?? '')),
            'privacy' => !empty($fields['privacy']) || !empty($data['privacy']) ? 1 : 0,
            'landing_url' => esc_url_raw((string) ($tracking['landing_url'] ?? $data['landing_url'] ?? '')),
            'landing_path' => sanitize_text_field((string) ($tracking['landing_path'] ?? $data['landing_path'] ?? '')),
            'page_title' => sanitize_text_field((string) ($tracking['page_title'] ?? $data['page_title'] ?? '')),
            'utm_source' => sanitize_text_field((string) ($tracking['utm_source'] ?? $data['utm_source'] ?? '')),
            'utm_medium' => sanitize_text_field((string) ($tracking['utm_medium'] ?? $data['utm_medium'] ?? '')),
            'utm_campaign' => sanitize_text_field((string) ($tracking['utm_campaign'] ?? $data['utm_campaign'] ?? '')),
            'utm_term' => sanitize_text_field((string) ($tracking['utm_term'] ?? $data['utm_term'] ?? '')),
            'utm_content' => sanitize_text_field((string) ($tracking['utm_content'] ?? $data['utm_content'] ?? '')),
            'gclid' => sanitize_text_field((string) ($tracking['gclid'] ?? $data['gclid'] ?? '')),
            'gbraid' => sanitize_text_field((string) ($tracking['gbraid'] ?? $data['gbraid'] ?? '')),
            'wbraid' => sanitize_text_field((string) ($tracking['wbraid'] ?? $data['wbraid'] ?? '')),
            'fields_json' => wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'tracking_json' => wp_json_encode($tracking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            // Consent evidence. Chile's Ley 21.719 and the GDPR both require
            // being able to show *what* was accepted and *when*, not just a
            // boolean. The IP is stored hashed: it proves origin without
            // keeping an identifier in the clear.
            'consent_at' => !empty($fields['privacy']) || !empty($data['privacy'])
                ? sanitize_text_field((string) ($data['consent_at'] ?? current_time('mysql')))
                : null,
            'consent_url' => esc_url_raw((string) ($data['consent_url'] ?? '')),
            'consent_version' => sanitize_text_field((string) ($data['consent_version'] ?? '')),
            'ip_hash' => (string) ($data['ip_hash'] ?? ''),
            'conversion_status' => sanitize_key((string) ($data['conversion_status'] ?? 'new')),
        ];

        // The unique index on submission_id is the real guard: two concurrent
        // requests carrying the same id must both end up reporting success,
        // never a 500 that makes the visitor submit again.
        $inserted = $wpdb->insert($table, $row);
        if ($inserted === false) {
            $existing = $this->find_by_submission_id($submission_id);
            if ($existing > 0) {
                return $existing;
            }

            error_log('[Constructor HUB] Error guardando lead: ' . $wpdb->last_error);
            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * One-way copy from the historical WPCode table. The old table is left
     * untouched. Re-running is safe because submission_id is unique.
     *
     * @return array{migrated:int,skipped:int,total:int}
     */
    public function migrate_legacy_leads(): array
    {
        $this->maybe_install_table();

        if (!$this->legacy_table_exists()) {
            return ['migrated' => 0, 'skipped' => 0, 'total' => 0];
        }

        global $wpdb;
        $legacy = $this->legacy_table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$legacy} ORDER BY id ASC", ARRAY_A);
        $rows = is_array($rows) ? $rows : [];

        $migrated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $submission_id = sanitize_text_field((string) ($row['submission_id'] ?? ''));
            if ($submission_id === '') {
                $submission_id = 'legacy-' . absint($row['id'] ?? 0);
            }

            if ($this->find_by_submission_id($submission_id) > 0) {
                $skipped++;
                continue;
            }

            $legacy_landing_id = absint($row['landing_id'] ?? 0);
            $hub_landing_id = $this->resolve_migrated_landing_id($legacy_landing_id);

            $lead_id = $this->insert([
                'legacy_id' => absint($row['id'] ?? 0),
                'submission_id' => $submission_id,
                'landing_id' => $hub_landing_id,
                'legacy_landing_id' => $legacy_landing_id,
                'form_id' => $row['form_id'] ?? '',
                'source_key' => $row['source_key'] ?? 'legacy_wpcode',
                'created_at' => $row['created_at'] ?? current_time('mysql'),
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'phone' => $row['phone'] ?? '',
                'company' => $row['company'] ?? '',
                'rut' => $row['rut'] ?? '',
                'area' => $row['area'] ?? '',
                'users' => $row['users'] ?? '',
                'message' => $row['message'] ?? '',
                'privacy' => !empty($row['privacy']),
                'landing_url' => $row['landing_url'] ?? '',
                'landing_path' => $row['landing_path'] ?? '',
                'page_title' => $row['page_title'] ?? '',
                'utm_source' => $row['utm_source'] ?? '',
                'utm_medium' => $row['utm_medium'] ?? '',
                'utm_campaign' => $row['utm_campaign'] ?? '',
                'utm_term' => $row['utm_term'] ?? '',
                'utm_content' => $row['utm_content'] ?? '',
                'gclid' => $row['gclid'] ?? '',
                'gbraid' => $row['gbraid'] ?? '',
                'wbraid' => $row['wbraid'] ?? '',
            ]);

            if ($lead_id > 0) {
                $migrated++;
            } else {
                $skipped++;
            }
        }

        update_option('hub_tibox_legacy_leads_last_migration', [
            'at' => current_time('mysql'),
            'migrated' => $migrated,
            'skipped' => $skipped,
            'total' => count($rows),
        ], false);

        return ['migrated' => $migrated, 'skipped' => $skipped, 'total' => count($rows)];
    }


    /**
     * Rows for the admin listing and for exports.
     *
     * @param array<string,mixed> $args
     * @return array<int,array<string,mixed>>
     */
    public function query(array $args = []): array
    {
        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();

        $where = ['1=1'];
        $params = [];

        $landing_id = absint($args['landing_id'] ?? 0);
        if ($landing_id > 0) {
            $where[] = 'landing_id = %d';
            $params[] = $landing_id;
        }

        $status = sanitize_key((string) ($args['conversion_status'] ?? ''));
        if ($status !== '') {
            $where[] = 'conversion_status = %s';
            $params[] = $status;
        }

        if (!empty($args['with_gclid'])) {
            $where[] = "(gclid <> '' OR gbraid <> '' OR wbraid <> '')";
        }

        $from = sanitize_text_field((string) ($args['from'] ?? ''));
        if ($from !== '') {
            $where[] = 'created_at >= %s';
            $params[] = $from;
        }

        $limit = (int) ($args['limit'] ?? 50);
        $limit = $limit > 0 ? min($limit, 5000) : 50;
        $offset = max(0, (int) ($args['offset'] ?? 0));

        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is assembled from literal fragments only; every value is a placeholder filled by prepare().
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,mixed> $args */
    public function count(array $args = []): int
    {
        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();

        $where = ['1=1'];
        $params = [];

        $landing_id = absint($args['landing_id'] ?? 0);
        if ($landing_id > 0) {
            $where[] = 'landing_id = %d';
            $params[] = $landing_id;
        }

        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode(' AND ', $where);

        if ($params === []) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fully literal statement, no values involved.
            return (int) $wpdb->get_var($sql);
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is assembled from literal fragments only; every value is a placeholder filled by prepare().
        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /** @return array<string,mixed>|null */
    public function get(int $lead_id): ?array
    {
        global $wpdb;
        $table = $this->table_name();
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $lead_id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function delete(int $lead_id): bool
    {
        global $wpdb;

        return $wpdb->delete($this->table_name(), ['id' => $lead_id], ['%d']) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public function find_by_email(string $email): array
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return [];
        }

        global $wpdb;
        $table = $this->table_name();
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s ORDER BY id ASC", $email),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    public function delete_by_email(string $email): int
    {
        $email = sanitize_email($email);
        if ($email === '') {
            return 0;
        }

        global $wpdb;

        return (int) $wpdb->delete($this->table_name(), ['email' => $email], ['%s']);
    }

    /**
     * Marks a lead as exported to Google Ads so it is not counted twice.
     */
    public function mark_conversion_exported(int $lead_id): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table_name(),
            ['conversion_exported_at' => current_time('mysql')],
            ['id' => $lead_id],
            ['%s'],
            ['%d']
        );
    }

    public function set_conversion_status(int $lead_id, string $status, ?float $value = null): void
    {
        $allowed = ['new', 'qualified', 'won', 'lost'];
        $status = in_array($status, $allowed, true) ? $status : 'new';

        $data = ['conversion_status' => $status];
        $format = ['%s'];

        if ($value !== null) {
            $data['conversion_value'] = $value;
            $format[] = '%f';
        }

        global $wpdb;
        $wpdb->update($this->table_name(), $data, ['id' => $lead_id], $format, ['%d']);
    }

    /**
     * Deletes leads older than the retention window.
     *
     * Keeping personal data forever because nobody chose a limit is itself a
     * decision, and the wrong one. Retention is off by default and configured
     * in months.
     */
    public function purge_expired(): int
    {
        $months = (int) apply_filters(
            'constructor_hub_lead_retention_months',
            (int) get_option('hub_tibox_lead_retention_months', 0)
        );

        if ($months <= 0) {
            return 0;
        }

        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . $months . ' months', (int) current_time('timestamp', true)));

        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created_at < %s",
            $cutoff
        ));
    }

    private function resolve_migrated_landing_id(int $legacy_landing_id): int
    {
        if ($legacy_landing_id <= 0 || !class_exists('HUB_Tibox_Landing_Manager')) {
            return 0;
        }

        $posts = get_posts([
            'post_type' => HUB_Tibox_Landing_Manager::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_hub_legacy_landing_id',
            'meta_value' => (string) $legacy_landing_id,
        ]);

        return !empty($posts) ? absint($posts[0]) : 0;
    }
}
