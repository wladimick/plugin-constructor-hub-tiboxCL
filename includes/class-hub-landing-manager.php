<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Landing content manager for Constructor HUB Tibox.
 */
final class HUB_Tibox_Landing_Manager
{
    public const POST_TYPE = 'hub_landing';

    public const MODE_LEGACY = 'legacy';
    public const MODE_HUB = 'hub';
    public const MODE_STANDALONE = 'standalone';
    public const MODE_PACKAGE = 'package';

    private const META_MODE = '_hub_landing_mode';
    private const META_HTML = '_hub_landing_html';
    private const META_CSS = '_hub_landing_css';
    private const META_JS = '_hub_landing_js';
    private const META_FULL_HTML = '_hub_landing_full_html';
    private const META_USE_HUB_CHROME = '_hub_landing_use_hub_chrome';
    private const META_RECIPIENT_EMAILS = '_hub_landing_recipient_emails';
    private const META_CONFIRMATION = '_hub_landing_confirmation';
    private const META_SUCCESS_MESSAGE = '_hub_landing_success_message';
    private const META_REQUIRED_FIELDS = '_hub_landing_required_fields';

    private const META_ADS_ACTIVE = '_hub_landing_ads_active';
    private const META_ADS_CAMPAIGN_NAME = '_hub_landing_ads_campaign_name';
    private const META_ADS_CAMPAIGN_ID = '_hub_landing_ads_campaign_id';
    private const META_ADS_START_DATE = '_hub_landing_ads_start_date';
    private const META_ADS_END_DATE = '_hub_landing_ads_end_date';
    private const META_ADS_FINAL_URL = '_hub_landing_ads_final_url';
    private const META_ADS_NOTES = '_hub_landing_ads_notes';

