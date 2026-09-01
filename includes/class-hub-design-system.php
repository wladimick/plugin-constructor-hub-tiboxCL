<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Per site design tokens.
 *
 * The core must never hardcode Tibox's identity, and a component built for
 * tibox.cl must be usable on prodata.cl by changing configuration rather than
 * code. Tokens are the mechanism that makes that true: a design references
 * `var(--hub-primary)` and each site defines what that means.
 */
final class HUB_Tibox_Design_System
{
    public const OPTION = 'hub_tibox_design_system';

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
        add_action('wp_head', [$this, 'print_tokens'], 2);
        add_action('admin_head', [$this, 'print_tokens_admin'], 2);
        add_action('constructor_hub_admin_menu', [$this, 'register_page']);
        add_action('admin_post_hub_tibox_save_design_system', [$this, 'save']);
        add_action('admin_post_hub_tibox_export_design_system', [$this, 'export']);
    }

    /**
     * Token definitions. Defaults are deliberately neutral: a fresh install
     * must not look like any particular client.
     *
     * @return array<string,array{group:string,label:string,default:string,type:string}>
     */
    public static function schema(): array
    {
        return (array) apply_filters('constructor_hub_design_tokens', [
            'primary' => ['group' => 'Color', 'label' => 'Primario', 'default' => '#1f2937', 'type' => 'color'],
            'secondary' => ['group' => 'Color', 'label' => 'Secundario', 'default' => '#4b5563', 'type' => 'color'],
            'accent' => ['group' => 'Color', 'label' => 'Acento', 'default' => '#2563eb', 'type' => 'color'],
            'text' => ['group' => 'Color', 'label' => 'Texto', 'default' => '#111827', 'type' => 'color'],
            'muted' => ['group' => 'Color', 'label' => 'Texto secundario', 'default' => '#6b7280', 'type' => 'color'],
            'background' => ['group' => 'Color', 'label' => 'Fondo', 'default' => '#ffffff', 'type' => 'color'],
            'surface' => ['group' => 'Color', 'label' => 'Superficie', 'default' => '#f9fafb', 'type' => 'color'],
            'border' => ['group' => 'Color', 'label' => 'Borde', 'default' => '#e5e7eb', 'type' => 'color'],

            'font-heading' => ['group' => 'Tipografía', 'label' => 'Titulares', 'default' => 'system-ui, -apple-system, "Segoe UI", sans-serif', 'type' => 'text'],
            'font-body' => ['group' => 'Tipografía', 'label' => 'Texto', 'default' => 'system-ui, -apple-system, "Segoe UI", sans-serif', 'type' => 'text'],
            'font-mono' => ['group' => 'Tipografía', 'label' => 'Monoespaciada', 'default' => 'ui-monospace, SFMono-Regular, monospace', 'type' => 'text'],
            'text-base' => ['group' => 'Tipografía', 'label' => 'Tamaño base', 'default' => '17px', 'type' => 'text'],
            'line-height' => ['group' => 'Tipografía', 'label' => 'Interlineado', 'default' => '1.6', 'type' => 'text'],

            'container' => ['group' => 'Layout', 'label' => 'Ancho de contenedor', 'default' => '1200px', 'type' => 'text'],
            'gutter' => ['group' => 'Layout', 'label' => 'Margen lateral', 'default' => '24px', 'type' => 'text'],
            'section-space' => ['group' => 'Layout', 'label' => 'Espacio entre secciones', 'default' => '80px', 'type' => 'text'],

            'radius-sm' => ['group' => 'Forma', 'label' => 'Radio pequeño', 'default' => '4px', 'type' => 'text'],
            'radius-md' => ['group' => 'Forma', 'label' => 'Radio medio', 'default' => '10px', 'type' => 'text'],
            'radius-lg' => ['group' => 'Forma', 'label' => 'Radio grande', 'default' => '20px', 'type' => 'text'],
            'shadow' => ['group' => 'Forma', 'label' => 'Sombra', 'default' => '0 1px 2px rgba(17,24,39,.08)', 'type' => 'text'],
        ]);
    }

    /** @return array<string,string> */
    public static function tokens(): array
    {
        $stored = get_option(self::OPTION, []);
        $stored = is_array($stored) ? $stored : [];

        $tokens = [];
        foreach (self::schema() as $key => $definition) {
            $value = isset($stored[$key]) ? trim((string) $stored[$key]) : '';
            $tokens[$key] = $value !== '' ? $value : (string) $definition['default'];
        }

        return $tokens;
    }

    public static function css(): string
    {
        $declarations = '';
        foreach (self::tokens() as $key => $value) {
            $declarations .= sprintf('--hub-%s:%s;', $key, $value);
        }

        return ':root{' . $declarations . '}';
    }

    public function print_tokens(): void
    {
        printf(
            "\n<style id=\"constructor-hub-design-system\">%s</style>\n",
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS, not HTML: values are stripped of braces, semicolons and angle brackets on save.
            wp_strip_all_tags(self::css())
        );
    }

    /** The editor should preview a design with the site's own tokens. */
    public function print_tokens_admin(): void
    {
        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->post_type !== HUB_Tibox_Design::POST_TYPE) {
            return;
        }

        $this->print_tokens();
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_settings()
            ? HUB_Tibox_Capabilities::MANAGE_SETTINGS
            : 'manage_options';

        add_submenu_page($parent, 'Design System', 'Design System', $capability, 'constructor-hub-design-system', [$this, 'render']);
    }

    public function render(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $tokens = self::tokens();
        $groups = [];
        foreach (self::schema() as $key => $definition) {
            $groups[$definition['group']][$key] = $definition;
        }
        ?>
        <div class="wrap">
            <h1>Design System</h1>
            <p>
                Los diseños generados por IA deben referenciar estos tokens en lugar de colores y medidas literales.
                Es lo que permite mover un componente entre sitios cambiando configuración y no código.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_save_design_system', 'hub_tibox_design_system_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_save_design_system">

                <?php foreach ($groups as $group => $definitions) : ?>
                    <h2><?php echo esc_html($group); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php foreach ($definitions as $key => $definition) : ?>
                            <tr>
                                <th scope="row">
                                    <label for="hub-token-<?php echo esc_attr($key); ?>"><?php echo esc_html($definition['label']); ?></label>
                                </th>
                                <td>
                                    <input
                                        id="hub-token-<?php echo esc_attr($key); ?>"
                                        type="<?php echo $definition['type'] === 'color' ? 'color' : 'text'; ?>"
                                        name="hub_token[<?php echo esc_attr($key); ?>]"
                                        value="<?php echo esc_attr($tokens[$key]); ?>"
                                        class="<?php echo $definition['type'] === 'color' ? '' : 'regular-text'; ?>">
                                    <code>--hub-<?php echo esc_html($key); ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>

                <h2>Importar</h2>
                <p>
                    <label for="hub-token-import">Pega aquí un Design System exportado desde otro sitio.</label>
                    <textarea id="hub-token-import" name="hub_token_import" rows="5" class="large-text code" placeholder='{"primary":"#0f172a"}'></textarea>
                </p>
                <p class="description">Si este campo trae JSON válido, sobrescribe los valores del formulario.</p>

                <?php submit_button('Guardar Design System'); ?>
            </form>

            <hr>
            <h2>Exportar</h2>
            <p>Copia estos valores en otro sitio para reutilizar los mismos componentes con otra identidad visual.</p>
            <textarea rows="10" class="large-text code" readonly onclick="this.select()"><?php echo esc_textarea((string) wp_json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></textarea>
            <p>
                <a class="button" href="<?php echo esc_url(wp_nonce_url(add_query_arg('action', 'hub_tibox_export_design_system', admin_url('admin-post.php')), 'hub_tibox_export_design_system')); ?>">
                    Descargar JSON
                </a>
            </p>

            <hr>
            <h2>CSS generado</h2>
            <pre style="background:#f6f7f7;padding:14px;overflow-x:auto;"><?php echo esc_html(self::css()); ?></pre>
        </div>
        <?php
    }

    public function save(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_save_design_system', 'hub_tibox_design_system_nonce');

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every value is sanitised against the schema below.
        $input = isset($_POST['hub_token']) ? (array) wp_unslash($_POST['hub_token']) : [];

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload; parsed below and every decoded value is sanitised against the schema.
        $import = isset($_POST['hub_token_import']) ? trim((string) wp_unslash($_POST['hub_token_import'])) : '';
        if ($import !== '') {
            $decoded = json_decode($import, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }

        $tokens = [];
        foreach (self::schema() as $key => $definition) {
            if (!isset($input[$key])) {
                continue;
            }

            $value = sanitize_text_field((string) $input[$key]);
            if ($value === '') {
                continue;
            }

            // A token ends up inside a CSS declaration: braces and semicolons
            // would let a value escape its rule.
            $value = str_replace(['{', '}', ';', '<', '>'], '', $value);
            $tokens[$key] = $value;
        }

        update_option(self::OPTION, $tokens, true);

        wp_safe_redirect(add_query_arg(['page' => 'constructor-hub-design-system', 'updated' => '1'], admin_url('admin.php')));
        exit;
    }

    public function export(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_settings()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_export_design_system');

        $filename = sanitize_file_name(wp_parse_url(home_url('/'), PHP_URL_HOST) . '-hub-design-system.json');

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download body, not HTML; escaping would corrupt it.
        echo (string) wp_json_encode(self::tokens(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
