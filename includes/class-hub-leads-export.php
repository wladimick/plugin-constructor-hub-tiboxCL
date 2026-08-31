<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lead exports, including Google Ads offline conversions.
 *
 * The plugin already stored `gclid`, `gbraid` and `wbraid`, which is the hard
 * part. What was missing is the step that turns a stored click id into money:
 * marking a lead as qualified or won and handing Google Ads the CSV it expects.
 * Without it, Ads optimises towards form submissions rather than towards leads
 * that became customers.
 */
final class HUB_Tibox_Leads_Export
{
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
        add_action('admin_post_hub_tibox_export_leads', [$this, 'export_leads']);
        add_action('admin_post_hub_tibox_export_conversions', [$this, 'export_conversions']);
        add_action('admin_post_hub_tibox_update_lead', [$this, 'update_lead']);
        add_action('admin_post_hub_tibox_delete_lead', [$this, 'delete_lead']);
    }

    public function export_leads(): void
    {
        if (!HUB_Tibox_Capabilities::can_export_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_export_leads');

        $landing_id = isset($_GET['landing_id']) ? absint($_GET['landing_id']) : 0;
        $rows = HUB_Tibox_Landing_Lead_Store::instance()->query([
            'landing_id' => $landing_id,
            'limit' => 5000,
        ]);

        $columns = [
            'id', 'created_at', 'landing_id', 'form_id', 'name', 'email', 'phone', 'company',
            'rut', 'area', 'users', 'message', 'landing_url', 'utm_source', 'utm_medium',
            'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid',
            'consent_at', 'consent_url', 'conversion_status', 'conversion_value',
        ];

        $this->stream_csv('leads', $columns, array_map(
            static function (array $row) use ($columns): array {
                $line = [];
                foreach ($columns as $column) {
                    $line[] = (string) ($row[$column] ?? '');
                }

                return $line;
            },
            $rows
        ));
    }

    /**
     * Google Ads offline conversion import format.
     *
     * Only leads that carry a click id and were marked as qualified or won are
     * exported, and each one is stamped so a second export does not double count
     * the same conversion.
     */
    public function export_conversions(): void
    {
        if (!HUB_Tibox_Capabilities::can_export_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_export_conversions');

        $store = HUB_Tibox_Landing_Lead_Store::instance();
        $conversion_name = (string) get_option('hub_tibox_ads_conversion_name', 'Lead calificado HUB');
        $currency = (string) get_option('hub_tibox_ads_currency', 'CLP');

        $lines = [];

        foreach (['qualified', 'won'] as $status) {
            foreach ($store->query(['conversion_status' => $status, 'with_gclid' => true, 'limit' => 5000]) as $row) {
                if ((string) ($row['conversion_exported_at'] ?? '') !== '') {
                    continue;
                }

                $click_id = '';
                $column = 'Google Click ID';
                foreach (['gclid' => 'Google Click ID', 'gbraid' => 'GBRAID', 'wbraid' => 'WBRAID'] as $key => $label) {
                    if ((string) ($row[$key] ?? '') !== '') {
                        $click_id = (string) $row[$key];
                        $column = $label;
                        break;
                    }
                }

                if ($click_id === '') {
                    continue;
                }

                $lines[] = [
                    $column,
                    $click_id,
                    $conversion_name,
                    // Google Ads expects the click time zone offset explicitly.
                    gmdate('Y-m-d H:i:sO', strtotime((string) $row['created_at'])),
                    (string) ($row['conversion_value'] ?? ''),
                    $currency,
                ];

                $store->mark_conversion_exported((int) $row['id']);
            }
        }

        $this->stream_csv(
            'google-ads-conversions',
            ['Click Id Type', 'Click Id', 'Conversion Name', 'Conversion Time', 'Conversion Value', 'Conversion Currency'],
            $lines
        );
    }

    public function update_lead(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        check_admin_referer('hub_tibox_update_lead_' . $lead_id);

        $status = isset($_POST['conversion_status']) ? sanitize_key(wp_unslash($_POST['conversion_status'])) : 'new';
        $value = isset($_POST['conversion_value']) && $_POST['conversion_value'] !== ''
            ? (float) sanitize_text_field(wp_unslash($_POST['conversion_value']))
            : null;

        HUB_Tibox_Landing_Lead_Store::instance()->set_conversion_status($lead_id, $status, $value);

        wp_safe_redirect(wp_get_referer() ?: admin_url('admin.php?page=constructor-hub-leads'));
        exit;
    }

    public function delete_lead(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $lead_id = isset($_GET['lead_id']) ? absint($_GET['lead_id']) : 0;
        check_admin_referer('hub_tibox_delete_lead_' . $lead_id);

        HUB_Tibox_Landing_Lead_Store::instance()->delete($lead_id);

        wp_safe_redirect(add_query_arg(
            ['page' => 'constructor-hub-leads', 'hub_notice' => 'lead_deleted'],
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * @param string[]              $headers
     * @param array<int,string[]>   $rows
     */
    private function stream_csv(string $name, array $headers, array $rows): void
    {
        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $filename = sanitize_file_name(sprintf('%s-%s-%s.csv', $host, $name, gmdate('Ymd-His')));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        if ($output === false) {
            exit;
        }

        // Excel on Windows needs the BOM to read UTF-8 accents correctly, and
        // this file is opened in Excel far more often than anywhere else.
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_map([$this, 'neutralise_formula'], $row));
        }

        fclose($output);
        exit;
    }

    /**
     * A visitor controls the message field. A value starting with `=` becomes a
     * live formula when the CSV is opened in a spreadsheet.
     */
    private function neutralise_formula(string $value): string
    {
        return $value !== '' && str_contains("=+-@\t\r", $value[0]) ? "'" . $value : $value;
    }
}
