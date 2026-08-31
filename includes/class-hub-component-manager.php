<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generic Header/Footer component registry for Constructor HUB Tibox.
 *
 * Components are stored as a private WordPress post type. Visual code is split
 * into HTML, CSS and JavaScript so AI-generated components can be pasted and
 * versioned without putting PHP inside the visual package.
 */
final class HUB_Tibox_Component_Manager
{
    public const POST_TYPE = 'hub_component';

    private const META_TYPE = '_hub_component_type';
    private const META_HTML = '_hub_component_html';
    private const META_CSS = '_hub_component_css';
    private const META_JS = '_hub_component_js';

    public const OPTION_HEADER = 'hub_tibox_active_header';
    public const OPTION_FOOTER = 'hub_tibox_active_footer';
    public const OPTION_HYBRID_ENABLED = 'hub_tibox_hybrid_enabled';
    public const OPTION_HYBRID_SCOPE = 'hub_tibox_hybrid_scope';
    public const OPTION_HYBRID_PAGES = 'hub_tibox_hybrid_pages';

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
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_component']);

        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_post_hub_tibox_save_components_settings', [$this, 'save_settings']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Componentes HUB',
                'singular_name' => 'Componente HUB',
                'add_new' => 'Nuevo componente',
                'add_new_item' => 'Crear componente HUB',
                'edit_item' => 'Editar componente HUB',
                'new_item' => 'Nuevo componente HUB',
                'view_item' => 'Ver componente HUB',
                'search_items' => 'Buscar componentes HUB',
                'not_found' => 'No hay componentes HUB.',
                'menu_name' => 'Constructor HUB',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'supports' => ['title'],
            'menu_icon' => 'dashicons-layout',
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'hub-component-settings',
            'Tipo de componente',
            [$this, 'render_type_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'hub-component-code',
            'Código visual',
            [$this, 'render_code_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_type_meta_box(WP_Post $post): void
    {
        wp_nonce_field('hub_tibox_save_component', 'hub_tibox_component_nonce');
        $type = $this->get_component_type($post->ID);
        ?>
        <p>
            <label for="hub-component-type"><strong>Responsabilidad</strong></label>
        </p>
        <select id="hub-component-type" name="hub_component_type" style="width:100%;">
            <option value="header" <?php selected($type, 'header'); ?>>Header</option>
            <option value="footer" <?php selected($type, 'footer'); ?>>Footer</option>
        </select>
        <p style="font-size:12px;color:#646970;">
            El componente debe contener solo el fragmento de Header o Footer. No incluir
            <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code> ni <code>&lt;body&gt;</code>.
        </p>
        <?php
    }

    public function render_code_meta_box(WP_Post $post): void
    {
        $html = (string) get_post_meta($post->ID, self::META_HTML, true);
        $css = (string) get_post_meta($post->ID, self::META_CSS, true);
        $js = (string) get_post_meta($post->ID, self::META_JS, true);
        ?>
        <p>
            <strong>HTML</strong><br>
            <textarea name="hub_component_html" rows="18" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($html); ?></textarea>
        </p>
        <p style="color:#646970;">
            Variables disponibles inicialmente: <code>{{SITE_URL}}</code>, <code>{{HOME_URL}}</code>,
            <code>{{SITE_NAME}}</code>, <code>{{CURRENT_YEAR}}</code>, <code>{{CUSTOM_LOGO}}</code>,
            <code>{{CUSTOM_LOGO_URL}}</code>, <code>{{MENU_PRIMARY}}</code> y <code>{{MENU_FOOTER}}</code>.
        </p>
        <p>
            <strong>CSS</strong><br>
            <textarea name="hub_component_css" rows="18" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($css); ?></textarea>
        </p>
        <p>
            <strong>JavaScript</strong><br>
            <textarea name="hub_component_js" rows="14" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($js); ?></textarea>
        </p>
        <p style="font-size:12px;color:#646970;">
            No pegar etiquetas <code>&lt;style&gt;</code> o <code>&lt;script&gt;</code>. Constructor HUB las carga por separado.
            Nunca guardar API keys, tokens o secretos aquí.
        </p>
        <?php
    }

    public function save_component(int $post_id): void
    {
        if (!isset($_POST['hub_tibox_component_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST['hub_tibox_component_nonce']));
        if (!wp_verify_nonce($nonce, 'hub_tibox_save_component')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $type = isset($_POST['hub_component_type'])
            ? sanitize_key(wp_unslash($_POST['hub_component_type']))
            : 'header';

        if (!in_array($type, ['header', 'footer'], true)) {
            $type = 'header';
        }

        update_post_meta($post_id, self::META_TYPE, $type);

        // Visual code is restricted to roles holding the HUB code capability.
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            HUB_Tibox_Capabilities::flag_code_not_saved();
            return;
        }

        $html = isset($_POST['hub_component_html']) ? wp_unslash($_POST['hub_component_html']) : '';
        $css = isset($_POST['hub_component_css']) ? wp_unslash($_POST['hub_component_css']) : '';
        $js = isset($_POST['hub_component_js']) ? wp_unslash($_POST['hub_component_js']) : '';

        update_post_meta($post_id, self::META_HTML, $html);
        update_post_meta($post_id, self::META_CSS, $css);
        update_post_meta($post_id, self::META_JS, $js);
    }

    public function add_settings_page(): void
    {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            'Configuración Constructor HUB',
            'Configuración',
            'manage_options',
            'constructor-hub-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $header = absint(get_option(self::OPTION_HEADER, 0));
        $footer = absint(get_option(self::OPTION_FOOTER, 0));
        $enabled = get_option(self::OPTION_HYBRID_ENABLED, '0') === '1';
        $scope = (string) get_option(self::OPTION_HYBRID_SCOPE, 'selected');
        $selected_pages = array_map('absint', (array) get_option(self::OPTION_HYBRID_PAGES, []));

        $headers = $this->get_components('header');
        $footers = $this->get_components('footer');
        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
            'post_status' => ['publish', 'private', 'draft'],
        ]);
        ?>
        <div class="wrap">
            <h1>Constructor HUB Tibox</h1>
            <p>Etapa actual: Header/Footer HUB en modo híbrido para páginas WordPress existentes.</p>

            <div class="notice notice-warning inline">
                <p><strong>Transición segura:</strong> activa este renderer solo cuando ya tengas Header y Footer HUB listos. La sustitución de una sola región manteniendo la otra del theme se implementará mediante adaptadores Legacy en una fase posterior.</p>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_save_components_settings', 'hub_tibox_settings_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_save_components_settings">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hub-active-header">Header activo</label></th>
                        <td>
                            <select id="hub-active-header" name="hub_active_header">
                                <option value="0">— Seleccionar —</option>
                                <?php foreach ($headers as $component) : ?>
                                    <option value="<?php echo esc_attr((string) $component->ID); ?>" <?php selected($header, $component->ID); ?>>
                                        <?php echo esc_html($component->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hub-active-footer">Footer activo</label></th>
                        <td>
                            <select id="hub-active-footer" name="hub_active_footer">
                                <option value="0">— Seleccionar —</option>
                                <?php foreach ($footers as $component) : ?>
                                    <option value="<?php echo esc_attr((string) $component->ID); ?>" <?php selected($footer, $component->ID); ?>>
                                        <?php echo esc_html($component->post_title); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Modo híbrido</th>
                        <td>
                            <label>
                                <input type="checkbox" name="hub_hybrid_enabled" value="1" <?php checked($enabled); ?>>
                                Activar renderer híbrido cuando el alcance coincida.
                            </label>
                            <p class="description">Requiere Header y Footer publicados y seleccionados.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Alcance</th>
                        <td>
                            <label style="display:block;margin-bottom:8px;">
                                <input type="radio" name="hub_hybrid_scope" value="selected" <?php checked($scope, 'selected'); ?>>
                                Solo páginas seleccionadas
                            </label>
                            <label style="display:block;">
                                <input type="radio" name="hub_hybrid_scope" value="all_pages" <?php checked($scope, 'all_pages'); ?>>
                                Todas las páginas WordPress
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hub-ip-header">Cabecera de IP del cliente</label></th>
                        <td>
                            <input id="hub-ip-header" name="hub_client_ip_header" class="regular-text" value="<?php echo esc_attr((string) get_option(HUB_Tibox_Landing_Forms::OPTION_IP_HEADER, '')); ?>" placeholder="CF-Connecting-IP">
                            <p class="description">
                                Solo si el sitio está detrás de Cloudflare, un balanceador o un WAF. Sin este valor el límite de envíos
                                usa la IP del proxy y afecta a todos los visitantes por igual.
                                <?php
                                $hub_detected = [];
                                foreach (['HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP', 'HTTP_TRUE_CLIENT_IP' => 'True-Client-IP', 'HTTP_X_FORWARDED_FOR' => 'X-Forwarded-For', 'HTTP_X_REAL_IP' => 'X-Real-IP'] as $hub_key => $hub_label) {
                                    if (!empty($_SERVER[$hub_key])) {
                                        $hub_detected[] = $hub_label;
                                    }
                                }
                                if ($hub_detected !== []) {
                                    echo '<br><strong>Detectadas en esta petición:</strong> <code>' . esc_html(implode('</code>, <code>', $hub_detected)) . '</code>';
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Elementor en Landings</th>
                        <td>
                            <label>
                                <input type="checkbox" name="hub_elementor_landing_support" value="1" <?php checked(get_option('hub_tibox_elementor_landing_support', '0'), '1'); ?>>
                                Permitir que Elementor edite el CPT de Landings.
                            </label>
                            <p class="description">Desactivado por defecto: el core no modifica la configuración de Elementor sin autorización explícita.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hub-hybrid-pages">Páginas de prueba/seleccionadas</label></th>
                        <td>
                            <select id="hub-hybrid-pages" name="hub_hybrid_pages[]" multiple size="12" style="min-width:360px;max-width:100%;">
                                <?php foreach ($pages as $page) : ?>
                                    <option value="<?php echo esc_attr((string) $page->ID); ?>" <?php selected(in_array($page->ID, $selected_pages, true)); ?>>
                                        <?php echo esc_html($page->post_title . ' — #' . $page->ID . ' (' . $page->post_status . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Usar este alcance primero para QA. En v0.3 el renderer híbrido se limita a Pages, no posts/archives.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar configuración HUB'); ?>
            </form>
        </div>
        <?php
    }

    public function save_settings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('hub_tibox_save_components_settings', 'hub_tibox_settings_nonce');

        $header = isset($_POST['hub_active_header']) ? absint($_POST['hub_active_header']) : 0;
        $footer = isset($_POST['hub_active_footer']) ? absint($_POST['hub_active_footer']) : 0;
        $scope = isset($_POST['hub_hybrid_scope']) ? sanitize_key(wp_unslash($_POST['hub_hybrid_scope'])) : 'selected';
        $pages = isset($_POST['hub_hybrid_pages']) ? array_map('absint', (array) $_POST['hub_hybrid_pages']) : [];

        if (!in_array($scope, ['selected', 'all_pages'], true)) {
            $scope = 'selected';
        }

        if ($header > 0 && $this->get_component_type($header) !== 'header') {
            $header = 0;
        }

        if ($footer > 0 && $this->get_component_type($footer) !== 'footer') {
            $footer = 0;
        }

        update_option(self::OPTION_HEADER, $header, false);
        update_option(self::OPTION_FOOTER, $footer, false);
        update_option(self::OPTION_HYBRID_ENABLED, isset($_POST['hub_hybrid_enabled']) ? '1' : '0', false);
        update_option(self::OPTION_HYBRID_SCOPE, $scope, false);
        update_option(self::OPTION_HYBRID_PAGES, array_values(array_unique(array_filter($pages))), false);

        $ip_header = isset($_POST['hub_client_ip_header'])
            ? preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['hub_client_ip_header'])))
            : '';
        update_option(HUB_Tibox_Landing_Forms::OPTION_IP_HEADER, (string) $ip_header, false);
        update_option('hub_tibox_elementor_landing_support', isset($_POST['hub_elementor_landing_support']) ? '1' : '0', false);

        wp_safe_redirect(add_query_arg([
            'post_type' => self::POST_TYPE,
            'page' => 'constructor-hub-settings',
            'updated' => '1',
        ], admin_url('edit.php')));
        exit;
    }

    /** @return WP_Post[] */
    public function get_components(string $type): array
    {
        if (!in_array($type, ['header', 'footer'], true)) {
            return [];
        }

        return get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_key' => self::META_TYPE,
            'meta_value' => $type,
        ]);
    }

