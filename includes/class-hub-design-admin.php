<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editing screen for a HUB design.
 *
 * Every save writes an immutable version. Publishing is an explicit act that
 * moves a pointer, so the previous version stays reachable and a rollback is one
 * click instead of a restore from backup.
 */
final class HUB_Tibox_Design_Admin
{
    private const NONCE_ACTION = 'hub_tibox_save_design';
    private const NONCE_FIELD = 'hub_tibox_design_nonce';

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
        add_action('add_meta_boxes_' . HUB_Tibox_Design::POST_TYPE, [$this, 'add_meta_boxes']);
        add_action('save_post_' . HUB_Tibox_Design::POST_TYPE, [$this, 'save_design'], 20);

        add_action('admin_post_hub_tibox_publish_version', [$this, 'handle_publish_version']);
        add_action('admin_post_hub_tibox_duplicate_design', [$this, 'handle_duplicate_design']);

        add_filter('manage_' . HUB_Tibox_Design::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . HUB_Tibox_Design::POST_TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('post_row_actions', [$this, 'row_actions'], 10, 2);
        add_filter('display_post_states', [$this, 'post_states'], 10, 2);
        add_action('pre_get_posts', [$this, 'filter_admin_list']);
        add_action('admin_notices', [$this, 'admin_notices']);
        add_filter('default_title', [$this, 'default_title'], 10, 2);
    }

    // ------------------------------------------------------------ meta boxes

    public function add_meta_boxes(): void
    {
        add_meta_box('hub-design-code', 'Diseño — HTML / CSS / JavaScript', [$this, 'render_code_box'], HUB_Tibox_Design::POST_TYPE, 'normal', 'high');
        add_meta_box('hub-design-versions', 'Versiones', [$this, 'render_versions_box'], HUB_Tibox_Design::POST_TYPE, 'normal', 'default');
        add_meta_box('hub-design-form', 'Formulario y correo', [$this, 'render_form_box'], HUB_Tibox_Design::POST_TYPE, 'normal', 'default');
        add_meta_box('hub-design-ads', 'Google Ads', [$this, 'render_ads_box'], HUB_Tibox_Design::POST_TYPE, 'normal', 'default');
        add_meta_box('hub-design-settings', 'Tipo y render', [$this, 'render_settings_box'], HUB_Tibox_Design::POST_TYPE, 'side', 'high');
        add_meta_box('hub-design-contract', 'Contrato para IA', [$this, 'render_contract_box'], HUB_Tibox_Design::POST_TYPE, 'side', 'default');
    }

    public function render_settings_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $type = HUB_Tibox_Design::get_type($post->ID);
        $requested = isset($_GET['hub_type']) ? sanitize_key(wp_unslash($_GET['hub_type'])) : '';
        if ($post->post_status === 'auto-draft' && HUB_Tibox_Design::is_valid_type($requested)) {
            $type = $requested;
        }

