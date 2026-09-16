<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pre-publication diagnostics for HUB designs.
 *
 * The guard has two levels:
 * - errors: structural problems that would render broken output and block publish;
 * - warnings: SEO/accessibility/security/performance signals that need review but
 *   are not safe to block automatically.
 *
 * It deliberately does not replace Rank Math, an accessibility audit or a CSP.
 * Its job is to stop obvious broken IA packages from becoming live and make
 * risky characteristics visible to the person publishing them.
 */
final class HUB_Tibox_Publish_Guard
{
    private const NOTICE_PREFIX = 'hub_publish_guard_';

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
        add_filter('constructor_hub_publish_allowed', [$this, 'allow_publish'], 10, 4);
        add_action('add_meta_boxes_' . HUB_Tibox_Design::POST_TYPE, [$this, 'add_meta_box'], 50);
        add_action('admin_notices', [$this, 'admin_notice']);
    }

    /**
     * Central publish gate used by Version_Store.
     *
     * @param bool                $allowed Existing decision from another guard.
     * @param array<string,mixed> $version Version about to become live.
     */
    public function allow_publish(bool $allowed, int $design_id, int $version_id, array $version): bool
    {
        if (!$allowed) {
            return false;
        }

        $report = $this->inspect($design_id, $version);
        if ($report['errors'] === []) {
            return true;
        }

        if (is_admin() && get_current_user_id() > 0) {
            set_transient(
                self::NOTICE_PREFIX . get_current_user_id(),
                [
                    'design_id' => $design_id,
                    'version_id' => $version_id,
                    'errors' => $report['errors'],
                    'warnings' => $report['warnings'],
                ],
                120
            );
        }

        return false;
    }

    public function add_meta_box(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            return;
        }

        add_meta_box(
            'hub-publish-guard',
            'Revisión antes de publicar',
            [$this, 'render_meta_box'],
            HUB_Tibox_Design::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        $store = HUB_Tibox_Version_Store::instance();
        $version = $store->get_working($post->ID) ?? $store->get_live($post->ID);

        if ($version === null) {
            echo '<p class="description">Guarda una primera versión para ejecutar la revisión.</p>';
            return;
        }

        $report = $this->inspect($post->ID, $version);

        if ($report['errors'] === [] && $report['warnings'] === []) {
            echo '<p style="color:#00713c;"><strong>Sin hallazgos automáticos.</strong></p>';
            echo '<p class="description">Esto no reemplaza QA visual, SEO ni pruebas de conversión.</p>';
            return;
        }

        if ($report['errors'] !== []) {
            echo '<p style="color:#b32d2e;"><strong>Bloquea publicación</strong></p><ul style="margin-left:18px;list-style:disc;">';
            foreach ($report['errors'] as $message) {
                printf('<li>%s</li>', esc_html($message));
            }
            echo '</ul>';
        }

        if ($report['warnings'] !== []) {
            echo '<p style="color:#996800;"><strong>Revisar</strong></p><ul style="margin-left:18px;list-style:disc;">';
            foreach ($report['warnings'] as $message) {
                printf('<li>%s</li>', esc_html($message));
            }
            echo '</ul>';
        }

        echo '<p class="description">Los errores estructurales bloquean. Las advertencias son informativas.</p>';
    }

    public function admin_notice(): void
    {
        if (!is_admin() || get_current_user_id() <= 0) {
            return;
        }

        $key = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient($key);
        if (!is_array($notice)) {
            return;
        }

        delete_transient($key);
        $errors = array_values(array_filter(array_map('strval', (array) ($notice['errors'] ?? []))));

        if ($errors === []) {
            return;
        }

        echo '<div class="notice notice-error"><p><strong>Constructor HUB bloqueó la publicación.</strong></p><ul style="list-style:disc;margin-left:20px;">';
        foreach ($errors as $message) {
            printf('<li>%s</li>', esc_html($message));
        }
        echo '</ul><p>Corrige los hallazgos, guarda una nueva versión y vuelve a publicar.</p></div>';
    }

    /**
     * Inspect a stored version in its WordPress context.
     *
     * @param array<string,mixed> $version Stored design version.
     * @return array{errors:string[],warnings:string[],metrics:array<string,int>}
     */
    public function inspect(int $design_id, array $version): array
    {
        $type = HUB_Tibox_Design::get_type($design_id);
        $html = (string) ($version['html'] ?? '');
        $js = (string) ($version['js'] ?? '');
        $schema = HUB_Tibox_Content::schema_from_version($version);
        $values = HUB_Tibox_Content::effective_values($design_id, $design_id, $schema);

        return self::inspect_payload($type, $html, $js, $schema, $values);
    }

    /**
     * Pure checker kept args-driven for unit tests.
     *
     * @param array<string,array<string,mixed>> $schema Normalized content schema.
     * @param array<string,mixed>               $values Effective base values.
     * @return array{errors:string[],warnings:string[],metrics:array<string,int>}
     */
    public static function inspect_payload(
        string $type,
        string $html,
        string $js,
        array $schema = [],
        array $values = []
    ): array {
        $errors = [];
        $warnings = [];
        $html = trim($html);
        $type = strtolower($type);
        $is_document = in_array($type, ['page', 'landing'], true);

        if ($html === '' && $type !== 'menu') {
            $errors[] = 'El diseño no contiene HTML utilizable.';
        }

        // Fixed registry placeholders are uppercase. Any braces left that match
        // the contract shape but are not known will print literally on screen.
        if (preg_match_all('/\{\{\s*([A-Z][A-Z0-9_]+)\s*\}\}/', $html, $matches)) {
            $known = class_exists('HUB_Tibox_Variables') ? HUB_Tibox_Variables::names() : [];
            foreach (array_values(array_unique($matches[1])) as $name) {
                if ($known !== [] && !in_array($name, $known, true)) {
                    $errors[] = sprintf('Variable no registrada: {{%s}}.', $name);
                }
            }
        }

        if (class_exists('HUB_Tibox_Content')) {
            foreach (HUB_Tibox_Content::unknown_references($html, $schema) as $reference) {
                $errors[] = sprintf('Campo de contenido no declarado: {{CONTENT.%s}}.', $reference);
            }
        }

        foreach ($schema as $path => $field) {
            if (empty($field['required'])) {
                continue;
            }

            $value = $values[$path] ?? '';
            $empty = is_array($value) ? $value === [] : trim((string) $value) === '' || (string) $value === '0';
            if ($empty) {
                $warnings[] = sprintf('El campo obligatorio "%s" no tiene un valor base.', (string) ($field['label'] ?? $path));
            }
        }

        $h1_count = preg_match_all('/<h1\b[^>]*>/i', $html, $unused);
        $h1_count = $h1_count === false ? 0 : $h1_count;

        if ($is_document && $h1_count !== 1) {
            $warnings[] = $h1_count === 0
                ? 'La página no contiene un H1 en el contenido HUB.'
                : sprintf('La página contiene %d elementos H1; normalmente debe existir uno.', $h1_count);
        }

        $img_count = preg_match_all('/<img\b[^>]*>/i', $html, $image_matches);
        $img_count = $img_count === false ? 0 : $img_count;
        $missing_alt = 0;

        foreach ($image_matches[0] as $tag) {
            if (!preg_match('/\balt\s*=\s*(["\']).*?\1/i', (string) $tag)) {
                ++$missing_alt;
            }
        }

        if ($missing_alt > 0) {
            $warnings[] = sprintf(
                '%d imagen(es) no declaran atributo alt. Las decorativas deben usar alt="".',
                $missing_alt
            );
        }

        $inline_events = preg_match_all('/\son[a-z]+\s*=/i', $html, $unused);
        $inline_events = $inline_events === false ? 0 : $inline_events;
        if ($inline_events > 0) {
            $warnings[] = sprintf('%d manejador(es) JavaScript inline detectados; prefiere script.js y addEventListener().', $inline_events);
        }

        $external_embeds = preg_match_all(
            '/<(?:script|iframe)\b[^>]+\bsrc\s*=\s*(["\'])https?:\/\//i',
            $html,
            $unused
        );
        $external_embeds = $external_embeds === false ? 0 : $external_embeds;
        if ($external_embeds > 0) {
            $warnings[] = sprintf('%d script/iframe externo detectado; revisar privacidad, CSP, rendimiento y proveedor.', $external_embeds);
        }

        $risk_patterns = [
            '/\beval\s*\(/i' => 'eval()',
            '/\bnew\s+Function\s*\(/i' => 'new Function()',
            '/\bdocument\.cookie\b/i' => 'document.cookie',
            '/\bWebSocket\s*\(/i' => 'WebSocket',
            '/\bfetch\s*\(\s*["\']https?:\/\//i' => 'fetch() externo',
            '/\bXMLHttpRequest\b/i' => 'XMLHttpRequest',
        ];

        foreach ($risk_patterns as $pattern => $label) {
            if ($js !== '' && preg_match($pattern, $js)) {
                $warnings[] = sprintf('JavaScript requiere revisión manual: %s detectado.', $label);
            }
        }

        if ($is_document && $html !== '' && preg_match('/<html\b|<head\b|<body\b/i', $html)) {
            $warnings[] = 'El contenido de una Page/Landing HUB incluye html/head/body; en modo HUB normalmente debe ser un fragmento del shell WordPress.';
        }

        return [
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'metrics' => [
                'h1' => $h1_count,
                'images' => $img_count,
                'images_missing_alt' => $missing_alt,
                'inline_events' => $inline_events,
                'external_embeds' => $external_embeds,
            ],
        ];
    }
}
