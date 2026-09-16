<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editable content layer for HUB designs.
 *
 * HTML/CSS/JS remains immutable and versioned. Editorial values live outside
 * those versions so a visual redesign does not overwrite copy, links or media.
 */
final class HUB_Tibox_Content
{
    public const META_VALUES = '_hub_content_values';

    private const NONCE_ACTION = 'hub_tibox_save_content';
    private const NONCE_FIELD = 'hub_tibox_content_nonce';

    /** @var string[] */
    private const TYPES = [
        'text',
        'textarea',
        'url',
        'media',
        'number',
        'boolean',
        'select',
    ];

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
        add_action('add_meta_boxes_hub_design', [$this, 'add_design_meta_box']);
        add_action('add_meta_boxes_page', [$this, 'add_page_meta_box']);
        add_action('save_post_hub_design', [$this, 'save_design_content'], 30, 2);
        add_action('save_post_page', [$this, 'save_page_content'], 30, 2);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    // -------------------------------------------------------------- contract

    /** @return string[] */
    public static function allowed_types(): array
    {
        return self::TYPES;
    }

    /**
     * Validate and normalize a manifest content schema.
     *
     * @param array<string,mixed> $schema Raw manifest schema.
     * @return array<string,array<string,mixed>>|WP_Error
     */
    public static function validate_schema(array $schema)
    {
        if ($schema === []) {
            return [];
        }

        if (count($schema) > 80) {
            return new WP_Error(
                'hub_content_schema_size',
                'content_schema supera el máximo de 80 campos por diseño.'
            );
        }

        $normalized = [];

        foreach ($schema as $path => $definition) {
            $path = (string) $path;

            if (!self::valid_path($path)) {
                return new WP_Error(
                    'hub_content_schema_path',
                    sprintf(
                        'La ruta de contenido "%s" no es válida. Usa letras minúsculas, números, guiones, guion bajo y puntos.',
                        $path
                    )
                );
            }

            if (!is_array($definition)) {
                return new WP_Error(
                    'hub_content_schema_definition',
                    sprintf('El campo "%s" debe declararse como un objeto JSON.', $path)
                );
            }

            $type = sanitize_key((string) ($definition['type'] ?? 'text'));
            if (!in_array($type, self::TYPES, true)) {
                return new WP_Error(
                    'hub_content_schema_type',
                    sprintf(
                        'El campo "%s" usa el tipo "%s". Tipos válidos: %s.',
                        $path,
                        $type,
                        implode(', ', self::TYPES)
                    )
                );
            }

            $field = [
                'type' => $type,
                'label' => sanitize_text_field((string) ($definition['label'] ?? '')),
                'required' => !empty($definition['required']),
                'group' => sanitize_text_field((string) ($definition['group'] ?? 'Contenido')),
                'help' => sanitize_text_field((string) ($definition['help'] ?? '')),
            ];

            if ($field['label'] === '') {
                $field['label'] = self::label_from_path($path);
            }

            // A select must know its options before its default is sanitized.
            // Otherwise a valid default would be discarded as an unknown value.
            if ($type === 'select') {
                $options = [];
                foreach ((array) ($definition['options'] ?? []) as $value => $option_label) {
                    $value = sanitize_key((string) $value);
                    if ($value === '') {
                        continue;
                    }

                    $options[$value] = sanitize_text_field((string) $option_label);
                }

                if ($options === []) {
                    return new WP_Error(
                        'hub_content_schema_options',
                        sprintf('El campo select "%s" debe declarar al menos una opción.', $path)
                    );
                }

                $field['options'] = $options;
            }

            if (array_key_exists('default', $definition)) {
                $field['default'] = self::sanitize_value($definition['default'], $field);
            }

            $normalized[$path] = $field;
        }

        return $normalized;
    }

