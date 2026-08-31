<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Region configuration and general settings.
 */
final class HUB_Tibox_Settings_Page
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
        add_action('constructor_hub_admin_menu', [$this, 'register_pages']);
        add_action('admin_post_hub_tibox_save_regions', [$this, 'save_regions']);
        add_action('admin_post_hub_tibox_save_settings', [$this, 'save_settings']);
    }

    public function register_pages(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_settings()
            ? HUB_Tibox_Capabilities::MANAGE_SETTINGS
            : 'manage_options';

        add_submenu_page($parent, 'Regiones', 'Regiones', $capability, 'constructor-hub-regions', [$this, 'render_regions']);
        add_submenu_page($parent, 'Configuración', 'Configuración', $capability, 'constructor-hub-settings', [$this, 'render_settings']);
    }

    // ---------------------------------------------------------------- regions

    public function render_regions(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }
        ?>
        <div class="wrap">
            <h1>Regiones Header y Footer</h1>
            <p>
                Cada región se migra por separado. No hace falta tener Header y Footer HUB listos a la vez para
                empezar.
            </p>

            <div class="notice notice-info inline">
                <p><strong>Inyectado</strong> conserva la plantilla del theme y coloca el diseño HUB mediante los hooks
                de WordPress. Es el modo seguro sobre un theme desconocido y el que permite migrar una sola región.</p>
                <p><strong>Reemplazo total</strong> entrega el documento completo a Constructor HUB. Úsalo cuando ambas
                regiones sean HUB: una región en modo theme no se imprime en este modo.</p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_save_regions', 'hub_tibox_regions_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_save_regions">

                <?php foreach (HUB_Tibox_Regions::names() as $region) : ?>
                    <?php
                    $config = HUB_Tibox_Regions::config($region);
                    $designs = HUB_Tibox_Design::list_by_type($region);
                    ?>
                    <h2><?php echo esc_html(ucfirst($region)); ?></h2>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row">Modo</th>
                            <td>
                                <?php
                                $modes = [
                                    HUB_Tibox_Regions::MODE_THEME => 'Theme actual (sin cambios)',
                                    HUB_Tibox_Regions::MODE_INJECT => 'HUB inyectado sobre la plantilla del theme',
                                    HUB_Tibox_Regions::MODE_REPLACE => 'HUB reemplaza el documento completo',
                                ];
                                foreach ($modes as $value => $label) :
                                    ?>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="radio" name="hub_region[<?php echo esc_attr($region); ?>][mode]" value="<?php echo esc_attr($value); ?>" <?php checked($config['mode'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hub-region-design-<?php echo esc_attr($region); ?>">Diseño</label></th>
                            <td>
                                <select id="hub-region-design-<?php echo esc_attr($region); ?>" name="hub_region[<?php echo esc_attr($region); ?>][design]">
                                    <option value="0">— Seleccionar —</option>
                                    <?php foreach ($designs as $design) : ?>
                                        <option value="<?php echo esc_attr((string) $design->ID); ?>" <?php selected($config['design'], $design->ID); ?>>
                                            <?php echo esc_html($design->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($designs === []) : ?>
                                    <p class="description">
                                        No hay diseños de tipo <?php echo esc_html($region); ?> publicados.
                                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . HUB_Tibox_Design::POST_TYPE . '&hub_type=' . $region)); ?>">Crear uno</a>.
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Alcance</th>
                            <td>
                                <?php
                                $scopes = [
                                    HUB_Tibox_Regions::SCOPE_ALL => 'Todo el sitio',
                                    HUB_Tibox_Regions::SCOPE_SELECTED => 'Solo los contenidos indicados',
                                    HUB_Tibox_Regions::SCOPE_EXCEPT => 'Todo el sitio excepto los indicados',
                                ];
                                foreach ($scopes as $value => $label) :
                                    ?>
                                    <label style="display:block;margin-bottom:6px;">
                                        <input type="radio" name="hub_region[<?php echo esc_attr($region); ?>][scope]" value="<?php echo esc_attr($value); ?>" <?php checked($config['scope'], $value); ?>>
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p>
                                    <label for="hub-region-targets-<?php echo esc_attr($region); ?>">IDs de contenido, separados por coma</label><br>
                                    <input id="hub-region-targets-<?php echo esc_attr($region); ?>" class="regular-text"
                                           name="hub_region[<?php echo esc_attr($region); ?>][targets]"
                                           value="<?php echo esc_attr(implode(', ', $config['targets'])); ?>">
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="hub-region-hide-<?php echo esc_attr($region); ?>">Ocultar la región del theme</label></th>
                            <td>
                                <input id="hub-region-hide-<?php echo esc_attr($region); ?>" class="large-text"
                                       name="hub_region[<?php echo esc_attr($region); ?>][hide_selector]"
                                       value="<?php echo esc_attr($config['hide_selector']); ?>"
                                       placeholder="<?php echo esc_attr($region === 'header' ? 'header.site-header, .elementor-location-header' : 'footer.site-footer, .elementor-location-footer'); ?>">
                                <p class="description">
                                    Selector CSS del <?php echo esc_html($region); ?> que imprime el theme. Solo se usa en modo
                                    inyectado, y es específico de cada theme: Constructor HUB nunca lo adivina.
                                </p>
                            </td>
                        </tr>
                    </table>
                <?php endforeach; ?>

                <?php submit_button('Guardar regiones'); ?>
            </form>
        </div>
        <?php
    }

    public function save_regions(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_save_regions', 'hub_tibox_regions_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every leaf is sanitised individually in the loop below.
        $input = isset($_POST['hub_region']) ? (array) wp_unslash($_POST['hub_region']) : [];

        foreach (HUB_Tibox_Regions::names() as $region) {
            $config = isset($input[$region]) && is_array($input[$region]) ? $input[$region] : [];
            $targets = array_filter(array_map('absint', explode(',', (string) ($config['targets'] ?? ''))));

            HUB_Tibox_Regions::save($region, [
                'mode' => sanitize_key((string) ($config['mode'] ?? HUB_Tibox_Regions::MODE_THEME)),
                'design' => absint($config['design'] ?? 0),
                'scope' => sanitize_key((string) ($config['scope'] ?? HUB_Tibox_Regions::SCOPE_ALL)),
                'targets' => $targets,
                'hide_selector' => (string) ($config['hide_selector'] ?? ''),
            ]);
        }

        wp_safe_redirect(add_query_arg(['page' => 'constructor-hub-regions', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    // --------------------------------------------------------------- settings

    public function render_settings(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $detected = [];
        foreach ([
            'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP',
            'HTTP_TRUE_CLIENT_IP' => 'True-Client-IP',
            'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For',
            'HTTP_X_REAL_IP' => 'X-Real-IP',
        ] as $key => $label) {
            if (!empty($_SERVER[$key])) {
                $detected[] = $label;
            }
        }
        ?>
        <div class="wrap">
            <h1>Configuración de Constructor HUB</h1>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_save_settings', 'hub_tibox_settings_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_save_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hub-ip-header">Cabecera de IP del cliente</label></th>
                        <td>
                            <input id="hub-ip-header" name="hub_client_ip_header" class="regular-text"
                                   value="<?php echo esc_attr((string) get_option(HUB_Tibox_Landing_Forms::OPTION_IP_HEADER, '')); ?>"
                                   placeholder="CF-Connecting-IP">
                            <p class="description">
                                Necesaria solo si el sitio está detrás de Cloudflare, un balanceador o un WAF. Sin ella,
                                el límite de envíos cuenta a todos los visitantes como uno solo.
                                <?php if ($detected !== []) : ?>
                                    <br><strong>Presentes en esta petición:</strong>
                                    <code><?php echo esc_html(implode(', ', $detected)); ?></code>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Elementor</th>
                        <td>
                            <label>
                                <input type="checkbox" name="hub_elementor_design_support" value="1" <?php checked(get_option('hub_tibox_elementor_design_support', '0'), '1'); ?>>
                                Permitir que Elementor edite los diseños HUB.
                            </label>
                            <p class="description">
                                Desactivado por defecto. Constructor HUB no modifica la configuración de Elementor sin
                                autorización explícita.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar configuración'); ?>
            </form>

            <hr>
            <h2>Capacidades</h2>
            <p>Constructor HUB usa capacidades propias en lugar de exigir rol de administrador para todo.</p>
            <table class="widefat striped" style="max-width:760px;">
                <thead><tr><th>Capacidad</th><th>Permite</th></tr></thead>
                <tbody>
                    <tr><td><code>hub_manage_designs</code></td><td>Ver el panel y los listados de diseños.</td></tr>
                    <tr><td><code>hub_edit_design_code</code></td><td>Editar HTML, CSS y JavaScript de un diseño.</td></tr>
                    <tr><td><code>hub_manage_leads</code></td><td>Consultar los leads de formularios.</td></tr>
                    <tr><td><code>hub_export_leads</code></td><td>Exportar leads y conversiones.</td></tr>
                    <tr><td><code>hub_manage_settings</code></td><td>Cambiar regiones, correo e integraciones.</td></tr>
                </tbody>
            </table>
            <p class="description">
                El reparto por rol se ajusta con el filtro <code>constructor_hub_role_capabilities</code>.
            </p>
        </div>
        <?php
    }

    public function save_settings(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_save_settings', 'hub_tibox_settings_nonce');

        $header = isset($_POST['hub_client_ip_header'])
            ? preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['hub_client_ip_header'])))
            : '';

        update_option(HUB_Tibox_Landing_Forms::OPTION_IP_HEADER, (string) $header, false);
        update_option('hub_tibox_elementor_design_support', isset($_POST['hub_elementor_design_support']) ? '1' : '0', false);

        wp_safe_redirect(add_query_arg(['page' => 'constructor-hub-settings', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }
}
