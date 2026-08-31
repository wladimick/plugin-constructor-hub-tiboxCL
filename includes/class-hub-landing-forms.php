<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Native form pipeline for HUB Landings.
 */
final class HUB_Tibox_Landing_Forms
{
    private const REST_NAMESPACE = 'constructor-hub/v1';
    private const REST_ROUTE = '/landing-submit';

    private static ?self $instance = null;
    private HUB_Tibox_Landing_Manager $landings;
    private HUB_Tibox_Landing_Lead_Store $store;
    private HUB_Tibox_Landing_Mailer $mailer;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                HUB_Tibox_Landing_Manager::instance(),
                HUB_Tibox_Landing_Lead_Store::instance(),
                HUB_Tibox_Landing_Mailer::instance()
            );
        }
        return self::$instance;
    }

    private function __construct(
        HUB_Tibox_Landing_Manager $landings,
        HUB_Tibox_Landing_Lead_Store $store,
        HUB_Tibox_Landing_Mailer $mailer
    ) {
        $this->landings = $landings;
        $this->store = $store;
        $this->mailer = $mailer;

        add_action('rest_api_init', [$this, 'register_rest_route']);
        add_action('admin_menu', [$this, 'add_leads_page']);
    }

    public function register_rest_route(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_submission'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function endpoint_url(): string
    {
        return rest_url(self::REST_NAMESPACE . self::REST_ROUTE);
    }

    public function handle_submission(WP_REST_Request $request): WP_REST_Response
    {
        if (!$this->origin_is_allowed($request)) {
            return $this->response(false, false, 'Origen no permitido.', 403);
        }

        if (!$this->rate_limit_allows_submission()) {
            return $this->response(false, false, 'Demasiados intentos. Intenta nuevamente en unos minutos.', 429);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->get_body_params();
        }
        $payload = is_array($payload) ? $payload : [];

        $landing_id = absint($payload['landing_id'] ?? 0);
        if (
            $landing_id <= 0 ||
            get_post_type($landing_id) !== HUB_Tibox_Landing_Manager::POST_TYPE ||
            get_post_status($landing_id) !== 'publish'
        ) {
            return $this->response(false, false, 'Landing no válida.', 400);
        }

        $submission_id = sanitize_text_field((string) ($payload['submission_id'] ?? ''));
        if ($submission_id === '') {
            $submission_id = wp_generate_uuid4();
        }

        if (trim((string) ($payload['website'] ?? '')) !== '') {
            return $this->response(
                true,
                false,
                $this->landings->get_success_message($landing_id),
                200,
                $submission_id
            );
        }

        $existing = $this->store->find_by_submission_id($submission_id);
        if ($existing > 0) {
            return $this->response(
                true,
                false,
                $this->landings->get_success_message($landing_id),
                200,
                $submission_id,
                $existing
            );
        }

        $fields = $this->sanitize_form_fields($payload);
        $tracking = $this->sanitize_tracking($payload);
        $errors = $this->validate_fields($landing_id, $fields);

        if ($errors !== []) {
            return new WP_REST_Response([
                'success' => false,
                'lead_created' => false,
                'message' => 'Revisa los campos indicados.',
                'errors' => $errors,
                'submission_id' => $submission_id,
            ], 422);
        }

        $lead_id = $this->store->insert([
            'submission_id' => $submission_id,
            'landing_id' => $landing_id,
            'form_id' => sanitize_key((string) ($payload['form_id'] ?? 'hub-landing-form')),
            'source_key' => 'constructor_hub_landing',
            'fields' => $fields,
            'tracking' => $tracking,
        ]);

        if ($lead_id <= 0) {
            return $this->response(
                false,
                false,
                'No fue posible registrar tu solicitud. Intenta nuevamente.',
                500,
                $submission_id
            );
        }

        $this->mailer->send_lead_notifications($landing_id, $lead_id, $fields, $tracking);

        do_action(
            'constructor_hub_landing_lead_created',
            $lead_id,
            $landing_id,
            $fields,
            $tracking
        );

        do_action('tibox_landing_lead_created', array_merge(
            $fields,
            $tracking,
            [
                'lead_id' => $lead_id,
                'landing_id' => $landing_id,
                'submission_id' => $submission_id,
                'source_key' => 'constructor_hub_landing',
            ]
        ));

        return $this->response(
            true,
            true,
            $this->landings->get_success_message($landing_id),
            201,
            $submission_id,
            $lead_id
        );
    }

    public function render_default_form(int $landing_id): string
    {
        $required = $this->landings->get_required_fields($landing_id);
        $privacy_url = (string) apply_filters(
            'constructor_hub_privacy_url',
            home_url('/aviso-de-privacidad/'),
            $landing_id
        );

        $req = static fn(string $key): string => in_array($key, $required, true) ? ' required' : '';
        $mark = static fn(string $key): string => in_array($key, $required, true) ? ' *' : '';

        ob_start();
        ?>
        <form class="hub-landing-form" data-hub-landing-form novalidate>
            <div class="hub-landing-form__grid">
                <label>
                    <span>Nombre<?php echo esc_html($mark('name')); ?></span>
                    <input type="text" name="name" autocomplete="name"<?php echo $req('name'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
                </label>
                <label>
                    <span>Correo *</span>
                    <input type="email" name="email" autocomplete="email" required>
                </label>
                <label>
                    <span>Teléfono<?php echo esc_html($mark('phone')); ?></span>
                    <input type="tel" name="phone" autocomplete="tel"<?php echo $req('phone'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
                </label>
                <label>
                    <span>Empresa<?php echo esc_html($mark('company')); ?></span>
                    <input type="text" name="company" autocomplete="organization"<?php echo $req('company'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
                </label>
                <label>
                    <span>RUT empresa<?php echo esc_html($mark('rut')); ?></span>
                    <input type="text" name="rut" autocomplete="off"<?php echo $req('rut'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
                </label>
                <label>
                    <span>Área / servicio<?php echo esc_html($mark('area')); ?></span>
                    <input type="text" name="area"<?php echo $req('area'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> >
                </label>
            </div>
            <label class="hub-landing-form__message">
                <span>Cuéntanos qué necesitas<?php echo esc_html($mark('message')); ?></span>
                <textarea name="message" rows="5"<?php echo $req('message'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>></textarea>
            </label>
            <label class="hub-landing-form__privacy">
                <input type="checkbox" name="privacy" value="1" required>
                <span>Acepto el <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener">aviso de privacidad</a>. *</span>
            </label>
            <div class="hub-landing-form__honeypot" aria-hidden="true">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <button type="submit" class="hub-landing-form__submit">Enviar consulta</button>
            <p class="hub-landing-form__status" data-hub-form-status aria-live="polite"></p>
            <input type="hidden" name="landing_id" value="<?php echo esc_attr((string) $landing_id); ?>">
            <input type="hidden" name="form_id" value="hub-landing-<?php echo esc_attr((string) $landing_id); ?>">
        </form>
        <?php
        return (string) ob_get_clean();
    }

    public function add_leads_page(): void
    {
        if (!class_exists('HUB_Tibox_Component_Manager')) {
            return;
        }

        add_submenu_page(
            'edit.php?post_type=' . HUB_Tibox_Component_Manager::POST_TYPE,
            'Leads de Landings',
            'Leads',
            'manage_options',
            'constructor-hub-leads',
            [$this, 'render_leads_page']
        );
    }

    public function render_leads_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        $this->store->maybe_install_table();
        global $wpdb;
        $table = $this->store->table_name();

        $per_page = 50;
        $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $offset = ($page - 1) * $per_page;
        $landing_id = isset($_GET['landing_id']) ? absint($_GET['landing_id']) : 0;

        if ($landing_id > 0) {
            $total = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE landing_id = %d",
                $landing_id
            ));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE landing_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $landing_id,
                $per_page,
                $offset
            ), ARRAY_A);
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $per_page,
                $offset
            ), ARRAY_A);
        }
        $rows = is_array($rows) ? $rows : [];

        $landings = get_posts([
            'post_type' => HUB_Tibox_Landing_Manager::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1>Leads de Landings</h1>
            <p>Fuente de verdad local de los formularios gestionados por Constructor HUB.</p>
            <form method="get" style="margin:16px 0;">
                <input type="hidden" name="post_type" value="<?php echo esc_attr(HUB_Tibox_Component_Manager::POST_TYPE); ?>">
                <input type="hidden" name="page" value="constructor-hub-leads">
                <label for="hub-lead-filter"><strong>Landing:</strong></label>
                <select id="hub-lead-filter" name="landing_id">
                    <option value="0">Todas las landings</option>
                    <?php foreach ($landings as $landing) : ?>
                        <option value="<?php echo esc_attr((string) $landing->ID); ?>" <?php selected($landing_id, $landing->ID); ?>>
                            <?php echo esc_html($landing->post_title ?: ('Landing #' . $landing->ID)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php submit_button('Filtrar', 'secondary', '', false); ?>
            </form>

            <p><strong>Total:</strong> <?php echo esc_html((string) $total); ?> leads.</p>
            <?php $this->render_rows_table($rows); ?>
            <?php $this->render_pagination($page, $per_page, $total, $landing_id); ?>
        </div>
        <?php
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function render_rows_table(array $rows): void
    {
        if ($rows === []) {
            echo '<div class="notice notice-info inline"><p>Aún no hay leads guardados.</p></div>';
            return;
        }

        echo '<div style="overflow-x:auto"><table class="widefat striped" style="min-width:1500px;font-size:12px">';
        echo '<thead><tr><th>Fecha</th><th>Landing</th><th>Nombre</th><th>Email</th><th>Teléfono</th><th>Empresa</th><th>RUT</th><th>Área</th><th>Mensaje</th><th>UTM</th><th>Ads IDs</th><th>Submission</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            echo '<td>' . esc_html((string) $row['created_at']) . '</td>';
            echo '<td>' . esc_html(get_the_title((int) $row['landing_id']) ?: ('#' . (int) $row['landing_id'])) . '</td>';
            echo '<td><strong>' . esc_html((string) $row['name']) . '</strong></td>';
            echo '<td><a href="mailto:' . esc_attr((string) $row['email']) . '">' . esc_html((string) $row['email']) . '</a></td>';
            echo '<td>' . esc_html((string) $row['phone']) . '</td>';
            echo '<td>' . esc_html((string) $row['company']) . '</td>';
            echo '<td>' . esc_html((string) $row['rut']) . '</td>';
            echo '<td>' . esc_html((string) $row['area']) . '</td>';
            echo '<td style="max-width:300px;white-space:normal">' . nl2br(esc_html((string) $row['message'])) . '</td>';
            echo '<td>' . $this->meta_line('source', (string) $row['utm_source']) . $this->meta_line('medium', (string) $row['utm_medium']) . $this->meta_line('campaign', (string) $row['utm_campaign']) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<td>' . $this->meta_line('gclid', (string) $row['gclid']) . $this->meta_line('gbraid', (string) $row['gbraid']) . $this->meta_line('wbraid', (string) $row['wbraid']) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo '<td><code style="word-break:break-all">' . esc_html((string) $row['submission_id']) . '</code></td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    private function render_pagination(int $page, int $per_page, int $total, int $landing_id): void
    {
        $pages = max(1, (int) ceil($total / $per_page));
        if ($pages <= 1) {
            return;
        }

        $args = [
            'post_type' => HUB_Tibox_Component_Manager::POST_TYPE,
            'page' => 'constructor-hub-leads',
        ];
        if ($landing_id > 0) {
            $args['landing_id'] = $landing_id;
        }

        echo '<p style="margin-top:16px">';
        if ($page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(array_merge($args, ['paged' => $page - 1]), admin_url('edit.php'))) . '">← Anterior</a> ';
        }
        echo '<span style="margin:0 10px">Página ' . esc_html((string) $page) . ' de ' . esc_html((string) $pages) . '</span>';
        if ($page < $pages) {
            echo ' <a class="button" href="' . esc_url(add_query_arg(array_merge($args, ['paged' => $page + 1]), admin_url('edit.php'))) . '">Siguiente →</a>';
        }
        echo '</p>';
    }

    /** @return array<string,mixed> */
    private function sanitize_form_fields(array $payload): array
    {
        $reserved = [
            'landing_id', 'submission_id', 'form_id', 'website',
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'gbraid', 'wbraid', 'landing_url', 'landing_path', 'page_title',
        ];

        $fields = [];
        foreach ($payload as $key => $value) {
            $field_key = sanitize_key((string) $key);
            if ($field_key === '' || in_array($field_key, $reserved, true) || count($fields) >= 40) {
                continue;
            }

            if (is_array($value)) {
                $clean = [];
                foreach (array_slice($value, 0, 20) as $item) {
                    $clean[] = $this->truncate(sanitize_text_field((string) $item), 500);
                }
                $fields[$field_key] = $clean;
            } else {
                $text = (string) $value;
                $fields[$field_key] = $field_key === 'message'
                    ? $this->truncate(sanitize_textarea_field($text), 4000)
                    : $this->truncate(sanitize_text_field($text), 1000);
            }
        }

        $fields['email'] = sanitize_email((string) ($payload['email'] ?? ''));
        $fields['privacy'] = filter_var($payload['privacy'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return $fields;
    }

    /** @return array<string,string> */
    private function sanitize_tracking(array $payload): array
    {
        $keys = [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'gclid', 'gbraid', 'wbraid', 'landing_url', 'landing_path', 'page_title',
        ];
        $tracking = [];
        foreach ($keys as $key) {
            $value = (string) ($payload[$key] ?? '');
            $tracking[$key] = $key === 'landing_url'
                ? esc_url_raw($this->truncate($value, 1500))
                : $this->truncate(sanitize_text_field($value), 1500);
        }
        return $tracking;
    }

    /** @param array<string,mixed> $fields @return array<string,string> */
    private function validate_fields(int $landing_id, array $fields): array
    {
        $errors = [];

        if (!is_email((string) ($fields['email'] ?? ''))) {
            $errors['email'] = 'Ingresa un correo electrónico válido.';
        }
        if (empty($fields['privacy'])) {
            $errors['privacy'] = 'Debes aceptar el aviso de privacidad.';
        }

        foreach ($this->landings->get_required_fields($landing_id) as $field) {
            $value = $fields[$field] ?? '';
            if (is_array($value) ? $value === [] : trim((string) $value) === '') {
                $errors[$field] = 'Este campo es obligatorio.';
            }
        }

        $rut = trim((string) ($fields['rut'] ?? ''));
        if ($rut !== '' && !$this->validate_chilean_rut($rut)) {
            $errors['rut'] = 'Ingresa un RUT válido.';
        }

        return $errors;
    }

    private function validate_chilean_rut(string $rut): bool
    {
        $clean = strtoupper((string) preg_replace('/[^0-9K]/', '', $rut));
        if (strlen($clean) < 2) {
            return false;
        }

        $body = substr($clean, 0, -1);
        $provided = substr($clean, -1);
        if ($body === '' || !ctype_digit($body)) {
            return false;
        }

        $sum = 0;
        $multiplier = 2;
        for ($i = strlen($body) - 1; $i >= 0; $i--) {
            $sum += ((int) $body[$i]) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $result = 11 - ($sum % 11);
        $expected = $result === 11 ? '0' : ($result === 10 ? 'K' : (string) $result);
        return hash_equals($expected, $provided);
    }

    private function origin_is_allowed(WP_REST_Request $request): bool
    {
        $site_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
        if ($site_host === '') {
            return true;
        }

        foreach (['origin', 'referer'] as $header) {
            $value = (string) $request->get_header($header);
            if ($value === '') {
                continue;
            }
            $host = strtolower((string) wp_parse_url($value, PHP_URL_HOST));
            if ($host !== '' && $host !== $site_host) {
                return false;
            }
        }
        return true;
    }

    private function rate_limit_allows_submission(): bool
    {
        $ip = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))
            : 'unknown';
        $hash = hash_hmac('sha256', $ip, wp_salt('auth'));
        $key = 'hub_lp_rate_' . substr($hash, 0, 24);
        $count = (int) get_transient($key);

        if ($count >= 8) {
            return false;
        }
        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    private function truncate(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length) : substr($value, 0, $length);
    }

    private function meta_line(string $label, string $value): string
    {
        return $value === '' ? '' : '<div><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</div>';
    }

    private function response(
        bool $success,
        bool $lead_created,
        string $message,
        int $status,
        string $submission_id = '',
        int $lead_id = 0
    ): WP_REST_Response {
        $payload = [
            'success' => $success,
            'lead_created' => $lead_created,
            'message' => $message,
        ];
        if ($submission_id !== '') {
            $payload['submission_id'] = $submission_id;
        }
        if ($lead_id > 0) {
            $payload['lead_id'] = $lead_id;
        }
        return new WP_REST_Response($payload, $status);
    }
}