    public static function valid_path(string $path): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_.-]{0,79}$/', $path);
    }

    /**
     * Return CONTENT references used in markup, without the CONTENT. prefix.
     *
     * @return string[]
     */
    public static function references_in(string $content): array
    {
        if (!str_contains($content, '{{CONTENT.')) {
            return [];
        }

        if (!preg_match_all('/\{\{\s*CONTENT\.([a-z0-9_.-]+)\s*\}\}/i', $content, $matches)) {
            return [];
        }

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /**
     * Return references used in markup but absent from the declared schema.
     *
     * @param array<string,array<string,mixed>> $schema Normalized schema.
     * @return string[]
     */
    public static function unknown_references(string $content, array $schema): array
    {
        $unknown = [];

        foreach (self::references_in($content) as $reference) {
            if (isset($schema[$reference])) {
                continue;
            }

            if (self::media_reference_base($reference, $schema) !== '') {
                continue;
            }

            $unknown[] = $reference;
        }

        return array_values(array_unique($unknown));
    }

    /**
     * Decode a normalized content schema from one stored design version.
     *
     * @param array<string,mixed>|null $version Version row.
     * @return array<string,array<string,mixed>>
     */
    public static function schema_from_version(?array $version): array
    {
        if ($version === null) {
            return [];
        }

        $manifest = $version['manifest'] ?? null;
        if (is_string($manifest)) {
            $manifest = json_decode($manifest, true);
        }

        if (!is_array($manifest)) {
            return [];
        }

        $validated = self::validate_schema((array) ($manifest['content_schema'] ?? []));

        return is_wp_error($validated) ? [] : $validated;
    }

    // --------------------------------------------------------------- storage

    /**
     * Seed defaults without overwriting content already edited in WordPress.
     *
     * @param array<string,array<string,mixed>> $schema Normalized schema.
     */
    public static function seed_defaults(int $design_id, array $schema): void
    {
        if ($design_id <= 0 || $schema === []) {
            return;
        }

        $current = self::stored_values($design_id, $design_id);
        $changed = false;

        foreach ($schema as $path => $field) {
            if (array_key_exists($path, $current) || !array_key_exists('default', $field)) {
                continue;
            }

            $current[$path] = self::sanitize_value($field['default'], $field);
            $changed = true;
        }

        if ($changed) {
            self::store_values($design_id, $design_id, $current);
        }
    }

    /**
     * Resolve values with precedence: default < design < Page/host override.
     *
     * @param array<string,array<string,mixed>> $schema Normalized schema.
     * @return array<string,mixed>
     */
    public static function effective_values(int $host_id, int $design_id, array $schema): array
    {
        $values = [];

        foreach ($schema as $path => $field) {
            $values[$path] = array_key_exists('default', $field)
                ? $field['default']
                : self::empty_value_for((string) ($field['type'] ?? 'text'));
        }

        foreach (self::stored_values($design_id, $design_id) as $path => $value) {
            if (isset($schema[$path])) {
                $values[$path] = $value;
            }
        }

        if ($host_id > 0 && $host_id !== $design_id) {
            foreach (self::stored_values($host_id, $design_id) as $path => $value) {
                if (isset($schema[$path])) {
                    $values[$path] = $value;
                }
            }
        }

        return $values;
    }

    /** @return array<string,mixed> */
    private static function stored_values(int $host_id, int $design_id): array
    {
        if ($host_id <= 0 || $design_id <= 0) {
            return [];
        }

        $all = get_post_meta($host_id, self::META_VALUES, true);
        if (!is_array($all)) {
            return [];
        }

        $values = $all[(string) $design_id] ?? [];

        return is_array($values) ? $values : [];
    }

    /** @param array<string,mixed> $values Values to store. */
    private static function store_values(int $host_id, int $design_id, array $values): void
    {
        if ($host_id <= 0 || $design_id <= 0) {
            return;
        }

        $all = get_post_meta($host_id, self::META_VALUES, true);
        $all = is_array($all) ? $all : [];
        $all[(string) $design_id] = $values;

        update_post_meta($host_id, self::META_VALUES, $all);
    }

    // ---------------------------------------------------------------- render

    /**
     * Replace CONTENT placeholders for one design/version.
     *
     * @param array<string,mixed> $version Version row.
     */
    public static function replace(string $html, int $design_id, array $version, int $host_id = 0): string
    {
        if ($html === '' || !str_contains($html, '{{CONTENT.')) {
            return $html;
        }

        $schema = self::schema_from_version($version);
        if ($schema === []) {
            return $html;
        }

        $host_id = $host_id > 0 ? $host_id : $design_id;
        $values = self::effective_values($host_id, $design_id, $schema);
        $map = [];

        foreach (self::references_in($html) as $reference) {
            $field_path = $reference;
            $property = '';

            if (!isset($schema[$field_path])) {
                $field_path = self::media_reference_base($reference, $schema);
                if ($field_path !== '') {
                    $property = substr($reference, strlen($field_path) + 1);
                }
            }

            if ($field_path === '' || !isset($schema[$field_path])) {
                continue;
            }

            $field = $schema[$field_path];
            $value = $values[$field_path] ?? self::empty_value_for((string) ($field['type'] ?? 'text'));
            $map['{{CONTENT.' . $reference . '}}'] = self::render_value($value, $field, $property);
        }

        if ($map === []) {
            return $html;
        }

        $html = strtr($html, $map);

        return preg_replace_callback(
            '/\{\{\s*CONTENT\.([a-z0-9_.-]+)\s*\}\}/i',
            static function (array $match) use ($map): string {
                $key = '{{CONTENT.' . strtolower((string) $match[1]) . '}}';
                return $map[$key] ?? (string) $match[0];
            },
            $html
        ) ?? $html;
    }

    /**
     * Escape one value according to its declared field type.
     *
     * @param mixed               $value Raw stored value.
     * @param array<string,mixed> $field Normalized field definition.
     */
    private static function render_value($value, array $field, string $property = ''): string
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'media') {
            $attachment_id = absint($value);
            if ($property === 'id') {
                return (string) $attachment_id;
            }

            if ($attachment_id <= 0) {
                return '';
            }

            if ($property === 'alt') {
                return esc_attr((string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true));
            }

            return esc_url((string) wp_get_attachment_image_url($attachment_id, 'full'));
        }

        if ($type === 'url') {
            return esc_url((string) $value);
        }

        if ($type === 'boolean') {
            return !empty($value) ? '1' : '0';
        }

        if ($type === 'number') {
            return esc_html(is_numeric($value) ? (string) $value : '');
        }

        return esc_html((string) $value);
    }

    // --------------------------------------------------------------- admin UI

    public function add_design_meta_box(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            return;
        }

        add_meta_box(
            'hub-content-fields',
            'Contenido editable HUB',
            [$this, 'render_design_meta_box'],
            HUB_Tibox_Design::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function add_page_meta_box(WP_Post $post): void
    {
        if (!current_user_can('edit_post', $post->ID) || !class_exists('HUB_Tibox_Page_Assignment')) {
            return;
        }

        $design_id = HUB_Tibox_Page_Assignment::instance()->assigned_design_id($post->ID);
        if ($design_id <= 0 || self::admin_schema($design_id) === []) {
            return;
        }

        add_meta_box(
            'hub-content-fields',
            'Contenido HUB de esta página',
            [$this, 'render_page_meta_box'],
            'page',
            'normal',
            'high'
        );
    }

    public function render_design_meta_box(WP_Post $post): void
    {
        $schema = self::admin_schema($post->ID);
        if ($schema === []) {
            echo '<p>Este diseño no declara <code>content_schema</code>. El HTML/CSS/JS sigue siendo versionable, pero no hay campos editoriales separados.</p>';
            return;
        }

        $this->render_editor($post->ID, $post->ID, $schema, false);
    }

    public function render_page_meta_box(WP_Post $post): void
    {
        $design_id = HUB_Tibox_Page_Assignment::instance()->assigned_design_id($post->ID);
        $schema = self::admin_schema($design_id);

        if ($design_id <= 0 || $schema === []) {
            echo '<p>No hay un diseño HUB con campos editables asignado a esta página.</p>';
            return;
        }

        $this->render_editor($post->ID, $design_id, $schema, true);
    }

    /** @param array<string,array<string,mixed>> $schema Normalized schema. */
    private function render_editor(int $host_id, int $design_id, array $schema, bool $page_override): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        printf(
            '<input type="hidden" name="hub_content_design_id" value="%s">',
            esc_attr((string) $design_id)
        );

        $values = self::effective_values($host_id, $design_id, $schema);
        $group = null;

        echo '<div class="hub-content-editor">';
        printf(
            '<p class="description">%s</p>',
            esc_html(
                $page_override
                    ? 'Estos valores pertenecen a esta Page de WordPress. Cambiar o publicar una nueva versión visual del diseño no los reemplaza.'
                    : 'Estos valores son el contenido base del diseño y permanecen separados de las versiones HTML/CSS/JS.'
            )
        );

        foreach ($schema as $path => $field) {
            $next_group = (string) ($field['group'] ?? 'Contenido');
            if ($group !== $next_group) {
                $group = $next_group;
                printf('<h3 style="margin:22px 0 8px;">%s</h3>', esc_html($group));
            }

            $value = $values[$path] ?? self::empty_value_for((string) ($field['type'] ?? 'text'));
            $this->render_field($path, $field, $value);
        }

        echo '</div>';
    }

    /**
     * Render one content field in the WordPress editor.
     *
     * @param array<string,mixed> $field Normalized field definition.
     * @param mixed               $value Current value.
     */
    private function render_field(string $path, array $field, $value): void
    {
        $type = (string) ($field['type'] ?? 'text');
        $label = (string) ($field['label'] ?? self::label_from_path($path));
        $required = !empty($field['required']);
        $name = 'hub_content_values[' . $path . ']';
        $id = 'hub-content-' . sanitize_html_class(str_replace('.', '-', $path));

        echo '<div class="hub-content-field" style="padding:11px 0;border-top:1px solid #e7e9ed;">';
        printf(
            '<label for="%s" style="display:block;font-weight:600;margin-bottom:6px;">%s%s</label>',
            esc_attr($id),
            esc_html($label),
            $required ? ' <span aria-hidden="true">*</span>' : ''
        );

        if ($type === 'textarea') {
            printf(
                '<textarea id="%s" name="%s" rows="4" class="widefat"%s>%s</textarea>',
                esc_attr($id),
                esc_attr($name),
                $required ? ' required' : '',
                esc_textarea((string) $value)
            );
        } elseif ($type === 'url') {
            printf(
                '<input id="%s" name="%s" type="url" class="widefat" value="%s"%s>',
                esc_attr($id),
                esc_attr($name),
                esc_attr((string) $value),
                $required ? ' required' : ''
            );
        } elseif ($type === 'number') {
            printf(
                '<input id="%s" name="%s" type="number" class="small-text" value="%s"%s>',
                esc_attr($id),
                esc_attr($name),
                esc_attr((string) $value),
                $required ? ' required' : ''
            );
        } elseif ($type === 'boolean') {
            printf(
                '<label><input id="%s" name="%s" type="checkbox" value="1" %s> Activado</label>',
                esc_attr($id),
                esc_attr($name),
                checked(!empty($value), true, false)
            );
        } elseif ($type === 'select') {
            printf(
                '<select id="%s" name="%s"%s>',
                esc_attr($id),
                esc_attr($name),
                $required ? ' required' : ''
            );

            if (!$required) {
                echo '<option value="">— Seleccionar —</option>';
            }

            foreach ((array) ($field['options'] ?? []) as $option_value => $option_label) {
                printf(
                    '<option value="%s" %s>%s</option>',
                    esc_attr((string) $option_value),
                    selected((string) $value, (string) $option_value, false),
                    esc_html((string) $option_label)
                );
            }
            echo '</select>';
        } elseif ($type === 'media') {
            $this->render_media_field($id, $name, absint($value));
        } else {
            printf(
                '<input id="%s" name="%s" type="text" class="widefat" value="%s"%s>',
                esc_attr($id),
                esc_attr($name),
                esc_attr((string) $value),
                $required ? ' required' : ''
            );
        }

        if ((string) ($field['help'] ?? '') !== '') {
            printf(
                '<p class="description" style="margin-top:5px;">%s</p>',
                esc_html((string) $field['help'])
            );
        }

        printf(
            '<code style="display:inline-block;margin-top:6px;font-size:11px;">{{CONTENT.%s}}</code>',
            esc_html($path)
        );
        echo '</div>';
    }

    private function render_media_field(string $id, string $name, int $attachment_id): void
    {
        $preview = $attachment_id > 0 ? (string) wp_get_attachment_image_url($attachment_id, 'medium') : '';

        echo '<div class="hub-content-media" data-hub-media-field>';
        printf(
            '<input id="%s" type="hidden" name="%s" value="%s" data-hub-media-input>',
            esc_attr($id),
            esc_attr($name),
            esc_attr((string) $attachment_id)
        );
        echo '<div data-hub-media-preview style="margin:0 0 8px;">';
        if ($preview !== '') {
            printf(
                '<img src="%s" alt="" style="display:block;max-width:220px;height:auto;border-radius:6px;">',
                esc_url($preview)
            );
        }
        echo '</div>';
        echo '<button type="button" class="button" data-hub-media-select>Seleccionar imagen</button> ';
        printf(
            '<button type="button" class="button-link-delete" data-hub-media-remove%s>Quitar</button>',
            $attachment_id > 0 ? '' : ' hidden'
        );
        echo '</div>';
    }

    public function enqueue_admin_assets(): void
    {
        if (!is_admin()) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || !in_array($screen->post_type, ['page', HUB_Tibox_Design::POST_TYPE], true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'constructor-hub-content-fields',
            TIBOX_AI_FRONTEND_URL . 'assets/js/content-fields-admin.js',
            [],
            TIBOX_AI_FRONTEND_VERSION,
            true
        );
    }

    public function save_design_content(int $post_id, WP_Post $post): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            return;
        }

        $this->save_content($post_id, $post_id, $post);
    }

    public function save_page_content(int $post_id, WP_Post $post): void
    {
        if (!current_user_can('edit_post', $post_id) || !class_exists('HUB_Tibox_Page_Assignment')) {
            return;
        }

        if (!$this->request_has_valid_nonce()) {
            return;
        }

        $design_id = HUB_Tibox_Page_Assignment::instance()->assigned_design_id($post_id);
        $posted_design = isset($_POST['hub_content_design_id'])
            ? absint($_POST['hub_content_design_id'])
            : 0;

        if ($design_id <= 0 || $posted_design !== $design_id) {
            return;
        }

        $this->save_content($post_id, $design_id, $post, true);
    }

    private function save_content(int $host_id, int $design_id, WP_Post $post, bool $nonce_checked = false): void
    {
        if (
            (!$nonce_checked && !$this->request_has_valid_nonce())
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || wp_is_post_revision($post->ID)
            || !current_user_can('edit_post', $post->ID)
        ) {
            return;
        }

        $schema = self::admin_schema($design_id);
        if ($schema === []) {
            return;
        }

        // Every field is sanitized below according to its schema type. WPCS
        // cannot infer that recursive field-level sanitization from this read.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $posted = isset($_POST['hub_content_values']) && is_array($_POST['hub_content_values'])
            ? wp_unslash($_POST['hub_content_values'])
            : [];

        $values = [];
        foreach ($schema as $path => $field) {
            $raw = (string) ($field['type'] ?? '') === 'boolean'
                ? ($posted[$path] ?? '0')
                : (array_key_exists($path, $posted) ? $posted[$path] : '');

            $values[$path] = self::sanitize_value($raw, $field);
        }

        self::store_values($host_id, $design_id, $values);
    }

    private function request_has_valid_nonce(): bool
    {
        if (!isset($_POST[self::NONCE_FIELD])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD]));

        return (bool) wp_verify_nonce($nonce, self::NONCE_ACTION);
    }

    /** @return array<string,array<string,mixed>> */
    private static function admin_schema(int $design_id): array
    {
        if ($design_id <= 0) {
            return [];
        }

        $store = HUB_Tibox_Version_Store::instance();
        $version = $store->get_working($design_id) ?? $store->get_live($design_id);

        return self::schema_from_version($version);
    }

    // -------------------------------------------------------------- helpers

    /**
     * Sanitize one value according to its declared field type.
     *
     * @param mixed               $value Raw value.
     * @param array<string,mixed> $field Normalized field definition.
     * @return mixed
     */
    private static function sanitize_value($value, array $field)
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'textarea') {
            return sanitize_textarea_field((string) $value);
        }

        if ($type === 'url') {
            return esc_url_raw((string) $value);
        }

        if ($type === 'media') {
            return absint($value);
        }

        if ($type === 'number') {
            return is_numeric($value) ? (string) $value : '';
        }

        if ($type === 'boolean') {
            return !empty($value) ? '1' : '0';
        }

        if ($type === 'select') {
            $value = sanitize_key((string) $value);
            return array_key_exists($value, (array) ($field['options'] ?? [])) ? $value : '';
        }

        return sanitize_text_field((string) $value);
    }

    /** @return int|string */
    private static function empty_value_for(string $type)
    {
        if ($type === 'media') {
            return 0;
        }

        return $type === 'boolean' ? '0' : '';
    }

    /**
     * Resolve a media field base path from `.url`, `.alt` or `.id` reference.
     *
     * @param array<string,array<string,mixed>> $schema Normalized schema.
     */
    private static function media_reference_base(string $reference, array $schema): string
    {
        foreach (['.url', '.alt', '.id'] as $suffix) {
            if (!str_ends_with($reference, $suffix)) {
                continue;
            }

            $base = substr($reference, 0, -strlen($suffix));
            if (isset($schema[$base]) && (string) ($schema[$base]['type'] ?? '') === 'media') {
                return $base;
            }
        }

        return '';
    }

    private static function label_from_path(string $path): string
    {
        $last = (string) preg_replace('/^.*\./', '', $path);
        $last = str_replace(['-', '_'], ' ', $last);

        return ucfirst($last);
    }
}
