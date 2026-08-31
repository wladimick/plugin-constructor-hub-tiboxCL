<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mail delivery for HUB Landings.
 *
 * Constructor HUB intentionally delegates transport to wp_mail(). If WP Mail
 * SMTP is configured with SendGrid, Microsoft 365, SMTP, etc., those providers
 * continue to be used without storing provider secrets in this plugin.
 */
final class HUB_Tibox_Landing_Mailer
{
    public const OPTION_RECIPIENTS = 'hub_tibox_mail_recipients';
    public const OPTION_CONFIRMATION = 'hub_tibox_mail_confirmation';

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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_post_hub_tibox_save_mail_settings', [$this, 'save_settings']);
        add_action('admin_post_hub_tibox_send_test_mail', [$this, 'send_test_mail']);
    }

    public function add_settings_page(): void
    {
        if (!class_exists('HUB_Tibox_Component_Manager')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'Correo Constructor HUB',
            'Correo',
            'manage_options',
            'constructor-hub-mail',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $recipients = (string) get_option(self::OPTION_RECIPIENTS, get_option('admin_email'));
        $confirmation = get_option(self::OPTION_CONFIRMATION, '1') === '1';
        $mail_status = $this->mail_transport_status();
        ?>
        <div class="wrap">
            <h1>Constructor HUB — Correo</h1>
            <p>
                Constructor HUB usa <code>wp_mail()</code>. El transporte real lo define WordPress o el plugin SMTP instalado.
                No guardes API keys de SendGrid dentro de Constructor HUB.
            </p>

            <div class="notice notice-info inline">
                <p><strong>Transporte detectado:</strong> <?php echo esc_html($mail_status); ?></p>
            </div>

            <?php if (isset($_GET['mail_test'])) : ?>
                <?php $ok = sanitize_key(wp_unslash($_GET['mail_test'])) === 'success'; ?>
                <div class="notice <?php echo $ok ? 'notice-success' : 'notice-error'; ?> inline">
                    <p><?php echo $ok ? 'WordPress aceptó el correo de prueba.' : 'wp_mail() informó un fallo al enviar el correo de prueba.'; ?></p>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_save_mail_settings', 'hub_tibox_mail_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_save_mail_settings">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hub-mail-recipients">Destinatarios por defecto</label></th>
                        <td>
                            <textarea id="hub-mail-recipients" name="hub_mail_recipients" rows="5" class="large-text"><?php echo esc_textarea($recipients); ?></textarea>
                            <p class="description">Un correo por línea o separados por coma. Cada landing puede sobrescribir esta lista.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Confirmación al contacto</th>
                        <td>
                            <label>
                                <input type="checkbox" name="hub_mail_confirmation" value="1" <?php checked($confirmation); ?>>
                                Enviar confirmación al correo ingresado por el lead.
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Guardar configuración de correo'); ?>
            </form>

            <hr>
            <h2>Prueba de entrega</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_send_test_mail', 'hub_tibox_test_mail_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_send_test_mail">
                <p>
                    <label for="hub-test-email"><strong>Enviar a:</strong></label><br>
                    <input id="hub-test-email" type="email" name="hub_test_email" value="<?php echo esc_attr((string) get_option('admin_email')); ?>" class="regular-text" required>
                </p>
                <?php submit_button('Enviar correo de prueba', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('hub_tibox_save_mail_settings', 'hub_tibox_mail_nonce');

        $raw = isset($_POST['hub_mail_recipients'])
            ? sanitize_textarea_field(wp_unslash($_POST['hub_mail_recipients']))
            : '';
        $emails = $this->parse_recipients($raw);

        update_option(self::OPTION_RECIPIENTS, implode("\n", $emails), false);
        update_option(self::OPTION_CONFIRMATION, isset($_POST['hub_mail_confirmation']) ? '1' : '0', false);

        wp_safe_redirect(add_query_arg([
            'post_type' => HUB_Tibox_Component_Manager::POST_TYPE,
            'page' => 'constructor-hub-mail',
            'updated' => '1',
        ], admin_url('edit.php')));
        exit;
    }

    public function send_test_mail(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('hub_tibox_send_test_mail', 'hub_tibox_test_mail_nonce');

        $email = isset($_POST['hub_test_email'])
            ? sanitize_email(wp_unslash($_POST['hub_test_email']))
            : '';

        $ok = $email !== '' && is_email($email) && wp_mail(
            $email,
            'Prueba Constructor HUB — ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            '<p>Este correo fue enviado mediante <strong>wp_mail()</strong> desde Constructor HUB.</p><p>Si WP Mail SMTP usa SendGrid, este mensaje debe viajar por ese transporte.</p>',
            ['Content-Type: text/html; charset=UTF-8']
        );

        wp_safe_redirect(add_query_arg([
            'post_type' => HUB_Tibox_Component_Manager::POST_TYPE,
            'page' => 'constructor-hub-mail',
            'mail_test' => $ok ? 'success' : 'failed',
        ], admin_url('edit.php')));
        exit;
    }

    /**
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $tracking
     */
    public function send_lead_notifications(int $landing_id, int $lead_id, array $fields, array $tracking): void
    {
        $recipients = $this->recipients_for_landing($landing_id);
        if ($recipients !== []) {
            $subject = sprintf(
                '[Constructor HUB] Nuevo lead — %s',
                wp_specialchars_decode(get_the_title($landing_id) ?: 'Landing', ENT_QUOTES)
            );

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $email = sanitize_email((string) ($fields['email'] ?? ''));
            if ($email !== '' && is_email($email)) {
                $name = $this->sanitize_header_value((string) ($fields['name'] ?? 'Contacto'));
                $headers[] = sprintf('Reply-To: %s <%s>', $name !== '' ? $name : 'Contacto', $email);
            }

            $sent = wp_mail(
                $recipients,
                $subject,
                $this->build_internal_body($landing_id, $lead_id, $fields, $tracking),
                $headers
            );

            if (!$sent) {
                error_log(sprintf('[Constructor HUB] wp_mail interno falló. Lead ID: %d', $lead_id));
            }
        }

        if (!$this->confirmation_enabled_for_landing($landing_id)) {
            return;
        }

        $email = sanitize_email((string) ($fields['email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            return;
        }

        $sent = wp_mail(
            $email,
            'Solicitud recibida — ' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $this->build_confirmation_body($landing_id, $fields),
            ['Content-Type: text/html; charset=UTF-8']
        );

        if (!$sent) {
            error_log(sprintf('[Constructor HUB] wp_mail confirmación falló. Lead ID: %d', $lead_id));
        }
    }

    /** @return string[] */
    public function recipients_for_landing(int $landing_id): array
    {
        $raw = '';
        if (class_exists('HUB_Tibox_Landing_Manager')) {
            $raw = HUB_Tibox_Landing_Manager::instance()->get_recipient_emails($landing_id);
        }
        if (trim($raw) === '') {
            $raw = (string) get_option(self::OPTION_RECIPIENTS, get_option('admin_email'));
        }
        return $this->parse_recipients($raw);
    }

    public function confirmation_enabled_for_landing(int $landing_id): bool
    {
        if (class_exists('HUB_Tibox_Landing_Manager')) {
            $override = HUB_Tibox_Landing_Manager::instance()->get_confirmation_override($landing_id);
            if ($override !== null) {
                return $override;
            }
        }
        return get_option(self::OPTION_CONFIRMATION, '1') === '1';
    }

    /** @return string[] */
    private function parse_recipients(string $raw): array
    {
        $parts = preg_split('/[\s,;]+/', $raw) ?: [];
        $emails = [];
        foreach ($parts as $part) {
            $email = sanitize_email(trim((string) $part));
            if ($email !== '' && is_email($email)) {
                $emails[] = strtolower($email);
            }
        }
        return array_values(array_unique($emails));
    }

    private function mail_transport_status(): string
    {
        if (defined('WPMS_PLUGIN_VER') || class_exists('WPMailSMTP\\WP')) {
            return 'WP Mail SMTP detectado; Constructor HUB delegará el transporte a su mailer configurado (por ejemplo SendGrid).';
        }

        return 'wp_mail() de WordPress. No se detectó WP Mail SMTP por sus identificadores habituales.';
    }

    /**
     * @param array<string,mixed> $fields
     * @param array<string,mixed> $tracking
     */
    private function build_internal_body(int $landing_id, int $lead_id, array $fields, array $tracking): string
    {
        $rows = [
            'Lead ID' => (string) $lead_id,
            'Landing' => get_the_title($landing_id),
            'URL' => get_permalink($landing_id),
        ];

        foreach ($fields as $key => $value) {
            if ($key === 'privacy') {
                continue;
            }
            $rows[ucfirst(str_replace('_', ' ', (string) $key))] = is_array($value)
                ? implode(', ', array_map('strval', $value))
                : (string) $value;
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'gclid', 'gbraid', 'wbraid'] as $key) {
            if (!empty($tracking[$key])) {
                $rows[strtoupper($key)] = (string) $tracking[$key];
            }
        }

        $html = '<div style="max-width:760px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">';
        $html .= '<div style="padding:24px;background:#0f172a;color:#fff;"><h1 style="margin:0;font-size:22px;">Nuevo lead — Constructor HUB</h1></div>';
        $html .= '<div style="padding:24px;border:1px solid #e2e8f0;border-top:0;"><table role="presentation" style="width:100%;border-collapse:collapse;">';

        foreach ($rows as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $html .= '<tr><td style="width:180px;padding:9px;border-bottom:1px solid #e2e8f0;font-weight:700;vertical-align:top;">' . esc_html((string) $label) . '</td>';
            $html .= '<td style="padding:9px;border-bottom:1px solid #e2e8f0;vertical-align:top;">' . nl2br(esc_html((string) $value)) . '</td></tr>';
        }

        $html .= '</table></div></div>';
        return $html;
    }

    /** @param array<string,mixed> $fields */
    private function build_confirmation_body(int $landing_id, array $fields): string
    {
        $name = trim((string) ($fields['name'] ?? ''));
        $first_name = $name !== '' ? (preg_split('/\s+/', $name)[0] ?? $name) : '';
        $greeting = $first_name !== '' ? 'Hola ' . esc_html($first_name) . ',' : 'Hola,';
        $site_name = esc_html(get_bloginfo('name'));

        return '<div style="max-width:600px;margin:0 auto;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
            . '<div style="padding:26px 24px;background:#0f172a;color:#fff;"><h1 style="margin:0;font-size:22px;">Solicitud recibida</h1></div>'
            . '<div style="padding:24px;border:1px solid #e2e8f0;border-top:0;">'
            . '<p>' . $greeting . '</p>'
            . '<p>Recibimos correctamente tu solicitud desde <strong>' . esc_html(get_the_title($landing_id)) . '</strong>.</p>'
            . '<p>El equipo de ' . $site_name . ' podrá contactarte utilizando los datos enviados.</p>'
            . '</div></div>';
    }

    private function sanitize_header_value(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }
}
