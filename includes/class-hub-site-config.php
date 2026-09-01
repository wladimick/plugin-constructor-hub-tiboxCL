<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Multi site operation: diagnostics and configuration transfer.
 *
 * Constructor HUB is meant to run on tibox.cl, prodata.cl and future clients
 * from the same core. That only holds if setting up a new site is a matter of
 * importing configuration, not of reading someone else's install and copying
 * settings by hand.
 */
final class HUB_Tibox_Site_Config
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
        // `constructor_hub_admin_menu` only fires once the site is unified; a
        // partial migration or a rollback both leave the site un-unified, and
        // that is exactly when an administrator most needs to reach this page
        // — to retry, or to confirm a rollback took effect. The Tools fallback
        // keeps it reachable either way.
        add_action('constructor_hub_admin_menu', [$this, 'register_page']);
        add_action('admin_menu', [$this, 'register_legacy_page']);
        add_action('admin_post_hub_tibox_export_config', [$this, 'export']);
        add_action('admin_post_hub_tibox_import_config', [$this, 'import']);
        add_action('admin_post_hub_tibox_retry_unification', [$this, 'retry_unification']);
        add_action('admin_post_hub_tibox_rollback_unification', [$this, 'rollback_unification']);
    }

    public function register_legacy_page(): void
    {
        if (HUB_Tibox_Upgrade::is_unified()) {
            // The unified menu already added this page under Constructor HUB.
            return;
        }

        $capability = HUB_Tibox_Capabilities::can_manage_settings() ? HUB_Tibox_Capabilities::MANAGE_SETTINGS : 'manage_options';
        add_management_page('Diagnóstico Constructor HUB', 'Constructor HUB', $capability, 'constructor-hub-diagnostics', [$this, 'render']);
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_settings()
            ? HUB_Tibox_Capabilities::MANAGE_SETTINGS
            : 'manage_options';

        add_submenu_page($parent, 'Diagnóstico', 'Diagnóstico', $capability, 'constructor-hub-diagnostics', [$this, 'render']);
    }

    /**
     * Portable configuration. Never includes content, leads or credentials.
     *
     * @return array<string,mixed>
     */
    public function export_payload(): array
    {
        return [
            'hub_config' => 1,
            'exported_at' => current_time('mysql'),
            'source' => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
            'plugin_version' => TIBOX_AI_FRONTEND_VERSION,
            'design_system' => HUB_Tibox_Design_System::tokens(),
            'regions' => array_map(
                static function (string $region): array {
                    $config = HUB_Tibox_Regions::config($region);
                    // Design ids are local to a site; the slug travels instead.
                    $config['design_slug'] = $config['design'] > 0 ? (string) get_post_field('post_name', $config['design']) : '';
                    unset($config['design'], $config['targets']);

                    return $config;
                },
                array_combine(HUB_Tibox_Regions::names(), HUB_Tibox_Regions::names())
            ),
            'settings' => [
                'client_ip_header' => (string) get_option(HUB_Tibox_Landing_Forms::OPTION_IP_HEADER, ''),
                'asset_optimizer' => get_option(HUB_Tibox_Asset_Optimizer::OPTION, '0'),
                'elementor_design_support' => get_option('hub_tibox_elementor_design_support', '0'),
                'lead_retention_months' => (int) get_option('hub_tibox_lead_retention_months', 0),
                'ads_conversion_name' => (string) get_option('hub_tibox_ads_conversion_name', ''),
                'ads_currency' => (string) get_option('hub_tibox_ads_currency', 'CLP'),
                'mail_confirmation' => get_option(HUB_Tibox_Landing_Mailer::OPTION_CONFIRMATION, '1'),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return string[] Human readable report of what was applied.
     */
    public function apply(array $payload): array
    {
        $applied = [];

        if ((int) ($payload['hub_config'] ?? 0) !== 1) {
            return ['El archivo no es una configuración de Constructor HUB.'];
        }

        if (is_array($payload['design_system'] ?? null)) {
            $tokens = [];
            foreach (HUB_Tibox_Design_System::schema() as $key => $definition) {
                if (!isset($payload['design_system'][$key])) {
                    continue;
                }

                $value = sanitize_text_field((string) $payload['design_system'][$key]);
                $tokens[$key] = str_replace(['{', '}', ';', '<', '>'], '', $value);
            }

            update_option(HUB_Tibox_Design_System::OPTION, $tokens, true);
            $applied[] = sprintf('Design System: %d tokens aplicados.', count($tokens));
        }

        if (is_array($payload['regions'] ?? null)) {
            foreach (HUB_Tibox_Regions::names() as $region) {
                $incoming = $payload['regions'][$region] ?? null;
                if (!is_array($incoming)) {
                    continue;
                }

                $slug = sanitize_title((string) ($incoming['design_slug'] ?? ''));
                $design_id = $slug !== '' ? HUB_Tibox_Design::resolve($slug, $region) : 0;

                HUB_Tibox_Regions::save($region, [
                    'mode' => sanitize_key((string) ($incoming['mode'] ?? HUB_Tibox_Regions::MODE_THEME)),
                    'design' => $design_id,
                    'scope' => sanitize_key((string) ($incoming['scope'] ?? HUB_Tibox_Regions::SCOPE_ALL)),
                    'targets' => [],
                    'hide_selector' => (string) ($incoming['hide_selector'] ?? ''),
                ]);

                $applied[] = $design_id > 0
                    ? sprintf('Región %s configurada con el diseño "%s".', $region, $slug)
                    : sprintf('Región %s configurada; falta importar el diseño "%s".', $region, $slug !== '' ? $slug : '—');
            }
        }

        $settings = is_array($payload['settings'] ?? null) ? $payload['settings'] : [];
        if ($settings !== []) {
            $map = [
                'client_ip_header' => HUB_Tibox_Landing_Forms::OPTION_IP_HEADER,
                'asset_optimizer' => HUB_Tibox_Asset_Optimizer::OPTION,
                'elementor_design_support' => 'hub_tibox_elementor_design_support',
                'lead_retention_months' => 'hub_tibox_lead_retention_months',
                'ads_conversion_name' => 'hub_tibox_ads_conversion_name',
                'ads_currency' => 'hub_tibox_ads_currency',
                'mail_confirmation' => HUB_Tibox_Landing_Mailer::OPTION_CONFIRMATION,
            ];

            foreach ($map as $key => $option) {
                if (!isset($settings[$key])) {
                    continue;
                }

                update_option($option, sanitize_text_field((string) $settings[$key]), false);
            }

            $applied[] = 'Ajustes generales aplicados.';
        }

        // Recipients are deliberately not transferred: sending another client's
        // leads to this client's inbox is the exact failure the audit found in
        // the hardcoded Tibox addresses.
        $applied[] = 'Destinatarios de correo NO importados: se configuran por sitio.';

        return $applied;
    }

    /**
     * Compatibility checks worth knowing before trusting a site to HUB.
     *
     * @return array<int,array{label:string,status:string,detail:string}>
     */
    public function diagnostics(): array
    {
        $checks = [];

        $uploads = wp_upload_dir();
        $writable = !empty($uploads['basedir']) && wp_is_writable((string) $uploads['basedir']);
        $checks[] = [
            'label' => 'Escritura en uploads',
            'status' => $writable ? 'ok' : 'warn',
            'detail' => $writable
                ? 'Los assets compilados y los packages se pueden almacenar.'
                : 'Sin escritura, el CSS y el JS se imprimen inline y no se pueden importar packages.',
        ];

        $checks[] = [
            'label' => 'Extensión ZipArchive',
            'status' => class_exists('ZipArchive') ? 'ok' : 'warn',
            'detail' => class_exists('ZipArchive')
                ? 'Importación y exportación de packages disponibles.'
                : 'Sin ZipArchive no se pueden importar ni exportar Design Packages.',
        ];

        $cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $checks[] = [
            'label' => 'WP-Cron',
            'status' => $cron_disabled ? 'warn' : 'ok',
            'detail' => $cron_disabled
                ? 'Deshabilitado: el correo se envía en línea y la retención de leads necesita un cron externo.'
                : 'Activo: los correos se encolan y la retención se ejecuta a diario.',
        ];

        $mailer = defined('WPMS_PLUGIN_VER') || class_exists('WPMailSMTP\\WP');
        $checks[] = [
            'label' => 'Transporte de correo',
            'status' => $mailer ? 'ok' : 'warn',
            'detail' => $mailer
                ? 'WP Mail SMTP detectado; wp_mail() usará el proveedor configurado allí.'
                : 'No se detectó WP Mail SMTP. wp_mail() usará el correo del servidor, con entregabilidad incierta.',
        ];

        $elementor = HUB_Tibox_Elementor_Adapter::is_active();
        $checks[] = [
            'label' => 'Elementor',
            'status' => 'info',
            'detail' => $elementor
                ? ('Activo' . (HUB_Tibox_Elementor_Adapter::is_pro_active() ? ' (Pro)' : '') . '. El adaptador está disponible.')
                : 'No detectado. El core no lo necesita.',
        ];

        $seo = defined('RANK_MATH_VERSION') || defined('WPSEO_VERSION') || defined('SEOPRESS_VERSION');
        $checks[] = [
            'label' => 'Plugin SEO',
            'status' => 'info',
            'detail' => $seo
                ? 'Detectado. Constructor HUB conserva wp_head() en todos sus modos, incluido el documento completo.'
                : 'No detectado. Constructor HUB no sustituye a un plugin SEO.',
        ];

        $failed_mail = HUB_Tibox_Mail_Log::instance()->failed_count();
        $checks[] = [
            'label' => 'Entregas de correo (24 h)',
            'status' => $failed_mail > 0 ? 'warn' : 'ok',
            'detail' => $failed_mail > 0
                ? sprintf('%d envíos fallidos. Revisa Correo enviado.', $failed_mail)
                : 'Sin fallos registrados.',
        ];

        $unified = HUB_Tibox_Upgrade::is_unified();
        $status = HUB_Tibox_Upgrade::status();
        $status_detail = [
            HUB_Tibox_Upgrade::STATUS_COMPLETE => 'Unificado en hub_design, con versionado y rollback.',
            HUB_Tibox_Upgrade::STATUS_PARTIAL => 'Migración parcial: algunos elementos no se migraron. Revisa la sección de migración más abajo antes de continuar.',
            HUB_Tibox_Upgrade::STATUS_ROLLED_BACK => 'Revertido a los post types históricos por acción explícita de un administrador.',
            HUB_Tibox_Upgrade::STATUS_PENDING => 'Todavía en los post types históricos: sin versionado ni rollback.',
        ];
        $checks[] = [
            'label' => 'Modelo de diseños',
            'status' => $unified ? 'ok' : ($status === HUB_Tibox_Upgrade::STATUS_PARTIAL ? 'warn' : 'info'),
            'detail' => $status_detail[$status] ?? $status_detail[HUB_Tibox_Upgrade::STATUS_PENDING],
        ];

        return $checks;
    }

    /**
     * Migration status, with the two actions that change it: retrying a
     * partial migration, or rolling a completed one back to the historical
     * post types.
     *
     * Both are gated on `manage_options` explicitly, stricter than the usual
     * `hub_manage_settings`: retrying writes new content, and rollback flips
     * public status on every migrated object.
     */
    private function render_unification_panel(): void
    {
        $status = HUB_Tibox_Upgrade::status();
        $notice = isset($_GET['hub_notice']) ? sanitize_key(wp_unslash($_GET['hub_notice'])) : '';
        ?>
        <?php if ($notice === 'unification_retried') : ?>
            <div class="notice notice-info is-dismissible"><p>Migración reintentada. Revisa el resultado abajo.</p></div>
        <?php elseif ($notice === 'unification_rolled_back') : ?>
            <div class="notice notice-success is-dismissible"><p>Sitio revertido a los post types históricos.</p></div>
        <?php endif; ?>

        <?php if ($status === HUB_Tibox_Upgrade::STATUS_PARTIAL) : ?>
            <?php $result = get_option(HUB_Tibox_Upgrade::OPTION_RESULT, []); ?>
            <div class="notice notice-warning">
                <p>
                    <strong>Migración parcial.</strong>
                    Creados: <?php echo esc_html((string) ($result['created'] ?? 0)); ?>,
                    ya existentes: <?php echo esc_html((string) ($result['existing'] ?? 0)); ?>,
                    <strong>fallidos: <?php echo esc_html((string) ($result['failed'] ?? 0)); ?></strong>.
                    Constructor HUB no activa el modelo unificado mientras haya fallos: el sitio sigue funcionando
                    exactamente como antes de intentar migrar.
                </p>
                <?php if (!empty($result['failures']) && is_array($result['failures'])) : ?>
                    <table class="widefat striped" style="max-width:760px;margin-bottom:12px;">
                        <thead><tr><th>Tipo</th><th>ID histórico</th><th>Error</th></tr></thead>
                        <tbody>
                        <?php foreach ($result['failures'] as $failure) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($failure['type'] ?? '')); ?></td>
                                <td><code>#<?php echo esc_html((string) ($failure['legacy_id'] ?? '')); ?></code></td>
                                <td><?php echo esc_html((string) ($failure['error'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                <?php if (current_user_can('manage_options')) : ?>
                    <p>
                        <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(
                            add_query_arg('action', 'hub_tibox_retry_unification', admin_url('admin-post.php')),
                            'hub_tibox_retry_unification'
                        )); ?>">Reintentar migración</a>
                    </p>
                <?php endif; ?>
            </div>
        <?php elseif (HUB_Tibox_Upgrade::is_unified() && current_user_can('manage_options')) : ?>
            <details style="margin-bottom:20px;">
                <summary style="cursor:pointer;font-weight:600;">Revertir a los post types históricos</summary>
                <div style="padding:14px 0 0;">
                    <p>
                        Restaura el estado publicado que tenían los componentes y landings históricos antes de la
                        migración, y retira sus reemplazos <code>hub_design</code> a borrador. No borra ningún dato:
                        los diseños y sus versiones quedan intactos y se puede volver a migrar más adelante.
                    </p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <?php wp_nonce_field('hub_tibox_rollback_unification', 'hub_tibox_rollback_nonce'); ?>
                        <input type="hidden" name="action" value="hub_tibox_rollback_unification">
                        <label style="display:block;margin-bottom:10px;">
                            <input type="checkbox" name="hub_confirm_rollback" value="1" required>
                            Entiendo que esto revierte el modelo de diseños de este sitio a los post types históricos.
                        </label>
                        <?php submit_button('Revertir a legacy', 'delete'); ?>
                    </form>
                </div>
            </details>
        <?php endif; ?>
        <?php
    }

    public function retry_unification(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_retry_unification');

        HUB_Tibox_Upgrade::instance()->retry_migration();

        wp_safe_redirect(add_query_arg([
            'page' => 'constructor-hub-diagnostics',
            'hub_notice' => 'unification_retried',
        ], admin_url('admin.php')));
        exit;
    }

    public function rollback_unification(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_rollback_unification', 'hub_tibox_rollback_nonce');

        if (empty($_POST['hub_confirm_rollback'])) {
            wp_die(esc_html__('Confirma la casilla antes de revertir.', 'constructor-hub-tibox'), '', ['back_link' => true]);
        }

        HUB_Tibox_Upgrade::instance()->rollback_to_legacy();

        wp_safe_redirect(add_query_arg([
            'page' => 'constructor-hub-diagnostics',
            'hub_notice' => 'unification_rolled_back',
        ], admin_url('admin.php')));
        exit;
    }

    public function render(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $report = get_transient('hub_config_import_' . get_current_user_id());
        delete_transient('hub_config_import_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1>Diagnóstico y configuración</h1>

            <?php $this->render_unification_panel(); ?>

            <h2>Compatibilidad de este sitio</h2>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                <?php foreach ($this->diagnostics() as $check) : ?>
                    <tr>
                        <th style="width:230px;"><?php echo esc_html($check['label']); ?></th>
                        <td style="width:90px;">
                            <?php
                            $colors = ['ok' => '#00713c', 'warn' => '#b26200', 'info' => '#50575e'];
                            $labels = ['ok' => 'OK', 'warn' => 'Atención', 'info' => 'Info'];
                            printf(
                                '<strong style="color:%s;">%s</strong>',
                                esc_attr($colors[$check['status']] ?? '#50575e'),
                                esc_html($labels[$check['status']] ?? '')
                            );
                            ?>
                        </td>
                        <td><?php echo esc_html($check['detail']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Versiones</h2>
            <table class="widefat striped" style="max-width:900px;">
                <tbody>
                    <tr><th style="width:230px;">Constructor HUB</th><td><code><?php echo esc_html(TIBOX_AI_FRONTEND_VERSION); ?></code></td></tr>
                    <tr><th>WordPress</th><td><code><?php echo esc_html(get_bloginfo('version')); ?></code></td></tr>
                    <tr><th>PHP</th><td><code><?php echo esc_html(PHP_VERSION); ?></code></td></tr>
                    <tr><th>Theme activo</th><td><code><?php echo esc_html((string) wp_get_theme()->get('Name')); ?></code></td></tr>
                    <tr><th>Contrato de packages</th><td><code><?php echo esc_html((string) HUB_Tibox_Package::CONTRACT); ?></code></td></tr>
                    <tr><th>Contrato de variables</th><td><code><?php echo esc_html(HUB_Tibox_Variables::CONTRACT_VERSION); ?></code></td></tr>
                </tbody>
            </table>

            <hr>
            <h2>Llevar esta configuración a otro sitio</h2>
            <p>
                Exporta tokens, regiones y ajustes generales. <strong>No incluye contenido, leads ni destinatarios de
                correo</strong>: enviar los leads de un cliente al buzón de otro es exactamente el fallo que la
                auditoría encontró en los correos codificados en el core.
            </p>

            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'hub_tibox_export_config', admin_url('admin-post.php')), 'hub_tibox_export_config')); ?>">
                    Descargar configuración
                </a>
            </p>

            <?php if (is_array($report) && $report !== []) : ?>
                <div class="notice notice-success">
                    <p><strong>Configuración importada:</strong></p>
                    <ul style="list-style:disc;margin-left:20px;">
                        <?php foreach ($report as $line) : ?>
                            <li><?php echo esc_html((string) $line); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_import_config', 'hub_tibox_config_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_import_config">
                <p>
                    <label for="hub-config-import"><strong>Pegar configuración</strong></label><br>
                    <textarea id="hub-config-import" name="hub_config" rows="8" class="large-text code"></textarea>
                </p>
                <?php submit_button('Importar configuración', 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    public function export(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_export_config');

        $host = (string) wp_parse_url(home_url('/'), PHP_URL_HOST);
        $filename = sanitize_file_name($host . '-constructor-hub-config.json');

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download body, not HTML.
        echo (string) wp_json_encode($this->export_payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function import(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_import_config', 'hub_tibox_config_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; every value is sanitised in apply().
        $raw = isset($_POST['hub_config']) ? trim((string) wp_unslash($_POST['hub_config'])) : '';
        $decoded = json_decode($raw, true);

        $report = is_array($decoded)
            ? $this->apply($decoded)
            : ['La configuración pegada no es JSON válido.'];

        set_transient('hub_config_import_' . get_current_user_id(), $report, 120);

        wp_safe_redirect(add_query_arg('page', 'constructor-hub-diagnostics', admin_url('admin.php')));
        exit;
    }
}
