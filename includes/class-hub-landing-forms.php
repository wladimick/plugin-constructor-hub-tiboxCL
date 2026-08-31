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
    private const LEGACY_NAMESPACE = 'tibox/v1';
    private const LEGACY_ROUTE = '/lead';
    public const OPTION_IP_HEADER = 'hub_tibox_client_ip_header';

    private static ?self $instance = null;
    private HUB_Tibox_Landing_Lead_Store $store;
    private HUB_Tibox_Landing_Mailer $mailer;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(
                HUB_Tibox_Landing_Lead_Store::instance(),
                HUB_Tibox_Landing_Mailer::instance()
            );
        }
        return self::$instance;
    }

    private function __construct(
        HUB_Tibox_Landing_Lead_Store $store,
        HUB_Tibox_Landing_Mailer $mailer
    ) {
        $this->store = $store;
        $this->mailer = $mailer;

        add_action('rest_api_init', [$this, 'register_rest_route']);
    }

    public function register_rest_route(): void
    {
        register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_submission'],
            'permission_callback' => '__return_true',
        ]);

        // Compatibility alias for the historical WPCode endpoint consumed by the
        // MVP `home-ai` template. Keeping it registered here means retiring the
        // WPCode snippet no longer silently breaks that form.
        register_rest_route(self::LEGACY_NAMESPACE, self::LEGACY_ROUTE, [
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

        if (!$this->attempt_budget_allows()) {
            return $this->response(false, false, 'Demasiados intentos. Intenta nuevamente en unos minutos.', 429);
        }

        $payload = $request->get_json_params();
        if (!is_array($payload) || $payload === []) {
            $payload = $request->get_body_params();
        }
        $payload = is_array($payload) ? $payload : [];

        $landing_id = absint($payload['landing_id'] ?? 0);
        if ($landing_id <= 0 || get_post_status($landing_id) !== 'publish') {
            return $this->response(false, false, 'Origen del formulario no válido.', 400);
        }

        $host_type = (string) get_post_type($landing_id);
        $source_key = in_array($host_type, [HUB_Tibox_Design::POST_TYPE, 'hub_landing'], true)
            ? 'constructor_hub_landing'
            : 'constructor_hub_page';

        // The submission id is client supplied. The column is varchar(100) and a
        // longer value would abort the INSERT on MySQL strict mode.
        $submission_id = $this->truncate(
            sanitize_text_field((string) ($payload['submission_id'] ?? '')),
            100
        );
        if ($submission_id === '') {
            $submission_id = wp_generate_uuid4();
        }

        if (trim((string) ($payload['website'] ?? '')) !== '') {
            return $this->response(
                true,
                false,
                HUB_Tibox_Form_Config::success_message($landing_id),
                200,
                $submission_id
            );
        }

        $existing = $this->store->find_by_submission_id($submission_id);
        if ($existing > 0) {
            return $this->response(
                true,
                false,
                HUB_Tibox_Form_Config::success_message($landing_id),
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

        if (!$this->creation_budget_allows((string) ($fields['email'] ?? ''))) {
            return $this->response(
                false,
                false,
                'Ya registramos varias solicitudes recientes con estos datos. Intenta nuevamente más tarde.',
                429,
                $submission_id
            );
        }

        $lead_id = $this->store->insert([
            'submission_id' => $submission_id,
            'landing_id' => $landing_id,
            'form_id' => sanitize_key((string) ($payload['form_id'] ?? 'hub-landing-form')),
            'source_key' => $source_key,
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

        $this->register_created_submission((string) ($fields['email'] ?? ''));

        $this->mailer->send_lead_notifications($landing_id, $lead_id, $fields, $tracking);

        do_action(
            'constructor_hub_landing_lead_created',
            $lead_id,
            $landing_id,
            $fields,
            $tracking
        );

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- documented bridge for the historical WPCode integrations; see docs/CHANGELOG.md.
        do_action('tibox_landing_lead_created', array_merge(
            $fields,
            $tracking,
            [
                'lead_id' => $lead_id,
                'landing_id' => $landing_id,
                'submission_id' => $submission_id,
                'source_key' => $source_key,
            ]
        ));

        return $this->response(
            true,
            true,
            HUB_Tibox_Form_Config::success_message($landing_id),
            201,
            $submission_id,
            $lead_id
        );
    }

    public function render_default_form(int $landing_id): string
    {
        $required = HUB_Tibox_Form_Config::required_fields($landing_id);
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

    public function render_leads_page(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_leads()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
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
            'post_type' => array_values(array_filter([
                class_exists('HUB_Tibox_Design') ? HUB_Tibox_Design::POST_TYPE : '',
                'hub_landing',
            ])),
            'post_status' => 'any',
            'posts_per_page' => 200,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
        ?>
        <div class="wrap">
            <h1>Leads de Landings</h1>
            <p>Fuente de verdad local de los formularios gestionados por Constructor HUB.</p>
            <form method="get" style="margin:16px 0;">
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

        $args = ['page' => 'constructor-hub-leads'];
        if ($landing_id > 0) {
            $args['landing_id'] = $landing_id;
        }

        echo '<p style="margin-top:16px">';
        if ($page > 1) {
            echo '<a class="button" href="' . esc_url(add_query_arg(array_merge($args, ['paged' => $page - 1]), admin_url('admin.php'))) . '">← Anterior</a> ';
        }
        echo '<span style="margin:0 10px">Página ' . esc_html((string) $page) . ' de ' . esc_html((string) $pages) . '</span>';
        if ($page < $pages) {
            echo ' <a class="button" href="' . esc_url(add_query_arg(array_merge($args, ['paged' => $page + 1]), admin_url('admin.php'))) . '">Siguiente →</a>';
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

        foreach (HUB_Tibox_Form_Config::required_fields($landing_id) as $field) {
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

    /**
     * Reject only requests that positively declare a foreign origin.
     *
     * The previous implementation compared the raw host against home_url() and
     * rejected `www.` variants and site aliases, which silently dropped real
     * leads on paid campaigns. A missing header is not treated as proof of
     * anything: spam control belongs to the honeypot and the rate budgets.
     */
    private function origin_is_allowed(WP_REST_Request $request): bool
    {
        $allowed = $this->allowed_hosts();
        if ($allowed === []) {
            return true;
        }

        foreach (['origin', 'referer'] as $header) {
            $value = (string) $request->get_header($header);
            if ($value === '' || strtolower($value) === 'null') {
                continue;
            }

            $host = $this->normalize_host((string) wp_parse_url($value, PHP_URL_HOST));
            if ($host !== '' && !in_array($host, $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return string[] */
    private function allowed_hosts(): array
    {
        $hosts = [];
        foreach ([home_url('/'), site_url('/'), (string) get_option('siteurl')] as $url) {
            $host = $this->normalize_host((string) wp_parse_url((string) $url, PHP_URL_HOST));
            if ($host !== '') {
                $hosts[] = $host;
            }
        }

        $hosts = array_map(
            fn($host): string => $this->normalize_host((string) $host),
            (array) apply_filters('constructor_hub_allowed_origins', $hosts)
        );

        return array_values(array_unique(array_filter($hosts)));
    }

    private function normalize_host(string $host): string
    {
        $host = strtolower(trim($host));
        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    /**
     * Best effort client IP.
     *
     * REMOTE_ADDR is the only value that cannot be spoofed, but behind a CDN it
     * is the proxy address and would rate limit the whole site as one visitor.
     * The forwarding header is therefore opt-in: it must be configured by an
     * administrator who knows the site sits behind that proxy.
     */
    private function client_ip(): string
    {
        $header = (string) apply_filters(
            'constructor_hub_client_ip_header',
            (string) get_option(self::OPTION_IP_HEADER, '')
        );

        if ($header !== '') {
            $header = strtoupper(str_replace('-', '_', $header));
            if (!str_starts_with($header, 'HTTP_') && $header !== 'REMOTE_ADDR') {
                $header = 'HTTP_' . $header;
            }
            if (!empty($_SERVER[$header])) {
                $raw = sanitize_text_field(wp_unslash((string) $_SERVER[$header]));
                foreach (explode(',', $raw) as $candidate) {
                    $candidate = trim($candidate);
                    if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                        return $candidate;
                    }
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : 'unknown';
    }

    private function budget_key(string $prefix, string $value): string
    {
        return 'hub_lp_' . $prefix . '_' . substr(hash_hmac('sha256', $value, wp_salt('auth')), 0, 24);
    }

    /**
     * Ceiling for raw attempts. Deliberately generous: a visitor correcting a
     * RUT or an email must never be locked out of the form.
     */
    private function attempt_budget_allows(): bool
    {
        $max = (int) apply_filters('constructor_hub_max_attempts_per_window', 60);
        $key = $this->budget_key('att', $this->client_ip());
        $count = (int) get_transient($key);

        if ($count >= $max) {
            return false;
        }

        set_transient($key, $count + 1, 10 * MINUTE_IN_SECONDS);
        return true;
    }

    /**
     * Budget for leads that are actually created. The email based key keeps
     * working when every visitor shares a proxy address.
     */
    private function creation_budget_allows(string $email): bool
    {
        $max_ip = (int) apply_filters('constructor_hub_max_leads_per_ip', 12);
        $max_email = (int) apply_filters('constructor_hub_max_leads_per_email', 3);

        if ((int) get_transient($this->budget_key('ip', $this->client_ip())) >= $max_ip) {
            return false;
        }

        $email = strtolower(trim($email));
        if ($email !== '' && (int) get_transient($this->budget_key('mail', $email)) >= $max_email) {
            return false;
        }

        return true;
    }

    private function register_created_submission(string $email): void
    {
        $ip_key = $this->budget_key('ip', $this->client_ip());
        set_transient($ip_key, ((int) get_transient($ip_key)) + 1, 10 * MINUTE_IN_SECONDS);

        $email = strtolower(trim($email));
        if ($email === '') {
            return;
        }

        $mail_key = $this->budget_key('mail', $email);
        set_transient($mail_key, ((int) get_transient($mail_key)) + 1, HOUR_IN_SECONDS);
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
