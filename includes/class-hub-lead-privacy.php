<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Personal data handling for the lead table.
 *
 * Leads live in a custom table, which WordPress knows nothing about: its own
 * export and erase tools would answer "no data found" while the rows sat there.
 * Registering the exporter and the eraser is what makes an access or deletion
 * request answerable without opening the database — required by the GDPR and,
 * in Chile, by Ley 21.719, which is enforceable from December 2026 and treats
 * the RUT as a national identifier.
 */
final class HUB_Tibox_Lead_Privacy
{
    public const CRON_HOOK = 'constructor_hub_purge_leads';

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
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_eraser']);

        add_action('init', [$this, 'schedule_retention']);
        add_action(self::CRON_HOOK, [$this, 'run_retention']);
    }

    /** @param array<string,mixed> $exporters */
    public function register_exporter(array $exporters): array
    {
        $exporters['constructor-hub-leads'] = [
            'exporter_friendly_name' => 'Constructor HUB — formularios',
            'callback' => [$this, 'export'],
        ];

        return $exporters;
    }

    /** @param array<string,mixed> $erasers */
    public function register_eraser(array $erasers): array
    {
        $erasers['constructor-hub-leads'] = [
            'eraser_friendly_name' => 'Constructor HUB — formularios',
            'callback' => [$this, 'erase'],
        ];

        return $erasers;
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,done:bool}
     */
    public function export(string $email, int $page = 1): array
    {
        $items = [];

        foreach (HUB_Tibox_Landing_Lead_Store::instance()->find_by_email($email) as $row) {
            $data = [];

            $labels = [
                'created_at' => 'Fecha',
                'name' => 'Nombre',
                'email' => 'Correo',
                'phone' => 'Teléfono',
                'company' => 'Empresa',
                'rut' => 'RUT',
                'area' => 'Área',
                'message' => 'Mensaje',
                'landing_url' => 'Página de origen',
                'utm_source' => 'UTM source',
                'utm_medium' => 'UTM medium',
                'utm_campaign' => 'UTM campaign',
                'consent_at' => 'Consentimiento otorgado',
                'consent_url' => 'Aviso aceptado',
            ];

            foreach ($labels as $key => $label) {
                $value = (string) ($row[$key] ?? '');
                if ($value === '') {
                    continue;
                }

                $data[] = ['name' => $label, 'value' => $value];
            }

            $items[] = [
                'group_id' => 'constructor-hub-leads',
                'group_label' => 'Formularios de Constructor HUB',
                'item_id' => 'hub-lead-' . (int) $row['id'],
                'data' => $data,
            ];
        }

        return ['data' => $items, 'done' => true];
    }

    /**
     * @return array{items_removed:bool,items_retained:bool,messages:string[],done:bool}
     */
    public function erase(string $email, int $page = 1): array
    {
        $removed = HUB_Tibox_Landing_Lead_Store::instance()->delete_by_email($email);

        return [
            'items_removed' => $removed > 0,
            'items_retained' => false,
            'messages' => $removed > 0
                ? [sprintf('Constructor HUB eliminó %d registro(s) de formulario.', $removed)]
                : [],
            'done' => true,
        ];
    }

    public function schedule_retention(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK)) {
            return;
        }

        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
    }

    public function run_retention(): void
    {
        $deleted = HUB_Tibox_Landing_Lead_Store::instance()->purge_expired();

        if ($deleted > 0) {
            update_option('hub_tibox_last_lead_purge', [
                'at' => current_time('mysql'),
                'deleted' => $deleted,
            ], false);
        }
    }
}