        $mode = HUB_Tibox_Design::get_render_mode($post->ID);
        ?>
        <p>
            <label for="hub-design-type"><strong>Tipo de diseño</strong></label>
            <select id="hub-design-type" name="hub_design_type" style="width:100%;margin-top:6px;">
                <?php foreach (HUB_Tibox_Design::types() as $key => $definition) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($type, $key); ?>>
                        <?php echo esc_html($definition['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p class="description">
            Los tipos Landing y Página tienen URL propia. El resto son fragmentos que se insertan con
            <code>[hub_design]</code>, el bloque HUB o una región.
        </p>

        <p>
            <label for="hub-design-mode"><strong>Modo de render</strong></label>
            <select id="hub-design-mode" name="hub_design_mode" style="width:100%;margin-top:6px;">
                <option value="<?php echo esc_attr(HUB_Tibox_Design::MODE_HUB); ?>" <?php selected($mode, HUB_Tibox_Design::MODE_HUB); ?>>HUB (fragmento en el shell)</option>
                <option value="<?php echo esc_attr(HUB_Tibox_Design::MODE_STANDALONE); ?>" <?php selected($mode, HUB_Tibox_Design::MODE_STANDALONE); ?>>Documento HTML completo</option>
                <option value="<?php echo esc_attr(HUB_Tibox_Design::MODE_PACKAGE); ?>" <?php selected($mode, HUB_Tibox_Design::MODE_PACKAGE); ?>>Package ZIP</option>
                <option value="<?php echo esc_attr(HUB_Tibox_Design::MODE_LEGACY); ?>" <?php selected($mode, HUB_Tibox_Design::MODE_LEGACY); ?>>Theme / Elementor</option>
            </select>
        </p>

        <p>
            <label>
                <input type="checkbox" name="hub_design_use_chrome" value="1" <?php checked(HUB_Tibox_Design::uses_chrome($post->ID)); ?>>
                Usar Header/Footer HUB
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="hub_design_css_scope" value="1" <?php checked(HUB_Tibox_Design::uses_css_scope($post->ID)); ?>>
                Aislar el CSS de este diseño
            </label>
        </p>
        <p class="description">
            El aislamiento antepone <code>.<?php echo esc_html(HUB_Tibox_Design::scope_class($post->ID)); ?></code>
            a cada selector. Actívalo si el diseño convive con el theme o con Elementor.
        </p>
        <?php
    }

    public function render_code_box(WP_Post $post): void
    {
        $version = HUB_Tibox_Version_Store::instance()->get_working($post->ID);
        $html = (string) ($version['html'] ?? '');
        $css = (string) ($version['css'] ?? '');
        $js = (string) ($version['js'] ?? '');
        $readonly = !HUB_Tibox_Capabilities::can_edit_design_code();
        ?>
        <?php if ($readonly) : ?>
            <div class="notice notice-warning inline"><p>
                Tu rol no puede editar código de diseño. Los campos son de solo lectura.
            </p></div>
        <?php endif; ?>

        <p>
            <label for="hub-design-html"><strong>HTML</strong></label>
            <textarea id="hub-design-html" name="hub_design_html" rows="20" style="width:100%;font-family:monospace;tab-size:2;" <?php disabled($readonly); ?>><?php echo esc_textarea($html); ?></textarea>
        </p>
        <p>
            <label for="hub-design-css"><strong>CSS</strong></label>
            <textarea id="hub-design-css" name="hub_design_css" rows="16" style="width:100%;font-family:monospace;tab-size:2;" <?php disabled($readonly); ?>><?php echo esc_textarea($css); ?></textarea>
        </p>
        <p>
            <label for="hub-design-js"><strong>JavaScript</strong></label>
            <textarea id="hub-design-js" name="hub_design_js" rows="14" style="width:100%;font-family:monospace;tab-size:2;" <?php disabled($readonly); ?>><?php echo esc_textarea($js); ?></textarea>
        </p>
        <p class="description">
            No pegar etiquetas <code>&lt;style&gt;</code> ni <code>&lt;script&gt;</code>: Constructor HUB compila cada
            bloque a su propio archivo. Nunca guardar aquí claves ni tokens.
        </p>
        <p>
            <label>
                <input type="checkbox" name="hub_design_publish_version" value="1" checked>
                <strong>Publicar esta versión al guardar</strong>
            </label>
            <span class="description">Desmárcalo para guardar un borrador y previsualizarlo antes de reemplazar lo que está en producción.</span>
        </p>
        <?php
    }

    public function render_versions_box(WP_Post $post): void
    {
        $store = HUB_Tibox_Version_Store::instance();
        $history = $store->history($post->ID);
        $live = $store->get_live($post->ID);
        $live_id = $live === null ? 0 : (int) $live['id'];

        if ($history === []) {
            echo '<p>Todavía no hay versiones. La primera se crea al guardar el diseño.</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:70px;">Versión</th>
                    <th style="width:110px;">Estado</th>
                    <th>Etiqueta</th>
                    <th style="width:100px;">Origen</th>
                    <th style="width:150px;">Fecha</th>
                    <th style="width:120px;">Tamaño</th>
                    <th style="width:210px;">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($history as $row) :
                $version_id = (int) $row['id'];
                $is_live = $version_id === $live_id;
                ?>
                <tr>
                    <td><strong><?php echo esc_html((string) $row['version']); ?></strong></td>
                    <td>
                        <?php if ($is_live) : ?>
                            <span style="color:#00713c;font-weight:600;">Publicada</span>
                        <?php else : ?>
                            <?php echo esc_html($row['status'] === HUB_Tibox_Version_Store::STATUS_DRAFT ? 'Borrador' : 'Archivada'); ?>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html((string) $row['label']); ?></td>
                    <td><code><?php echo esc_html((string) $row['source']); ?></code></td>
                    <td><?php echo esc_html((string) $row['created_at']); ?></td>
                    <td style="font-variant-numeric:tabular-nums;">
                        <?php echo esc_html(sprintf('%d / %d / %d', (int) $row['html_length'], (int) $row['css_length'], (int) $row['js_length'])); ?>
                    </td>
                    <td>
                        <a class="button button-small" target="_blank" rel="noopener"
                           href="<?php echo esc_url(HUB_Tibox_Preview::url($post->ID, $version_id)); ?>">Preview</a>
                        <?php if (!$is_live) : ?>
                            <a class="button button-small button-primary"
                               href="<?php echo esc_url($this->publish_url($post->ID, $version_id)); ?>">
                                <?php echo esc_html($row['status'] === HUB_Tibox_Version_Store::STATUS_ARCHIVED ? 'Rollback' : 'Publicar'); ?>
                            </a>
                        <?php endif; ?>
                        <a class="button button-small" href="<?php echo esc_url($this->export_url($post->ID, $version_id)); ?>">ZIP</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            Tamaño en caracteres de HTML / CSS / JavaScript. El enlace de preview está firmado y caduca en 24 horas:
            se puede compartir con alguien sin cuenta en WordPress.
        </p>
        <?php
    }

    public function render_form_box(WP_Post $post): void
    {
        $required = HUB_Tibox_Design::get_required_fields($post->ID);
        $confirmation = (string) get_post_meta($post->ID, HUB_Tibox_Design::META_CONFIRMATION, true);
        if (!in_array($confirmation, ['default', 'yes', 'no'], true)) {
            $confirmation = 'default';
        }
        $labels = [
            'name' => 'Nombre', 'phone' => 'Teléfono', 'company' => 'Empresa', 'rut' => 'RUT',
            'area' => 'Área/servicio', 'users' => 'Usuarios', 'message' => 'Mensaje',
        ];
        ?>
        <p><strong>Campos obligatorios adicionales</strong></p>
        <p class="description">Correo y consentimiento son siempre obligatorios.</p>
        <div style="display:flex;flex-wrap:wrap;gap:10px 18px;margin:10px 0 18px;">
            <?php foreach ($labels as $key => $label) : ?>
                <label>
                    <input type="checkbox" name="hub_design_required_fields[]" value="<?php echo esc_attr($key); ?>" <?php checked(in_array($key, $required, true)); ?>>
                    <?php echo esc_html($label); ?>
                </label>
            <?php endforeach; ?>
        </div>

        <p>
            <label for="hub-design-recipients"><strong>Destinatarios de este diseño</strong></label>
            <textarea id="hub-design-recipients" name="hub_design_recipients" rows="3" class="large-text"><?php echo esc_textarea(HUB_Tibox_Design::get_recipient_emails($post->ID)); ?></textarea>
        </p>
        <p class="description">Vacío = destinatarios globales de Constructor HUB → Correo.</p>

        <p>
            <label for="hub-design-confirmation"><strong>Confirmación al contacto</strong></label><br>
            <select id="hub-design-confirmation" name="hub_design_confirmation">
                <option value="default" <?php selected($confirmation, 'default'); ?>>Usar configuración global</option>
                <option value="yes" <?php selected($confirmation, 'yes'); ?>>Sí</option>
                <option value="no" <?php selected($confirmation, 'no'); ?>>No</option>
            </select>
        </p>

        <p>
            <label for="hub-design-success"><strong>Mensaje de éxito</strong></label>
            <textarea id="hub-design-success" name="hub_design_success_message" rows="2" class="large-text"><?php echo esc_textarea((string) get_post_meta($post->ID, HUB_Tibox_Design::META_SUCCESS_MESSAGE, true)); ?></textarea>
        </p>
        <?php
    }

    public function render_ads_box(WP_Post $post): void
    {
        $values = [
            'name' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME, true),
            'id' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_CAMPAIGN_ID, true),
            'start' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_START_DATE, true),
            'end' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_END_DATE, true),
            'url' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_FINAL_URL, true),
            'notes' => get_post_meta($post->ID, HUB_Tibox_Design::META_ADS_NOTES, true),
        ];
        ?>
        <p>
            <label>
                <input type="checkbox" name="hub_design_ads_active" value="1" <?php checked(HUB_Tibox_Design::has_active_campaign($post->ID)); ?>>
                <strong>Este diseño tiene una campaña activa de Google Ads</strong>
            </label>
        </p>
        <p class="description">
            Marca la landing en los listados y avisa antes de editarla. Con versiones y rollback ya no hace falta
            bloquear la edición: publicar una versión nueva es reversible.
        </p>
        <table class="form-table" role="presentation">
            <tr><th><label for="hub-ads-name">Campaña</label></th><td><input id="hub-ads-name" name="hub_design_ads_campaign_name" class="regular-text" value="<?php echo esc_attr((string) $values['name']); ?>"></td></tr>
            <tr><th><label for="hub-ads-id">ID Google Ads</label></th><td><input id="hub-ads-id" name="hub_design_ads_campaign_id" class="regular-text" value="<?php echo esc_attr((string) $values['id']); ?>"></td></tr>
            <tr><th>Fechas</th><td><input type="date" name="hub_design_ads_start_date" value="<?php echo esc_attr((string) $values['start']); ?>"> — <input type="date" name="hub_design_ads_end_date" value="<?php echo esc_attr((string) $values['end']); ?>"></td></tr>
            <tr><th><label for="hub-ads-url">URL final</label></th><td><input id="hub-ads-url" type="url" name="hub_design_ads_final_url" class="large-text" value="<?php echo esc_attr((string) $values['url']); ?>"></td></tr>
            <tr><th><label for="hub-ads-notes">Notas</label></th><td><textarea id="hub-ads-notes" name="hub_design_ads_notes" rows="3" class="large-text"><?php echo esc_textarea((string) $values['notes']); ?></textarea></td></tr>
        </table>
        <?php
    }

    public function render_contract_box(WP_Post $post): void
    {
        $groups = [];
        foreach (HUB_Tibox_Variables::registry() as $name => $definition) {
            $groups[$definition['group']][] = $name;
        }
        ?>
        <p><strong>Variables disponibles</strong></p>
        <?php foreach ($groups as $group => $names) : ?>
            <p style="margin-bottom:4px;"><em><?php echo esc_html(ucfirst($group)); ?></em><br>
                <?php foreach ($names as $name) : ?>
                    <code style="display:inline-block;margin:2px 2px 0 0;">{{<?php echo esc_html($name); ?>}}</code>
                <?php endforeach; ?>
            </p>
        <?php endforeach; ?>
        <p class="description">
            Contrato <?php echo esc_html(HUB_Tibox_Variables::CONTRACT_VERSION); ?>.
            Formulario propio: usar <code>data-hub-landing-form</code>. Honeypot: <code>website</code>.
        </p>
        <p><strong>Insertar en otra página</strong></p>
        <p>
            Shortcode:<br>
            <code>[hub_design slug="<?php echo esc_html($post->post_name !== '' ? $post->post_name : 'slug'); ?>"]</code>
        </p>
        <p>
            Bloque: <em>Diseño HUB</em>. Elementor: widget <em>Diseño HUB</em>.<br>
            Plantilla PHP:<br>
            <code>constructor_hub_render('<?php echo esc_html($post->post_name !== '' ? $post->post_name : 'slug'); ?>');</code>
        </p>
        <?php
    }

    // ----------------------------------------------------------------- saving

    public function save_design(int $post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $type = isset($_POST['hub_design_type']) ? sanitize_key(wp_unslash($_POST['hub_design_type'])) : 'section';
        if (!HUB_Tibox_Design::is_valid_type($type)) {
            $type = 'section';
        }
        update_post_meta($post_id, HUB_Tibox_Design::META_TYPE, $type);

        $mode = isset($_POST['hub_design_mode']) ? sanitize_key(wp_unslash($_POST['hub_design_mode'])) : HUB_Tibox_Design::MODE_HUB;
        $modes = [HUB_Tibox_Design::MODE_HUB, HUB_Tibox_Design::MODE_STANDALONE, HUB_Tibox_Design::MODE_PACKAGE, HUB_Tibox_Design::MODE_LEGACY];
        update_post_meta($post_id, HUB_Tibox_Design::META_RENDER_MODE, in_array($mode, $modes, true) ? $mode : HUB_Tibox_Design::MODE_HUB);

        update_post_meta($post_id, HUB_Tibox_Design::META_USE_CHROME, isset($_POST['hub_design_use_chrome']) ? '1' : '0');
        update_post_meta($post_id, HUB_Tibox_Design::META_CSS_SCOPE, isset($_POST['hub_design_css_scope']) ? '1' : '0');

        $required = isset($_POST['hub_design_required_fields'])
            ? array_map('sanitize_key', (array) wp_unslash($_POST['hub_design_required_fields']))
            : [];
        update_post_meta(
            $post_id,
            HUB_Tibox_Design::META_REQUIRED_FIELDS,
            array_values(array_intersect(HUB_Tibox_Design::allowed_required_fields(), $required))
        );

        $recipients = isset($_POST['hub_design_recipients']) ? sanitize_textarea_field(wp_unslash($_POST['hub_design_recipients'])) : '';
        update_post_meta($post_id, HUB_Tibox_Design::META_RECIPIENTS, $recipients);

        $confirmation = isset($_POST['hub_design_confirmation']) ? sanitize_key(wp_unslash($_POST['hub_design_confirmation'])) : 'default';
        update_post_meta($post_id, HUB_Tibox_Design::META_CONFIRMATION, in_array($confirmation, ['default', 'yes', 'no'], true) ? $confirmation : 'default');

        $success = isset($_POST['hub_design_success_message']) ? sanitize_textarea_field(wp_unslash($_POST['hub_design_success_message'])) : '';
        update_post_meta($post_id, HUB_Tibox_Design::META_SUCCESS_MESSAGE, $success);

        update_post_meta($post_id, HUB_Tibox_Design::META_ADS_ACTIVE, isset($_POST['hub_design_ads_active']) ? '1' : '0');
        $this->save_text($post_id, HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME, 'hub_design_ads_campaign_name');
        $this->save_text($post_id, HUB_Tibox_Design::META_ADS_CAMPAIGN_ID, 'hub_design_ads_campaign_id');
        $this->save_date($post_id, HUB_Tibox_Design::META_ADS_START_DATE, 'hub_design_ads_start_date');
        $this->save_date($post_id, HUB_Tibox_Design::META_ADS_END_DATE, 'hub_design_ads_end_date');
        update_post_meta(
            $post_id,
            HUB_Tibox_Design::META_ADS_FINAL_URL,
            isset($_POST['hub_design_ads_final_url']) ? esc_url_raw(wp_unslash($_POST['hub_design_ads_final_url'])) : ''
        );
        $notes = isset($_POST['hub_design_ads_notes']) ? sanitize_textarea_field(wp_unslash($_POST['hub_design_ads_notes'])) : '';
        update_post_meta($post_id, HUB_Tibox_Design::META_ADS_NOTES, $notes);

        $this->maybe_store_version($post_id);
    }

    private function maybe_store_version(int $post_id): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller, save_design().
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- design code is the payload; the code capability is checked below.
        // The code fields are absent when the editor rendered them read only.
        if (!isset($_POST['hub_design_html']) && !isset($_POST['hub_design_css']) && !isset($_POST['hub_design_js'])) {
            return;
        }

        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            HUB_Tibox_Capabilities::flag_code_not_saved();
            return;
        }

        $store = HUB_Tibox_Version_Store::instance();
        $current = $store->get_working($post_id);

        $version_id = $store->create($post_id, [
            'html' => isset($_POST['hub_design_html']) ? wp_unslash($_POST['hub_design_html']) : (string) ($current['html'] ?? ''),
            'css' => isset($_POST['hub_design_css']) ? wp_unslash($_POST['hub_design_css']) : (string) ($current['css'] ?? ''),
            'js' => isset($_POST['hub_design_js']) ? wp_unslash($_POST['hub_design_js']) : (string) ($current['js'] ?? ''),
            'entry' => (string) ($current['entry'] ?? ''),
            'asset_dir' => (string) ($current['asset_dir'] ?? ''),
            'manifest' => (string) ($current['manifest'] ?? ''),
            'source' => 'editor',
        ]);

        if ($version_id <= 0) {
            return;
        }

        if (!empty($_POST['hub_design_publish_version'])) {
            $store->publish($post_id, $version_id);
        }
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.NonceVerification.Missing
    }

    // ----------------------------------------------------------- admin routes

    public function handle_publish_version(): void
    {
        $design_id = isset($_GET['design_id']) ? absint($_GET['design_id']) : 0;
        $version_id = isset($_GET['version_id']) ? absint($_GET['version_id']) : 0;

        check_admin_referer('hub_tibox_publish_version_' . $design_id . '_' . $version_id);

        if (!current_user_can('edit_post', $design_id) || !HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $published = HUB_Tibox_Version_Store::instance()->publish($design_id, $version_id);

        wp_safe_redirect(add_query_arg(
            ['hub_notice' => $published ? 'published' : 'publish_failed'],
            (string) get_edit_post_link($design_id, 'url')
        ));
        exit;
    }

    public function handle_duplicate_design(): void
    {
        $source_id = isset($_GET['design_id']) ? absint($_GET['design_id']) : 0;
        check_admin_referer('hub_tibox_duplicate_design_' . $source_id);

        if (!current_user_can('edit_post', $source_id) || !current_user_can('create_hub_designs')) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $source = get_post($source_id);
        if (!$source instanceof WP_Post || $source->post_type !== HUB_Tibox_Design::POST_TYPE) {
            wp_die(esc_html__('Diseño no válido.', 'constructor-hub-tibox'), '', ['response' => 400]);
        }

        $new_id = wp_insert_post([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => $source->post_title . ' — copia',
            'post_excerpt' => $source->post_excerpt,
        ], true);

        if (is_wp_error($new_id)) {
            wp_die(esc_html($new_id->get_error_message()));
        }

        foreach (get_post_meta($source_id) as $key => $values) {
            if (in_array($key, [HUB_Tibox_Design::META_ADS_ACTIVE, HUB_Tibox_Design::META_CURRENT_VERSION, HUB_Tibox_Design::META_LEGACY_ID], true)) {
                continue;
            }
            foreach ($values as $value) {
                add_post_meta($new_id, (string) $key, maybe_unserialize($value));
            }
        }
        update_post_meta($new_id, HUB_Tibox_Design::META_ADS_ACTIVE, '0');

        $live = HUB_Tibox_Version_Store::instance()->get_live($source_id);
        if ($live !== null) {
            $version_id = HUB_Tibox_Version_Store::instance()->create((int) $new_id, [
                'html' => (string) $live['html'],
                'css' => (string) $live['css'],
                'js' => (string) $live['js'],
                'manifest' => (string) $live['manifest'],
                'entry' => (string) $live['entry'],
                'source' => 'duplicate',
                'label' => sprintf('Copiada de #%d v%d', $source_id, (int) $live['version']),
            ]);
            if ($version_id > 0) {
                HUB_Tibox_Version_Store::instance()->publish((int) $new_id, $version_id);
            }
        }

        do_action('constructor_hub_design_duplicated', $source_id, (int) $new_id);

        wp_safe_redirect((string) get_edit_post_link((int) $new_id, 'url'));
        exit;
    }

    // ------------------------------------------------------------- list table

    /** @param array<string,string> $columns */
    public function columns(array $columns): array
    {
        $reordered = [];
        foreach ($columns as $key => $label) {
            $reordered[$key] = $label;
            if ($key === 'title') {
                $reordered['hub_type'] = 'Tipo';
                $reordered['hub_version'] = 'Versión';
                $reordered['hub_campaign'] = 'Campaña';
            }
        }

        return $reordered;
    }

    public function column(string $column, int $post_id): void
    {
        if ($column === 'hub_type') {
            echo esc_html(HUB_Tibox_Design::type_label(HUB_Tibox_Design::get_type($post_id)));
            return;
        }

        if ($column === 'hub_version') {
            $live = HUB_Tibox_Version_Store::instance()->get_live($post_id);
            echo $live === null ? '—' : esc_html('v' . (int) $live['version']);
            return;
        }

        if ($column === 'hub_campaign') {
            if (!HUB_Tibox_Design::has_active_campaign($post_id)) {
                echo '—';
                return;
            }
            echo '<strong style="color:#b32d2e;">Activa</strong>';
            $name = (string) get_post_meta($post_id, HUB_Tibox_Design::META_ADS_CAMPAIGN_NAME, true);
            if ($name !== '') {
                echo '<br>' . esc_html($name);
            }
        }
    }

    /** @param array<string,string> $actions */
    public function row_actions(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== HUB_Tibox_Design::POST_TYPE || !current_user_can('edit_post', $post->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg(['action' => 'hub_tibox_duplicate_design', 'design_id' => $post->ID], admin_url('admin-post.php')),
            'hub_tibox_duplicate_design_' . $post->ID
        );

        if (current_user_can('create_hub_designs')) {
            $actions['hub_duplicate'] = '<a href="' . esc_url($url) . '">Duplicar</a>';
        }

        return $actions;
    }

    /** @param array<string,string> $states */
    public function post_states(array $states, WP_Post $post): array
    {
        if ($post->post_type === HUB_Tibox_Design::POST_TYPE && HUB_Tibox_Design::has_active_campaign($post->ID)) {
            $states['hub_ads_active'] = 'Google Ads activa';
        }

        return $states;
    }

    public function filter_admin_list(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== HUB_Tibox_Design::POST_TYPE) {
            return;
        }

        $type = isset($_GET['hub_type']) ? sanitize_key(wp_unslash($_GET['hub_type'])) : '';
        if ($type === '' || !HUB_Tibox_Design::is_valid_type($type)) {
            return;
        }

        $query->set('meta_query', [[
            'key' => HUB_Tibox_Design::META_TYPE,
            'value' => $type,
        ]]);
    }

    public function default_title(string $title, $post = null): string
    {
        if (!$post instanceof WP_Post || $post->post_type !== HUB_Tibox_Design::POST_TYPE) {
            return $title;
        }

        $type = isset($_GET['hub_type']) ? sanitize_key(wp_unslash($_GET['hub_type'])) : '';
        return HUB_Tibox_Design::is_valid_type($type) ? HUB_Tibox_Design::type_label($type) . ' ' : $title;
    }

    public function admin_notices(): void
    {
        $notice = isset($_GET['hub_notice']) ? sanitize_key(wp_unslash($_GET['hub_notice'])) : '';
        if ($notice === '') {
            return;
        }

        $messages = [
            'published' => ['success', 'Versión publicada. La anterior queda archivada y se puede restaurar.'],
            'publish_failed' => ['error', 'No fue posible publicar esa versión.'],
        ];

        if (!isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];
        printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($class), esc_html($message));
    }

    /** Package export closes the loop: site to AI, or site to another site. */
    public function export_url(int $design_id, int $version_id): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => 'hub_tibox_export_package',
                'design_id' => $design_id,
                'version_id' => $version_id,
            ], admin_url('admin-post.php')),
            'hub_tibox_export_package_' . $design_id
        );
    }

    public function publish_url(int $design_id, int $version_id): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => 'hub_tibox_publish_version',
                'design_id' => $design_id,
                'version_id' => $version_id,
            ], admin_url('admin-post.php')),
            'hub_tibox_publish_version_' . $design_id . '_' . $version_id
        );
    }

    private function save_text(int $post_id, string $meta_key, string $field): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller before these helpers run.
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        update_post_meta($post_id, $meta_key, $value);
    }

    private function save_date(int $post_id, string $meta_key, string $field): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by the caller before these helpers run.
        $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ($value !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value = '';
        }
        update_post_meta($post_id, $meta_key, $value);
    }
}
