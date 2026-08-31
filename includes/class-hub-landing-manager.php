<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Landing content manager for Constructor HUB Tibox.
 *
 * Landings are stored as a dedicated public post type so WordPress remains the
 * backend for title, slug, status and SEO hooks, while the visual layer is
 * authored as isolated HTML/CSS/JS.
 */
final class HUB_Tibox_Landing_Manager
{
    public const POST_TYPE = 'hub_landing';

    private const META_HTML = '_hub_landing_html';
    private const META_CSS = '_hub_landing_css';
    private const META_JS = '_hub_landing_js';
    private const META_USE_HUB_CHROME = '_hub_landing_use_hub_chrome';
    private const META_RECIPIENT_EMAIL = '_hub_landing_recipient_email';
    private const META_SUCCESS_MESSAGE = '_hub_landing_success_message';

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
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_landing']);
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
                'menu_name' => 'Landings',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => true,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => [
                'slug' => $rewrite_slug,
                'with_front' => false,
            ],
            'supports' => ['title'],
            'capability_type' => 'page',
            'map_meta_cap' => true,
        ]);
    }

    public function add_meta_boxes(): void
    {
        add_meta_box(
            'hub-landing-code',
            'Diseño IA — HTML / CSS / JavaScript',
            [$this, 'render_code_meta_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'hub-landing-settings',
            'Configuración de landing',
            [$this, 'render_settings_meta_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'hub-landing-contract',
            'Contrato para IA',
            [$this, 'render_contract_meta_box'],
            self::POST_TYPE,
            'normal',
            'default'
        );
    }

    public function render_code_meta_box(WP_Post $post): void
    {
        wp_nonce_field('hub_tibox_save_landing', 'hub_tibox_landing_nonce');

        $html = (string) get_post_meta($post->ID, self::META_HTML, true);
        $css = (string) get_post_meta($post->ID, self::META_CSS, true);
        $js = (string) get_post_meta($post->ID, self::META_JS, true);
        ?>
        <p>
            Pega el código generado por ChatGPT, Claude u otra herramienta como tres piezas independientes.
            Constructor HUB conserva <code>wp_head()</code>, <code>wp_body_open()</code> y <code>wp_footer()</code>.
        </p>
        <p>
            <label for="hub-landing-html"><strong>HTML de la landing</strong></label><br>
            <textarea id="hub-landing-html" name="hub_landing_html" rows="22" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($html); ?></textarea>
        </p>
        <p class="description">
            No incluir <code>&lt;!doctype&gt;</code>, <code>&lt;html&gt;</code>, <code>&lt;head&gt;</code>, <code>&lt;body&gt;</code>,
            <code>&lt;style&gt;</code> ni <code>&lt;script&gt;</code>.
        </p>
        <p>
            <label for="hub-landing-css"><strong>CSS</strong></label><br>
            <textarea id="hub-landing-css" name="hub_landing_css" rows="18" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($css); ?></textarea>
        </p>
        <p>
            <label for="hub-landing-js"><strong>JavaScript</strong></label><br>
            <textarea id="hub-landing-js" name="hub_landing_js" rows="16" style="width:100%;font-family:monospace;tab-size:2;"><?php echo esc_textarea($js); ?></textarea>
        </p>
        <?php
    }

    public function render_settings_meta_box(WP_Post $post): void
    {
        $use_hub_chrome = get_post_meta($post->ID, self::META_USE_HUB_CHROME, true) === '1';
        $recipient = (string) get_post_meta($post->ID, self::META_RECIPIENT_EMAIL, true);
        $success_message = (string) get_post_meta($post->ID, self::META_SUCCESS_MESSAGE, true);

        if ($recipient === '') {
            $recipient = (string) get_option('admin_email');
        }

        if ($success_message === '') {
            $success_message = 'Gracias. Recibimos tus datos y te contactaremos pronto.';
        }
        ?>
        <p>
            <label>
                <input type="checkbox" name="hub_landing_use_hub_chrome" value="1" <?php checked($use_hub_chrome); ?>>
                Usar Header/Footer HUB globales
            </label>
        </p>
        <p class="description">
            Desactivado = canvas completo para una landing independiente. Activado = Header HUB + Landing + Footer HUB.
        </p>
        <hr>
        <p><strong>Formulario HUB</strong></p>
        <p>
            <label for="hub-landing-recipient">Correo de notificación</label><br>
            <input id="hub-landing-recipient" type="email" name="hub_landing_recipient_email" value="<?php echo esc_attr($recipient); ?>" style="width:100%;">
        </p>
        <p>
            <label for="hub-landing-success">Mensaje de éxito</label><br>
            <textarea id="hub-landing-success" name="hub_landing_success_message" rows="4" style="width:100%;"><?php echo esc_textarea($success_message); ?></textarea>
        </p>
        <p class="description">
            Inserta <code>{{HUB_FORM}}</code> en el HTML para utilizar el formulario nativo. Los envíos quedan registrados en WordPress.
        </p>
        <?php
    }

    public function render_contract_meta_box(): void
    {
        ?>
        <p><strong>Variables disponibles</strong></p>
        <p>
            <code>{{SITE_URL}}</code> · <code>{{HOME_URL}}</code> · <code>{{SITE_NAME}}</code> ·
            <code>{{CURRENT_YEAR}}</code> · <code>{{CUSTOM_LOGO}}</code> · <code>{{CUSTOM_LOGO_URL}}</code> ·
            <code>{{LANDING_URL}}</code> · <code>{{LANDING_TITLE}}</code> · <code>{{HUB_FORM}}</code>
        </p>
        <p><strong>Formulario personalizado</strong></p>
        <p>
            La IA también puede diseñar su propio formulario usando <code>data-hub-landing-form</code> en la etiqueta
            <code>&lt;form&gt;</code>. El campo <code>email</code> y el consentimiento <code>privacy</code> son obligatorios en el endpoint.
            Añadir un honeypot oculto llamado <code>website</code>.
        </p>
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

        update_post_meta(
            $post_id,
            self::META_USE_HUB_CHROME,
            isset($_POST['hub_landing_use_hub_chrome']) ? '1' : '0'
        );

        $recipient = isset($_POST['hub_landing_recipient_email'])
            ? sanitize_email(wp_unslash($_POST['hub_landing_recipient_email']))
            : '';
        update_post_meta($post_id, self::META_RECIPIENT_EMAIL, $recipient);

        $success_message = isset($_POST['hub_landing_success_message'])
            ? sanitize_textarea_field(wp_unslash($_POST['hub_landing_success_message']))
            : '';
        update_post_meta($post_id, self::META_SUCCESS_MESSAGE, $success_message);

        if (current_user_can('unfiltered_html')) {
            $html = isset($_POST['hub_landing_html']) ? wp_unslash($_POST['hub_landing_html']) : '';
            $css = isset($_POST['hub_landing_css']) ? wp_unslash($_POST['hub_landing_css']) : '';
            $js = isset($_POST['hub_landing_js']) ? wp_unslash($_POST['hub_landing_js']) : '';

            update_post_meta($post_id, self::META_HTML, $html);
            update_post_meta($post_id, self::META_CSS, $css);
            update_post_meta($post_id, self::META_JS, $js);
        }
    }

    public function get_html(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::META_HTML, true);
    }

    public function get_css(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::META_CSS, true);
    }

    public function get_js(int $post_id): string
    {
        return (string) get_post_meta($post_id, self::META_JS, true);
    }

    public function uses_hub_chrome(int $post_id): bool
    {
        return get_post_meta($post_id, self::META_USE_HUB_CHROME, true) === '1';
    }

    public function get_recipient_email(int $post_id): string
    {
        $email = sanitize_email((string) get_post_meta($post_id, self::META_RECIPIENT_EMAIL, true));
        return $email !== '' ? $email : sanitize_email((string) get_option('admin_email'));
    }

    public function get_success_message(int $post_id): string
    {
        $message = trim((string) get_post_meta($post_id, self::META_SUCCESS_MESSAGE, true));
        return $message !== '' ? $message : 'Gracias. Recibimos tus datos y te contactaremos pronto.';
    }
}
