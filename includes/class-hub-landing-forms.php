<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Native form pipeline for HUB Landings.
 *
 * Public submissions are validated, rate-limited, stored in WordPress and can
 * trigger a wp_mail notification. The frontend contract is intentionally
 * independent from WPForms/Elementor so the same landing can run on Tibox,
 * Prodata or another WordPress installation.
 */
final class HUB_Tibox_Landing_Forms
{
    public const SUBMISSION_POST_TYPE = 'hub_landing_lead';
    private const REST_NAMESPACE = 'constructor-hub/v1';
    private const REST_ROUTE = '/landing-submit';

    private static ?self $instance = null;
    private HUB_Tibox_Landing_Manager $landings;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(HUB_Tibox_Landing_Manager::instance());
        }

        return self::$instance;
    }

    private function __construct(HUB_Tibox_Landing_Manager $landings)
    {
        $this->landings = $landings;

        add_action('init', [$this, 'register_submission_post_type']);
        add_action('rest_api_init', [$this, 'register_rest_route']);
        add_action('add_meta_boxes_' . self::SUBMISSION_POST_TYPE, [$this, 'add_submission_meta_box']);
    }

    public function register_submission_post_type(): void
    {
        register_post_type(self::SUBMISSION_POST_TYPE, [
            'labels' => [
                'name' => 'Envíos Landings',
                'singular_name' => 'Envío Landing',
                'menu_name' => 'Envíos Landings',
                'edit_item' => 'Ver envío',
                'search_items' => 'Buscar envíos',
                'not_found' => 'No hay envíos de landings.',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'show_in_rest' => false,
            'supports' => [],
            // Leads contain personal data. Only administrators should be able to
            // browse/read/delete them from wp-admin. Public creation happens via
            // the REST handler and does not rely on these UI capabilities.
            'capabilities' => [
                'edit_post' => 'manage_options',
                'read_post' => 'manage_options',
                'delete_post' => 'manage_options',
                'edit_posts' => 'manage_options',
                'edit_others_posts' => 'manage_options',
                'publish_posts' => 'manage_options',
                'read_private_posts' => 'manage_options',
                'delete_posts' => 'manage_options',
                'delete_private_posts' => 'manage_options',
                'delete_published_posts' => 'manage_options',
                'delete_others_posts' => 'manage_options',
                'edit_private_posts' => 'manage_options',
                'edit_published_posts' => 'manage_options',
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap' => false,
        ]);
    }

    public function add_submission_meta_box(): void
    {
        add_meta_box(
            'hub-landing-lead-details',
            'Datos del envío',
            [$this, 'render_submission_meta_box'],
            self::SUBMISSION_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_submission_meta_box(WP_Post $post): void
    {
        $landing_id = absint(get_post_meta($post->ID, '_hub_landing_id', true));
        $submission_id = (string) get_post_meta($post->ID, '_hub_submission_id', true);
        $submitted_at = (string) get_post_meta($post->ID, '_hub_submitted_at', true);
        $fields = json_decode((string) get_post_meta($post->ID, '_hub_fields', true), true);
        $tracking = json_decode((string) get_post_meta($post->ID, '_hub_tracking', true), true);

        $fields = is_array($fields) ? $fields : [];
        $tracking = is_array($tracking) ? $tracking : [];
        ?>
        <table class="widefat striped" style="max-width:1000px;">
            <tbody>
                <tr>
                    <th style="width:180px;">Landing</th>
                    <td>
                        <?php if ($landing_id > 0) : ?>
                            <a href="<?php echo esc_url(get_edit_post_link($landing_id)); ?>"><?php echo esc_html(get_the_title($landing_id)); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
                <tr><th>Fecha</th><td><?php echo esc_html($submitted_at ?: '—'); ?></td></tr>
                <tr><th>Submission ID</th><td><code><?php echo esc_html($submission_id ?: '—'); ?></code></td></tr>
                <?php foreach ($fields as $key => $value) : ?>
                    <?php if ($key === 'privacy') continue; ?>
                    <tr>
                        <th><?php echo esc_html(ucfirst(str_replace('_', ' ', (string) $key))); ?></th>
                        <td><?php echo esc_html(is_array($value) ? implode(', ', $value) : (string) $value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (array_filter($tracking) !== []) : ?>
            <h3>Tracking</h3>
            <table class="widefat striped" style="max-width:1000px;">
                <tbody>
                    <?php foreach ($tracking as $key => $value) : ?>
                        <?php if ((string) $value === '') continue; ?>
                        <tr>
                            <th style="width:180px;"><?php echo esc_html($key); ?></th>
                            <td><?php echo esc_html((string) $value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    public function register_rest_route(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'POST',
            'callback' => [$this, 'handle_submission'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle_submission(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->origin_is_allowed()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Origen no permitido.',
            ], 403);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            $payload = $request->get_params();
        }

        $landing_id = isset($payload['landing_id']) ? absint($payload['landing_id']) : 0;
        if ($landing_id <= 0 || get_post_type($landing_id) !== HUB_Tibox_Landing_Manager::POST_TYPE || get_post_status($landing_id) !== 'publish') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Landing no válida.',
            ], 400);
        }

        // Honeypot: bots receive a neutral success response so they do not learn
        // which anti-spam condition was triggered.
        if (!empty($payload['website'])) {
            return new WP_REST_Response([
                'success' => true,
                'lead_created' => false,
                'message' => $this->landings->get_success_message($landing_id),
            ], 200);
        }

        if (!$this->rate_limit_allows_submission()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Demasiados intentos. Intenta nuevamente en unos minutos.',
            ], 429);
        }

        $email = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
        if ($email === '' || !is_email($email)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ingresa un correo electrónico válido.',
                'errors' => ['email' => 'Correo inválido.'],
            ], 422);
        }

        $privacy = filter_var($payload['privacy'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$privacy) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Debes aceptar el aviso de privacidad.',
                'errors' => ['privacy' => 'Consentimiento requerido.'],
            ], 422);
        }

        $submission_id = isset($payload['submission_id'])
            ? sanitize_text_field((string) $payload['submission_id'])
            : wp_generate_uuid4();

        if ($submission_id === '') {
            $submission_id = wp_generate_uuid4();
        }

        $duplicate_id = $this->find_submission_by_external_id($submission_id);
        if ($duplicate_id > 0) {
            return new WP_REST_Response([
                'success' => true,
                'lead_created' => false,
                'submission_id' => $submission_id,
                'message' => $this->landings->get_success_message($landing_id),
            ], 200);
        }

        $fields = $this->sanitize_form_fields($payload);
        $tracking = $this->sanitize_tracking($payload);
        $name = $fields['name'] ?? '';

        $title_parts = [get_the_title($landing_id) ?: 'Landing'];
        if ($name !== '') {
            $title_parts[] = $name;
        }
        $title_parts[] = $email;
        $title_parts[] = wp_date('Y-m-d H:i');

        $lead_id = wp_insert_post([
            'post_type' => self::SUBMISSION_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => implode(' — ', $title_parts),
        ], true);

        if (is_wp_error($lead_id)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No fue posible registrar tu solicitud.',
            ], 500);
        }

        update_post_meta($lead_id, '_hub_landing_id', $landing_id);
        update_post_meta($lead_id, '_hub_submission_id', $submission_id);
        update_post_meta($lead_id, '_hub_email', $email);
        update_post_meta($lead_id, '_hub_fields', wp_json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        update_post_meta($lead_id, '_hub_tracking', wp_json_encode($tracking, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        update_post_meta($lead_id, '_hub_submitted_at', current_time('mysql'));

        $this->send_notification($landing_id, $lead_id, $fields, $tracking);

        do_action('constructor_hub_landing_lead_created', $lead_id, $landing_id, $fields, $tracking);

        return new WP_REST_Response([
            'success' => true,
            'lead_created' => true,
            'lead_id' => $lead_id,
            'submission_id' => $submission_id,
            'message' => $this->landings->get_success_message($landing_id),
        ], 201);
    }

    public function render_default_form(int $landing_id): string
    {
        $privacy_url = (string) apply_filters(
            'constructor_hub_privacy_url',
            home_url('/aviso-de-privacidad/'),
            $landing_id
        );
        $privacy_url = esc_url($privacy_url);

        ob_start();
        ?>
        <form class="hub-landing-form" data-hub-landing-form novalidate>
            <div class="hub-landing-form__grid">
                <label>
                    <span>Nombre</span>
                    <input type="text" name="name" autocomplete="name" required>
                </label>
                <label>
                    <span>Correo</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Teléfono</span>
                    <input type="tel" name="phone" autocomplete="tel">
                </label>
                <label>
                    <span>Empresa</span>
                    <input type="text" name="company" autocomplete="organization">
                </label>
            </div>
            <label class="hub-landing-form__message">
                <span>Cuéntanos qué necesitas</span>
                <textarea name="message" rows="5"></textarea>
            </label>
            <label class="hub-landing-form__privacy">
                <input type="checkbox" name="privacy" value="1" required>
                <span>Acepto el <a href="<?php echo $privacy_url; ?>" target="_blank" rel="noopener">aviso de privacidad</a>.</span>
            </label>
            <div class="hub-landing-form__honeypot" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <button type="submit" class="hub-landing-form__submit">Enviar consulta</button>
            <p class="hub-landing-form__status" data-hub-form-status aria-live="polite"></p>
            <input type="hidden" name="landing_id" value="<?php echo esc_attr((string) $landing_id); ?>">
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public function endpoint_url(): string
    {
        return rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
    }

    private function origin_is_allowed(): bool
    {
        $home_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($home_host === '') {
            return true;
        }

        foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $server_key) {
            if (empty($_SERVER[$server_key])) {
                continue;
            }

            $value = esc_url_raw(wp_unslash($_SERVER[$server_key]));
            $host = strtolower((string) wp_parse_url($value, PHP_URL_HOST));
            if ($host !== '' && $host !== $home_host) {
                return false;
            }
        }

        return true;
    }

    private function rate_limit_allows_submission(): bool
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
        $hash = hash_hmac('sha256', $ip, wp_salt('auth'));
        $key = 'hub_lp_rate_' . substr($hash, 0, 24);
        $count = (int) get_transient($key);

        if ($count >= 8) {
            return false;
        }

        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private function find_submission_by_external_id(string $submission_id): int
    {
        $posts = get_posts([
            'post_type' => self::SUBMISSION_POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => '_hub_submission_id',
            'meta_value' => $submission_id,
        ]);

        return !empty($posts) ? absint($posts[0]) : 0;
    }

    private function sanitize_form_fields(array $payload): array
    {
        $reserved = [
            'landing_id', 'submission_id', 'privacy', 'website',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'gbraid', 'wbraid', 'landing_url', 'page_title',
        ];

        $fields = [];
        foreach ($payload as $key => $value) {
            $field_key = sanitize_key((string) $key);
            if ($field_key === '' || in_array($field_key, $reserved, true) || count($fields) >= 30) {
                continue;
            }

            if (is_array($value)) {
                $items = array_slice($value, 0, 20);
                $clean = array_map(function ($item): string {
                    return $this->truncate_text(sanitize_text_field((string) $item), 500);
                }, $items);
                $fields[$field_key] = $clean;
                continue;
            }

            $text = (string) $value;
            $fields[$field_key] = $field_key === 'message'
                ? $this->truncate_text(sanitize_textarea_field($text), 4000)
                : $this->truncate_text(sanitize_text_field($text), 1000);
        }

        $fields['email'] = sanitize_email((string) ($payload['email'] ?? ''));
        $fields['privacy'] = true;

        return $fields;
    }

    private function sanitize_tracking(array $payload): array
    {
        $keys = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'gbraid', 'wbraid', 'landing_url', 'page_title',
        ];

        $tracking = [];
        foreach ($keys as $key) {
            $tracking[$key] = $this->truncate_text(
                sanitize_text_field((string) ($payload[$key] ?? '')),
                1000
            );
        }

        return $tracking;
    }

    private function truncate_text(string $value, int $length): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $length);
        }

        return substr($value, 0, $length);
    }

    private function send_notification(int $landing_id, int $lead_id, array $fields, array $tracking): void
    {
        $recipient = $this->landings->get_recipient_email($landing_id);
        if ($recipient === '' || !is_email($recipient)) {
            return;
        }

        $subject = sprintf('[Constructor HUB] Nuevo lead — %s', get_the_title($landing_id));
        $lines = [
            'Se recibió un nuevo formulario desde una Landing HUB.',
            '',
            'Landing: ' . get_the_title($landing_id),
            'URL: ' . get_permalink($landing_id),
            'Lead ID: ' . $lead_id,
            '',
        ];

        foreach ($fields as $key => $value) {
            if ($key === 'privacy') {
                continue;
            }
            $display = is_array($value) ? implode(', ', $value) : (string) $value;
            $lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $display;
        }

        if (!empty($tracking['utm_campaign'])) {
            $lines[] = '';
            $lines[] = 'Campaña: ' . $tracking['utm_campaign'];
        }

        wp_mail($recipient, $subject, implode("\n", $lines));
    }
}