    private const OPTION_REWRITE_VERSION = 'hub_tibox_landing_rewrite_version';
    private const REWRITE_VERSION = '2';

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
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 99);
        add_action('add_meta_boxes_' . self::POST_TYPE, [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_landing']);
        add_filter('map_meta_cap', [$this, 'protect_active_campaign'], 10, 4);
        add_action('admin_notices', [$this, 'campaign_admin_notice']);
        add_filter('display_post_states', [$this, 'display_post_states'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'add_admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_column'], 10, 2);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
        add_action('admin_post_hub_tibox_duplicate_landing', [$this, 'duplicate_landing']);
    }

    public function register_post_type(): void
    {
        $rewrite_slug = (string) apply_filters('constructor_hub_landing_rewrite_slug', 'landing');
        $rewrite_slug = trim(sanitize_title($rewrite_slug), '/');
        if ($rewrite_slug === '') {
            $rewrite_slug = 'landing';
        }

        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Landings',
                'singular_name' => 'Landing',
                'add_new' => 'Nueva landing',
                'add_new_item' => 'Crear landing con IA',
                'edit_item' => 'Editar landing',
                'new_item' => 'Nueva landing',
                'view_item' => 'Ver landing',
                'search_items' => 'Buscar landings',
                'not_found' => 'No hay landings.',
                'all_items' => 'Landings',
                'menu_name' => 'Landings',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'show_in_rest' => true,
            'has_archive' => false,
            'rewrite' => [
                'slug' => $rewrite_slug,
                'with_front' => false,
            ],
            'supports' => ['title', 'editor', 'thumbnail', 'revisions', 'custom-fields', 'page-attributes'],
            'capability_type' => 'page',
            'map_meta_cap' => true,
        ]);
    }

    public function maybe_flush_rewrite_rules(): void
    {
        if ((string) get_option(self::OPTION_REWRITE_VERSION, '') === self::REWRITE_VERSION) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::OPTION_REWRITE_VERSION, self::REWRITE_VERSION, false);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'hub-landing-mode',
            'Motor / modo de landing',
            [$this, 'render_mode_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'hub-landing-code',
            'Diseño HUB — HTML / CSS / JavaScript',
            [$this, 'render_code_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'hub-landing-standalone',
            'Documento HTML completo',
            [$this, 'render_standalone_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'hub-landing-form-settings',
            'Formulario / correo',
            [$this, 'render_form_settings_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'hub-landing-google-ads',
            'Google Ads',
            [$this, 'render_google_ads_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
        add_meta_box(
            'hub-landing-contract',
            'Contrato para IA',
            [$this, 'render_contract_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_mode_meta_box(WP_Post $post): void
    {
        wp_nonce_field('hub_tibox_save_landing', 'hub_tibox_landing_nonce');
        $mode = $this->get_mode($post->ID);
        $use_hub_chrome = $this->uses_hub_chrome($post->ID);
        ?>
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:12px 0;">
            <?php
            $modes = [
                self::MODE_LEGACY => ['WordPress / Elementor', 'Usa el template normal del theme y permite Elementor.'],
                self::MODE_HUB => ['HUB', 'WordPress conserva hooks y Constructor HUB renderiza HTML/CSS/JS.'],
                self::MODE_STANDALONE => ['HTML completo', 'Documento completo generado por IA; Constructor HUB intenta inyectar hooks WordPress.'],
                self::MODE_PACKAGE => ['Package ZIP', 'Proyecto IA importado desde ZIP con sus assets relativos.'],
            ];
            foreach ($modes as $value => [$title, $description]) :
                ?>
                <label style="border:1px solid #c3c4c7;border-radius:8px;padding:12px;display:block;">
                    <input type="radio" name="hub_landing_mode" value="<?php echo esc_attr($value); ?>" <?php checked($mode, $value); ?>>
                    <strong style="display:block;margin:7px 0 4px;"><?php echo esc_html($title); ?></strong>
                    <small><?php echo esc_html($description); ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <p>
            <label>
                <input type="checkbox" name="hub_landing_use_hub_chrome" value="1" <?php checked($use_hub_chrome); ?>>
                Usar Header/Footer HUB globales cuando el modo sea HUB.
            </label>
        </p>
        <p><strong>URL:</strong> <code><?php echo esc_html(get_permalink($post) ?: home_url('/landing/slug/')); ?></code></p>
        <?php
    }

    public function render_code_meta_box(WP_Post $post): void
    {
        ?>
        <p>Pega aquí el diseño IA cuando uses el modo <strong>HUB</strong>.</p>
        <p><label><strong>HTML</strong></label><br><textarea name="hub_landing_html" rows="20" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($this->get_html($post->ID)); ?></textarea></p>
        <p><label><strong>CSS</strong></label><br><textarea name="hub_landing_css" rows="16" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($this->get_css($post->ID)); ?></textarea></p>
        <p><label><strong>JavaScript</strong></label><br><textarea name="hub_landing_js" rows="14" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($this->get_js($post->ID)); ?></textarea></p>
        <p class="description">No incluir PHP. En modo HUB no incluir <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code> ni <code>&lt;body&gt;</code>.</p>
        <?php
    }

    public function render_standalone_meta_box(WP_Post $post): void
    {
        ?>
        <p>Solo se utiliza en modo <strong>HTML completo</strong>. Admite documentos desde <code>&lt;!doctype html&gt;</code> hasta <code>&lt;/html&gt;</code>.</p>
        <textarea name="hub_landing_full_html" rows="24" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($this->get_full_html($post->ID)); ?></textarea>
        <?php
    }

    public function render_form_settings_meta_box(WP_Post $post): void
    {
        $required = $this->get_required_fields($post->ID);
        $recipients = $this->get_recipient_emails($post->ID);
        $confirmation = (string) get_post_meta($post->ID, self::META_CONFIRMATION, true);
        if (!in_array($confirmation, ['default', 'yes', 'no'], true)) {
            $confirmation = 'default';
        }
        $success = $this->get_success_message($post->ID);
        ?>
        <p><strong>Campos obligatorios adicionales</strong></p>
        <p>Email y consentimiento siempre son obligatorios. Marca solo lo que requiera esta landing:</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px 18px;">
            <?php foreach (['name' => 'Nombre', 'phone' => 'Teléfono', 'company' => 'Empresa', 'rut' => 'RUT', 'area' => 'Área/servicio', 'users' => 'Usuarios', 'message' => 'Mensaje'] as $key => $label) : ?>
                <label><input type="checkbox" name="hub_landing_required_fields[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $required, true)); ?>> <?php echo esc_html($label); ?></label>
            <?php endforeach; ?>
        </div>
        <hr>
        <p><label for="hub-landing-recipients"><strong>Destinatarios de esta landing</strong></label></p>
        <textarea id="hub-landing-recipients" name="hub_landing_recipient_emails" rows="4" class="large-text"><?php echo esc_textarea($recipients); ?></textarea>
        <p class="description">Vacío = usar los destinatarios globales de Constructor HUB → Correo. Uno por línea o separados por coma.</p>
        <p>
            <label for="hub-landing-confirmation"><strong>Confirmación al lead</strong></label><br>
            <select id="hub-landing-confirmation" name="hub_landing_confirmation">
                <option value="default" <?php selected($confirmation, 'default'); ?>>Usar configuración global</option>
                <option value="yes" <?php selected($confirmation, 'yes'); ?>>Sí</option>
                <option value="no" <?php selected($confirmation, 'no'); ?>>No</option>
            </select>
        </p>
        <p><label for="hub-landing-success"><strong>Mensaje de éxito</strong></label><br><textarea id="hub-landing-success" name="hub_landing_success_message" rows="3" class="large-text"><?php echo esc_textarea($success); ?></textarea></p>
        <?php
    }

    public function render_google_ads_meta_box(WP_Post $post): void
    {
        $active = $this->has_active_campaign($post->ID);
        $values = [
            'name' => get_post_meta($post->ID, self::META_ADS_CAMPAIGN_NAME, true),
            'id' => get_post_meta($post->ID, self::META_ADS_CAMPAIGN_ID, true),
            'start' => get_post_meta($post->ID, self::META_ADS_START_DATE, true),
            'end' => get_post_meta($post->ID, self::META_ADS_END_DATE, true),
            'url' => get_post_meta($post->ID, self::META_ADS_FINAL_URL, true),
            'notes' => get_post_meta($post->ID, self::META_ADS_NOTES, true),
        ];
        ?>
        <p><label><input type="checkbox" name="hub_landing_ads_active" value="1" <?php checked($active); ?>> <strong>Esta landing tiene una campaña activa de Google Ads</strong></label></p>
        <p class="description">Cuando está activa, usuarios no administradores no pueden editar ni eliminar la landing.</p>
        <table class="form-table" role="presentation">
            <tr><th><label for="hub-ads-name">Campaña</label></th><td><input id="hub-ads-name" name="hub_landing_ads_campaign_name" class="regular-text" value="<?php echo esc_attr((string) $values['name']); ?>"></td></tr>
            <tr><th><label for="hub-ads-id">ID Google Ads</label></th><td><input id="hub-ads-id" name="hub_landing_ads_campaign_id" class="regular-text" value="<?php echo esc_attr((string) $values['id']); ?>"></td></tr>
            <tr><th>Fechas</th><td><input type="date" name="hub_landing_ads_start_date" value="<?php echo esc_attr((string) $values['start']); ?>"> — <input type="date" name="hub_landing_ads_end_date" value="<?php echo esc_attr((string) $values['end']); ?>"></td></tr>
            <tr><th><label for="hub-ads-url">URL final</label></th><td><input id="hub-ads-url" type="url" name="hub_landing_ads_final_url" class="large-text" value="<?php echo esc_attr((string) $values['url']); ?>"></td></tr>
            <tr><th><label for="hub-ads-notes">Notas</label></th><td><textarea id="hub-ads-notes" name="hub_landing_ads_notes" rows="4" class="large-text"><?php echo esc_textarea((string) $values['notes']); ?></textarea></td></tr>
        </table>
        <?php
    }

    public function render_contract_meta_box(): void
    {
        ?>
        <p><strong>Variables HUB</strong></p>
        <p><code>{{SITE_URL}}</code><br><code>{{HOME_URL}}</code><br><code>{{SITE_NAME}}</code><br><code>{{CURRENT_YEAR}}</code><br><code>{{CUSTOM_LOGO}}</code><br><code>{{CUSTOM_LOGO_URL}}</code><br><code>{{LANDING_URL}}</code><br><code>{{LANDING_TITLE}}</code><br><code>{{HUB_FORM}}</code><br><code>{{FORM_ENDPOINT}}</code><br><code>{{PRIVACY_URL}}</code></p>
        <p>Formulario IA custom: usar <code>data-hub-landing-form</code>. Email + privacy son obligatorios; honeypot: <code>website</code>.</p>
        <?php
    }

    public function save_landing(int $post_id): void
    {
        if (!isset($_POST['hub_tibox_landing_nonce'])) {
            return;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST['hub_tibox_landing_nonce']));
        if (!wp_verify_nonce($nonce, 'hub_tibox_save_landing')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $mode = isset($_POST['hub_landing_mode']) ? sanitize_key(wp_unslash($_POST['hub_landing_mode'])) : self::MODE_HUB;
        if (!in_array($mode, [self::MODE_LEGACY, self::MODE_HUB, self::MODE_STANDALONE, self::MODE_PACKAGE], true)) {
            $mode = self::MODE_HUB;
        }
        update_post_meta($post_id, self::META_MODE, $mode);
        update_post_meta($post_id, self::META_USE_HUB_CHROME, isset($_POST['hub_landing_use_hub_chrome']) ? '1' : '0');

        $allowed_required = ['name', 'phone', 'company', 'rut', 'area', 'users', 'message'];
        $required = isset($_POST['hub_landing_required_fields']) ? array_map('sanitize_key', (array) wp_unslash($_POST['hub_landing_required_fields'])) : [];
        $required = array_values(array_intersect($allowed_required, $required));
        update_post_meta($post_id, self::META_REQUIRED_FIELDS, $required);

        $recipients = isset($_POST['hub_landing_recipient_emails']) ? sanitize_textarea_field(wp_unslash($_POST['hub_landing_recipient_emails'])) : '';
        update_post_meta($post_id, self::META_RECIPIENT_EMAILS, $recipients);

        $confirmation = isset($_POST['hub_landing_confirmation']) ? sanitize_key(wp_unslash($_POST['hub_landing_confirmation'])) : 'default';
        if (!in_array($confirmation, ['default', 'yes', 'no'], true)) {
            $confirmation = 'default';
        }
        update_post_meta($post_id, self::META_CONFIRMATION, $confirmation);

        $success = isset($_POST['hub_landing_success_message']) ? sanitize_textarea_field(wp_unslash($_POST['hub_landing_success_message'])) : '';
        update_post_meta($post_id, self::META_SUCCESS_MESSAGE, $success);

        update_post_meta($post_id, self::META_ADS_ACTIVE, isset($_POST['hub_landing_ads_active']) ? '1' : '0');
        $this->save_text_meta($post_id, self::META_ADS_CAMPAIGN_NAME, 'hub_landing_ads_campaign_name');
        $this->save_text_meta($post_id, self::META_ADS_CAMPAIGN_ID, 'hub_landing_ads_campaign_id');
        $this->save_date_meta($post_id, self::META_ADS_START_DATE, 'hub_landing_ads_start_date');
        $this->save_date_meta($post_id, self::META_ADS_END_DATE, 'hub_landing_ads_end_date');
        $url = isset($_POST['hub_landing_ads_final_url']) ? esc_url_raw(wp_unslash($_POST['hub_landing_ads_final_url'])) : '';
        update_post_meta($post_id, self::META_ADS_FINAL_URL, $url);
        $notes = isset($_POST['hub_landing_ads_notes']) ? sanitize_textarea_field(wp_unslash($_POST['hub_landing_ads_notes'])) : '';
        update_post_meta($post_id, self::META_ADS_NOTES, $notes);

        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            // Never drop the design silently: the previous behaviour saved the
            // rest of the form and discarded HTML/CSS/JS with no feedback, which
            // is total data loss on multisite where only super admins hold
            // `unfiltered_html`.
            HUB_Tibox_Capabilities::flag_code_not_saved();
            return;
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- design code is the payload; the nonce and the code capability are verified above.
        update_post_meta($post_id, self::META_HTML, isset($_POST['hub_landing_html']) ? wp_unslash($_POST['hub_landing_html']) : '');
        update_post_meta($post_id, self::META_CSS, isset($_POST['hub_landing_css']) ? wp_unslash($_POST['hub_landing_css']) : '');
        update_post_meta($post_id, self::META_JS, isset($_POST['hub_landing_js']) ? wp_unslash($_POST['hub_landing_js']) : '');
        $full_html = isset($_POST['hub_landing_full_html']) ? wp_unslash($_POST['hub_landing_full_html']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        update_post_meta($post_id, self::META_FULL_HTML, $full_html);
    }

    public function get_mode(int $post_id): string
    {
        $mode = (string) get_post_meta($post_id, self::META_MODE, true);
        return in_array($mode, [self::MODE_LEGACY, self::MODE_HUB, self::MODE_STANDALONE, self::MODE_PACKAGE], true)
            ? $mode
            : self::MODE_HUB;
    }

    public function get_html(int $post_id): string { return (string) get_post_meta($post_id, self::META_HTML, true); }
    public function get_css(int $post_id): string { return (string) get_post_meta($post_id, self::META_CSS, true); }
    public function get_js(int $post_id): string { return (string) get_post_meta($post_id, self::META_JS, true); }
    public function get_full_html(int $post_id): string { return (string) get_post_meta($post_id, self::META_FULL_HTML, true); }
    public function uses_hub_chrome(int $post_id): bool { return get_post_meta($post_id, self::META_USE_HUB_CHROME, true) === '1'; }
    public function get_recipient_emails(int $post_id): string { return (string) get_post_meta($post_id, self::META_RECIPIENT_EMAILS, true); }

    public function get_confirmation_override(int $post_id): ?bool
    {
        $value = (string) get_post_meta($post_id, self::META_CONFIRMATION, true);
        if ($value === 'yes') return true;
        if ($value === 'no') return false;
        return null;
    }

    /** @return string[] */
    public function get_required_fields(int $post_id): array
    {
        $value = get_post_meta($post_id, self::META_REQUIRED_FIELDS, true);
        return is_array($value) ? array_values(array_map('sanitize_key', $value)) : [];
    }

    public function get_success_message(int $post_id): string
    {
        $message = trim((string) get_post_meta($post_id, self::META_SUCCESS_MESSAGE, true));
        return $message !== '' ? $message : 'Gracias. Recibimos tus datos y te contactaremos pronto.';
    }

    /** @param array<string,mixed> $data */
    public function import_legacy_data(int $post_id, array $data): void
    {
        $mode = sanitize_key((string) ($data['mode'] ?? self::MODE_HUB));
        if (!in_array($mode, [self::MODE_LEGACY, self::MODE_HUB, self::MODE_STANDALONE, self::MODE_PACKAGE], true)) {
            $mode = self::MODE_HUB;
        }

        update_post_meta($post_id, self::META_MODE, $mode);
        update_post_meta($post_id, '_hub_legacy_landing_id', absint($data['legacy_id'] ?? 0));
        update_post_meta($post_id, self::META_HTML, (string) ($data['html'] ?? ''));
        update_post_meta($post_id, self::META_CSS, (string) ($data['css'] ?? ''));
        update_post_meta($post_id, self::META_JS, (string) ($data['js'] ?? ''));
        update_post_meta($post_id, self::META_FULL_HTML, (string) ($data['full_html'] ?? ''));
        update_post_meta($post_id, self::META_USE_HUB_CHROME, !empty($data['use_hub_chrome']) ? '1' : '0');

        $required = is_array($data['required_fields'] ?? null) ? $data['required_fields'] : [];
        $allowed_required = ['name', 'phone', 'company', 'rut', 'area', 'users', 'message'];
        update_post_meta($post_id, self::META_REQUIRED_FIELDS, array_values(array_intersect($allowed_required, array_map('sanitize_key', $required))));

        if (!empty($data['recipient_emails'])) {
            update_post_meta($post_id, self::META_RECIPIENT_EMAILS, sanitize_textarea_field((string) $data['recipient_emails']));
        }
        if (!empty($data['success_message'])) {
            update_post_meta($post_id, self::META_SUCCESS_MESSAGE, sanitize_textarea_field((string) $data['success_message']));
        }

        $ads = is_array($data['ads'] ?? null) ? $data['ads'] : [];
        update_post_meta($post_id, self::META_ADS_ACTIVE, !empty($ads['active']) ? '1' : '0');
        update_post_meta($post_id, self::META_ADS_CAMPAIGN_NAME, sanitize_text_field((string) ($ads['campaign_name'] ?? '')));
        update_post_meta($post_id, self::META_ADS_CAMPAIGN_ID, sanitize_text_field((string) ($ads['campaign_id'] ?? '')));
        update_post_meta($post_id, self::META_ADS_START_DATE, sanitize_text_field((string) ($ads['start_date'] ?? '')));
        update_post_meta($post_id, self::META_ADS_END_DATE, sanitize_text_field((string) ($ads['end_date'] ?? '')));
        update_post_meta($post_id, self::META_ADS_FINAL_URL, esc_url_raw((string) ($ads['final_url'] ?? '')));
        update_post_meta($post_id, self::META_ADS_NOTES, sanitize_textarea_field((string) ($ads['notes'] ?? '')));
    }

    public function has_active_campaign(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_ADS_ACTIVE, true) === '1';
    }

    public function protect_active_campaign(array $caps, string $cap, int $user_id, array $args): array
    {
        if (!in_array($cap, ['edit_post', 'delete_post'], true) || empty($args[0])) {
            return $caps;
        }
        $post_id = absint($args[0]);
        if (get_post_type($post_id) !== self::POST_TYPE || !$this->has_active_campaign($post_id)) {
            return $caps;
        }
        $user = get_userdata($user_id);
        if ($user instanceof WP_User && user_can($user, 'manage_options')) {
            return $caps;
        }
        return ['do_not_allow'];
    }

    public function campaign_admin_notice(): void
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }
        $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
        if ($post_id <= 0 || !$this->has_active_campaign($post_id)) {
            return;
        }
        $campaign = (string) get_post_meta($post_id, self::META_ADS_CAMPAIGN_NAME, true);
        echo '<div class="notice notice-warning"><p><strong>Esta landing tiene una campaña activa de Google Ads.</strong></p><p>';
        if ($campaign !== '') {
            echo 'Campaña: <strong>' . esc_html($campaign) . '</strong>. ';
        }
        echo 'Evita cambios directos que puedan afectar conversión, medición o aprobación de anuncios. Usa “Duplicar para nueva versión”.</p></div>';
    }

    public function display_post_states(array $states, WP_Post $post): array
    {
        if ($post->post_type === self::POST_TYPE && $this->has_active_campaign($post->ID)) {
            $states['hub_ads_active'] = 'Google Ads activa';
        }
        return $states;
    }

    public function add_admin_columns(array $columns): array
    {
        $columns['hub_mode'] = 'Motor';
        $columns['hub_campaign'] = 'Campaña';
        return $columns;
    }

    public function render_admin_column(string $column, int $post_id): void
    {
        if ($column === 'hub_mode') {
            $labels = [
                self::MODE_LEGACY => 'WordPress / Elementor',
                self::MODE_HUB => 'HUB',
                self::MODE_STANDALONE => 'HTML completo',
                self::MODE_PACKAGE => 'Package ZIP',
            ];
            echo esc_html($labels[$this->get_mode($post_id)] ?? $this->get_mode($post_id));
        }
        if ($column === 'hub_campaign') {
            if (!$this->has_active_campaign($post_id)) {
                echo '—';
                return;
            }
            $name = (string) get_post_meta($post_id, self::META_ADS_CAMPAIGN_NAME, true);
            echo '<strong style="color:#b32d2e">Activa</strong>';
            if ($name !== '') echo '<br>' . esc_html($name);
        }
    }

    public function add_row_actions(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== self::POST_TYPE || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        $url = wp_nonce_url(
            add_query_arg([
                'action' => 'hub_tibox_duplicate_landing',
                'post_id' => $post->ID,
            ], admin_url('admin-post.php')),
            'hub_tibox_duplicate_landing_' . $post->ID
        );
        $actions['hub_duplicate'] = '<a href="' . esc_url($url) . '">Duplicar para nueva versión</a>';
        return $actions;
    }

    public function duplicate_landing(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }
        $source_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        check_admin_referer('hub_tibox_duplicate_landing_' . $source_id);
        $source = get_post($source_id);
        if (!$source instanceof WP_Post || $source->post_type !== self::POST_TYPE) {
            wp_die('Landing no válida.');
        }

        $new_id = wp_insert_post([
            'post_type' => self::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => $source->post_title . ' — Nueva versión',
            'post_content' => $source->post_content,
            'post_excerpt' => $source->post_excerpt,
        ], true);
        if (is_wp_error($new_id)) {
            wp_die(esc_html($new_id->get_error_message()));
        }

        foreach (get_post_meta($source_id) as $key => $values) {
            if (
                $key === self::META_ADS_ACTIVE ||
                $key === '_hub_legacy_landing_id' ||
                str_starts_with((string) $key, '_hub_landing_zip_')
            ) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($new_id, $key, maybe_unserialize($value));
            }
        }
        update_post_meta($new_id, self::META_ADS_ACTIVE, '0');

        if (
            $this->get_mode($source_id) === self::MODE_PACKAGE &&
            class_exists('HUB_Tibox_Landing_Zip_Importer')
        ) {
            HUB_Tibox_Landing_Zip_Importer::instance()->clone_package($source_id, $new_id);
        }

        wp_safe_redirect(get_edit_post_link($new_id, 'url'));
        exit;
    }

    private function save_text_meta(int $post_id, string $meta_key, string $input_name): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller before these helpers run.
        $value = isset($_POST[$input_name]) ? sanitize_text_field(wp_unslash($_POST[$input_name])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        update_post_meta($post_id, $meta_key, $value);
    }

    private function save_date_meta(int $post_id, string $meta_key, string $input_name): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller before these helpers run.
        $value = isset($_POST[$input_name]) ? sanitize_text_field(wp_unslash($_POST[$input_name])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value = '';
        }
        update_post_meta($post_id, $meta_key, $value);
    }
}