    public function get_active_component_id(string $type): int
    {
        if ($type === 'header') {
            return absint(get_option(self::OPTION_HEADER, 0));
        }

        if ($type === 'footer') {
            return absint(get_option(self::OPTION_FOOTER, 0));
        }

        return 0;
    }

    public function get_component_type(int $post_id): string
    {
        $type = (string) get_post_meta($post_id, self::META_TYPE, true);
        return in_array($type, ['header', 'footer'], true) ? $type : 'header';
    }

    public function render_active_component(string $type): void
    {
        $post_id = $this->get_active_component_id($type);
        if ($post_id <= 0 || get_post_status($post_id) !== 'publish') {
            return;
        }

        if ($this->get_component_type($post_id) !== $type) {
            return;
        }

        $html = (string) get_post_meta($post_id, self::META_HTML, true);
        echo $this->replace_variables($html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-authored component HTML.
    }

    public function get_active_css(): string
    {
        return $this->get_active_code(self::META_CSS);
    }

    public function get_active_js(): string
    {
        return $this->get_active_code(self::META_JS);
    }

    public function hybrid_is_configured(): bool
    {
        return $this->get_active_component_id('header') > 0
            && $this->get_active_component_id('footer') > 0;
    }

    public function should_use_hybrid_for_page(int $page_id): bool
    {
        if (get_option(self::OPTION_HYBRID_ENABLED, '0') !== '1') {
            return false;
        }

        if (!$this->hybrid_is_configured()) {
            return false;
        }

        $scope = (string) get_option(self::OPTION_HYBRID_SCOPE, 'selected');
        if ($scope === 'all_pages') {
            return true;
        }

        $selected = array_map('absint', (array) get_option(self::OPTION_HYBRID_PAGES, []));
        return in_array($page_id, $selected, true);
    }

    private function get_active_code(string $meta_key): string
    {
        $chunks = [];
        foreach (['header', 'footer'] as $type) {
            $post_id = $this->get_active_component_id($type);
            if ($post_id <= 0 || get_post_status($post_id) !== 'publish') {
                continue;
            }

            $code = trim((string) get_post_meta($post_id, $meta_key, true));
            if ($code !== '') {
                $chunks[] = $code;
            }
        }

        return implode("\n\n", $chunks);
    }

    private function replace_variables(string $html): string
    {
        $logo_html = has_custom_logo() ? get_custom_logo() : esc_html(get_bloginfo('name'));
        $logo_id = (int) get_theme_mod('custom_logo', 0);
        $logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'full') : '';

        $primary_menu = wp_nav_menu([
            'theme_location' => 'primary',
            'container' => false,
            'echo' => false,
            'fallback_cb' => false,
        ]);

        $footer_menu = wp_nav_menu([
            'theme_location' => 'footer',
            'container' => false,
            'echo' => false,
            'fallback_cb' => false,
        ]);

        $variables = [
            '{{SITE_URL}}' => untrailingslashit(esc_url(home_url('/'))),
            '{{HOME_URL}}' => esc_url(home_url('/')),
            '{{SITE_NAME}}' => esc_html(get_bloginfo('name')),
            '{{CURRENT_YEAR}}' => esc_html(wp_date('Y')),
            '{{CUSTOM_LOGO}}' => (string) $logo_html,
            '{{CUSTOM_LOGO_URL}}' => esc_url($logo_url),
            '{{MENU_PRIMARY}}' => (string) $primary_menu,
            '{{MENU_FOOTER}}' => (string) $footer_menu,
        ];

        return strtr($html, $variables);
    }
}
