<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delivery log for every message Constructor HUB sends.
 *
 * Before this, a `wp_mail()` failure went to `error_log` and the commercial team
 * found out when somebody complained that nobody had called them back. The log
 * makes "the lead was saved but the notification never left" a five second
 * check instead of a support ticket.
 */
final class HUB_Tibox_Mail_Log
{
    private const DB_VERSION = '1.0.0';
    private const DB_VERSION_OPTION = 'hub_tibox_mail_log_db_version';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

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
    }

    public function table_name(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'hub_mail_log';
    }

    public function maybe_install_table(): void
    {
        if (get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return;
        }

        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = $this->table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL DEFAULT 0,
            design_id bigint(20) unsigned NOT NULL DEFAULT 0,
            kind varchar(30) NOT NULL DEFAULT 'internal',
            recipients text NULL,
            subject varchar(255) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'queued',
            error text NULL,
            attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * @param string[] $recipients
     */
    public function record(int $lead_id, int $design_id, string $kind, array $recipients, string $subject): int
    {
        $this->maybe_install_table();

        global $wpdb;
        $now = current_time('mysql');

        $inserted = $wpdb->insert($this->table_name(), [
            'lead_id' => $lead_id,
            'design_id' => $design_id,
            'kind' => sanitize_key($kind),
            'recipients' => implode(', ', array_map('sanitize_email', $recipients)),
            'subject' => sanitize_text_field($subject),
            'status' => self::STATUS_QUEUED,
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === false ? 0 : (int) $wpdb->insert_id;
    }

    public function mark(int $log_id, string $status, string $error = ''): void
    {
        if ($log_id <= 0) {
            return;
        }

        global $wpdb;
        $table = $this->table_name();

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = %s, error = %s, attempts = attempts + 1, updated_at = %s WHERE id = %d",
            $status,
            $error,
            current_time('mysql'),
            $log_id
        ));
    }

    public function failed_count(int $hours = 24): int
    {
        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();
        $since = gmdate('Y-m-d H:i:s', strtotime('-' . max(1, $hours) . ' hours', (int) current_time('timestamp', true)));

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE status = %s AND created_at >= %s",
            self::STATUS_FAILED,
            $since
        ));
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_leads()
            ? HUB_Tibox_Capabilities::MANAGE_LEADS
            : 'manage_options';

        add_submenu_page($parent, 'Entregas de correo', 'Correo enviado', $capability, 'constructor-hub-mail-log', [$this, 'render']);
    }

    public function render(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $this->maybe_install_table();

        global $wpdb;
        $table = $this->table_name();
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 200", ARRAY_A);
        $rows = is_array($rows) ? $rows : [];
        ?>
        <div class="wrap">
            <h1>Entregas de correo</h1>
            <p>
                Constructor HUB entrega a <code>wp_mail()</code>. Lo que aparece aquí es lo que WordPress aceptó o
                rechazó; el transporte real lo decide el mailer configurado en el sitio.
            </p>

            <?php $failed = $this->failed_count(); ?>
            <?php if ($failed > 0) : ?>
                <div class="notice notice-error inline">
                    <p><strong><?php echo esc_html((string) $failed); ?></strong> envíos fallaron en las últimas 24 horas.</p>
                </div>
            <?php endif; ?>

            <?php if ($rows === []) : ?>
                <div class="notice notice-info inline"><p>Todavía no hay envíos registrados.</p></div>
            <?php else : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th style="width:150px;">Fecha</th>
                            <th style="width:90px;">Tipo</th>
                            <th style="width:90px;">Estado</th>
                            <th>Destinatarios</th>
                            <th>Asunto</th>
                            <th>Error</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <tr>
                            <td><?php echo esc_html((string) $row['updated_at']); ?></td>
                            <td><?php echo esc_html($row['kind'] === 'confirmation' ? 'Confirmación' : 'Interno'); ?></td>
                            <td>
                                <?php
                                $labels = [
                                    self::STATUS_SENT => ['Enviado', '#00713c'],
                                    self::STATUS_FAILED => ['Falló', '#b32d2e'],
                                    self::STATUS_QUEUED => ['En cola', '#996800'],
                                ];
                                [$label, $color] = $labels[$row['status']] ?? [(string) $row['status'], '#50575e'];
                                printf('<strong style="color:%s;">%s</strong>', esc_attr($color), esc_html($label));
                                ?>
                            </td>
                            <td><?php echo esc_html((string) $row['recipients']); ?></td>
                            <td><?php echo esc_html((string) $row['subject']); ?></td>
                            <td><?php echo esc_html((string) $row['error']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
