<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Explicit migration assistant for the historical Tibox WPCode snippets.
 *
 * It never disables WPCode or deletes legacy posts/tables. The goal is to copy
 * data into Constructor HUB, run QA, and only then let an administrator retire
 * the snippets manually.
 */
final class HUB_Tibox_Legacy_Migrator
{
    private const LEGACY_POST_TYPE = 'tibox_landing';

    private static ?self $instance = null;
    private HUB_Tibox_Landing_Manager $landings;
    private HUB_Tibox_Landing_Lead_Store $store;
    private HUB_Tibox_Landing_Zip_Importer $zip;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                HUB_Tibox_Landing_Manager::instance(),
                HUB_Tibox_Landing_Lead_Store::instance(),
                HUB_Tibox_Landing_Zip_Importer::instance()
            );
        }
        return self::$instance;
    }

    private function __construct(
        HUB_Tibox_Landing_Manager $landings,
        HUB_Tibox_Landing_Lead_Store $store,
        HUB_Tibox_Landing_Zip_Importer $zip
    ) {
        $this->landings = $landings;
        $this->store = $store;
        $this->zip = $zip;

        add_action('admin_menu', [$this, 'add_page']);
        add_action('admin_post_hub_tibox_migrate_wpcode', [$this, 'run_migration']);
    }

    public function add_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'Migración WPCode',
            'Migración WPCode',
            'manage_options',
            'constructor-hub-migration',
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $legacy_landings = $this->legacy_landing_ids();
        $legacy_leads = $this->legacy_lead_count();
        $last = get_option('hub_tibox_legacy_last_migration', []);
        $wpcode_classes = [
            'Gestor de Landings' => class_exists('TIBOX_Landing_Manager'),
            'Endpoint / Leads' => class_exists('TIBOX_Landing_Form_API'),
            'Importador ZIP' => class_exists('TIBOX_Landing_Zip_Importer'),
        ];
        ?>
        <div class="wrap">
            <h1>Constructor HUB — Migración desde WPCode</h1>
            <p>
                Esta herramienta copia las landings y leads históricos de los snippets <code>constructor-ia</code> hacia Constructor HUB.
                <strong>No borra la fuente anterior y no desactiva WPCode.</strong>
            </p>

            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th style="width:260px;">Landings antiguas detectadas</th><td><?php echo esc_html((string) count($legacy_landings)); ?></td></tr>
                    <tr><th>Leads tabla antigua</th><td><?php echo esc_html((string) $legacy_leads); ?></td></tr>
                    <tr><th>Tabla antigua</th><td><code><?php echo esc_html($this->store->legacy_table_name()); ?></code> — <?php echo $this->store->legacy_table_exists() ? 'detectada' : 'no detectada'; ?></td></tr>
                </tbody>
            </table>

            <h2>Snippets/clases activos en esta petición</h2>
            <ul>
                <?php foreach ($wpcode_classes as $label => $active) : ?>
                    <li><?php echo esc_html($label); ?>: <strong><?php echo $active ? 'ACTIVO' : 'no detectado'; ?></strong></li>
                <?php endforeach; ?>
            </ul>

            <?php if (is_array($last) && $last !== []) : ?>
                <div class="notice notice-info inline"><p>
                    Última migración: <?php echo esc_html((string) ($last['at'] ?? '')); ?> —
                    landings nuevas: <?php echo esc_html((string) ($last['landings_created'] ?? 0)); ?>,
                    omitidas: <?php echo esc_html((string) ($last['landings_skipped'] ?? 0)); ?>,
                    leads migrados: <?php echo esc_html((string) ($last['leads_migrated'] ?? 0)); ?>.
                </p></div>
            <?php endif; ?>

            <div class="notice notice-warning inline"><p>
                <strong>Importante:</strong> mantén los snippets WPCode activos hasta completar QA de formularios, SendGrid/WP Mail SMTP,
                Google Ads, WebOps y las URLs migradas. Después se desactivan uno a uno.
            </p></div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_migrate_wpcode', 'hub_tibox_migration_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_migrate_wpcode">
                <?php submit_button('Copiar datos desde WPCode a Constructor HUB', 'primary'); ?>
            </form>
        </div>
        <?php
    }

    public function run_migration(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        check_admin_referer('hub_tibox_migrate_wpcode', 'hub_tibox_migration_nonce');

        $created = 0;
        $skipped = 0;
        $package_copies = 0;

        foreach ($this->legacy_landing_ids() as $legacy_id) {
            $existing = $this->find_migrated_landing($legacy_id);
            if ($existing > 0) {
                $skipped++;
                continue;
            }

            $legacy = get_post($legacy_id);
            if (!$legacy instanceof WP_Post) {
                $skipped++;
                continue;
            }

            $new_id = wp_insert_post([
                'post_type' => HUB_Tibox_Landing_Manager::POST_TYPE,
                'post_status' => in_array($legacy->post_status, ['publish', 'draft', 'private', 'pending'], true) ? $legacy->post_status : 'draft',
                'post_title' => $legacy->post_title,
                'post_name' => $legacy->post_name,
                'post_content' => $legacy->post_content,
                'post_excerpt' => $legacy->post_excerpt,
                'post_author' => $legacy->post_author,
            ], true);

            if (is_wp_error($new_id)) {
                $skipped++;
                continue;
            }

            $mode = (string) get_post_meta($legacy_id, '_tibox_lp_mode', true);
            $hub_mode = HUB_Tibox_Landing_Manager::MODE_HUB;
            $html = '';
            $full_html = '';

            if ($mode === 'wordpress') {
                $hub_mode = HUB_Tibox_Landing_Manager::MODE_LEGACY;
                $this->copy_elementor_meta($legacy_id, $new_id);
            } elseif ($mode === 'full_html') {
                $hub_mode = HUB_Tibox_Landing_Manager::MODE_STANDALONE;
                $full_html = (string) get_post_meta($legacy_id, '_tibox_lp_full_html', true);
            } else {
                // Historical Canvas rendered post_content without theme chrome.
                $hub_mode = HUB_Tibox_Landing_Manager::MODE_HUB;
                $html = (string) $legacy->post_content;
            }

            $zip_entry = (string) get_post_meta($legacy_id, '_tibox_lp_zip_entry', true);
            $zip_name = (string) get_post_meta($legacy_id, '_tibox_lp_zip_original_name', true);
            $legacy_package_dir = $this->legacy_package_dir($legacy_id);
            if ($zip_entry !== '' && is_dir($legacy_package_dir)) {
                if ($this->zip->import_existing_directory($new_id, $legacy_package_dir, $zip_entry, $zip_name ?: 'legacy-package.zip')) {
                    $hub_mode = HUB_Tibox_Landing_Manager::MODE_PACKAGE;
                    $package_copies++;
                }
            }

            $this->landings->import_legacy_data($new_id, [
                'legacy_id' => $legacy_id,
                'mode' => $hub_mode,
                'html' => $html,
                'css' => (string) get_post_meta($legacy_id, '_tibox_lp_custom_css', true),
                'js' => (string) get_post_meta($legacy_id, '_tibox_lp_custom_js', true),
                'full_html' => $full_html,
                // Preserve validation behavior of the historical Tibox endpoint.
                'required_fields' => ['name', 'company', 'rut'],
                'success_message' => 'Solicitud recibida. Nuestro equipo gestionará tu requerimiento y te contactará dentro del próximo día hábil.',
                'ads' => [
                    'active' => get_post_meta($legacy_id, '_tibox_lp_ads_active', true) === '1',
                    'campaign_name' => get_post_meta($legacy_id, '_tibox_lp_ads_campaign_name', true),
                    'campaign_id' => get_post_meta($legacy_id, '_tibox_lp_ads_campaign_id', true),
                    'start_date' => get_post_meta($legacy_id, '_tibox_lp_ads_start_date', true),
                    'end_date' => get_post_meta($legacy_id, '_tibox_lp_ads_end_date', true),
                    'final_url' => get_post_meta($legacy_id, '_tibox_lp_ads_final_url', true),
                    'notes' => get_post_meta($legacy_id, '_tibox_lp_ads_notes', true),
                ],
            ]);

            $thumbnail_id = get_post_thumbnail_id($legacy_id);
            if ($thumbnail_id > 0) {
                set_post_thumbnail($new_id, $thumbnail_id);
            }

            $created++;
        }

        $lead_result = $this->store->migrate_legacy_leads();

        $result = [
            'at' => current_time('mysql'),
            'landings_created' => $created,
            'landings_skipped' => $skipped,
            'packages_copied' => $package_copies,
            'leads_migrated' => $lead_result['migrated'],
            'leads_skipped' => $lead_result['skipped'],
            'leads_total' => $lead_result['total'],
        ];
        update_option('hub_tibox_legacy_last_migration', $result, false);

        wp_safe_redirect(add_query_arg([
            'post_type' => HUB_Tibox_Component_Manager::POST_TYPE,
            'page' => 'constructor-hub-migration',
            'migrated' => '1',
        ], admin_url('edit.php')));
        exit;
    }

    /** @return int[] */
    private function legacy_landing_ids(): array
    {
        global $wpdb;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' ORDER BY ID ASC",
            self::LEGACY_POST_TYPE
        ));
        return array_values(array_map('absint', is_array($ids) ? $ids : []));
    }

    private function legacy_lead_count(): int
    {
        if (!$this->store->legacy_table_exists()) {
            return 0;
        }
        global $wpdb;
        $table = $this->store->legacy_table_name();
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }

    private function find_migrated_landing(int $legacy_id): int
    {
        $posts = get_posts([
            'post_type' => HUB_Tibox_Landing_Manager::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_hub_legacy_landing_id',
            'meta_value' => $legacy_id,
        ]);
        return !empty($posts) ? absint($posts[0]) : 0;
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

    private function legacy_package_dir(int $legacy_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . 'tibox-landings/' . $legacy_id;
    }

}
