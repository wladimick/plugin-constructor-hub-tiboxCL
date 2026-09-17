<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe redesign workflow for Pages that already have a HUB design assigned.
 *
 * The important distinction from Site Factory is identity: a redesign must be
 * imported into the SAME hub_design so Page-level CONTENT values keep working.
 * The current renderer and current live version are left untouched until the
 * administrator explicitly publishes the new version.
 */
final class HUB_Tibox_Redesign_Workflow
{
    private const SLUG = 'constructor-hub-redesign';
    private const ACTION = 'hub_tibox_redesign_page';
    private const NONCE_ACTION = 'hub_tibox_redesign_page';
    private const NONCE_FIELD = 'hub_tibox_redesign_nonce';
    private const NOTICE_PREFIX = 'hub_redesign_notice_';

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
        add_action('constructor_hub_admin_menu', [$this, 'register_page'], 6);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_redesign']);
    }

    /**
     * Compare two normalized content schemas.
     *
     * @param array<string,array<string,mixed>> $before Current/live schema.
     * @param array<string,array<string,mixed>> $after  Incoming schema.
     * @return array{
     *   added:string[],
     *   removed:string[],
     *   type_changed:array<string,array{from:string,to:string}>,
     *   required_without_default:string[]
     * }
     */
    public static function compare_schemas(array $before, array $after): array
    {
        $added = array_values(array_diff(array_keys($after), array_keys($before)));
        $removed = array_values(array_diff(array_keys($before), array_keys($after)));
        $type_changed = [];
        $required_without_default = [];

        foreach ($after as $path => $field) {
            if (isset($before[$path])) {
                $from = (string) ($before[$path]['type'] ?? 'text');
                $to = (string) ($field['type'] ?? 'text');
                if ($from !== $to) {
                    $type_changed[$path] = ['from' => $from, 'to' => $to];
                }
            }

            if (
                in_array($path, $added, true)
                && !empty($field['required'])
                && !array_key_exists('default', $field)
            ) {
                $required_without_default[] = $path;
            }
        }

        sort($added);
        sort($removed);
        sort($required_without_default);
        ksort($type_changed);

        return [
            'added' => $added,
            'removed' => $removed,
            'type_changed' => $type_changed,
            'required_without_default' => $required_without_default,
        ];
    }

    /**
     * Schema changes that should force a manual preview instead of one-click
     * publication. Removed optional fields are not blockers because their
     * stored values are retained and simply ignored by the new version.
     *
     * @param array{
     *   added:string[],
     *   removed:string[],
     *   type_changed:array<string,array{from:string,to:string}>,
     *   required_without_default:string[]
     * } $diff
     */
    public static function has_breaking_schema_change(array $diff): bool
    {
        return $diff['type_changed'] !== [] || $diff['required_without_default'] !== [];
    }

    /**
     * Resolve the renderer after the redesign operation.
     *
     * A Page already in HUB stays in HUB even while a new draft is reviewed;
     * it continues serving the previous live version. Theme/Elementor only
     * switches to HUB after an explicit activation request AND a successful
     * publish of the new version.
     */
    public static function renderer_after(
        string $current_mode,
        bool $activate_requested,
        bool $version_published
    ): string {
        if ($current_mode === HUB_Tibox_Page_Assignment::MODE_HUB) {
            return HUB_Tibox_Page_Assignment::MODE_HUB;
        }

        if ($activate_requested && $version_published) {
            return HUB_Tibox_Page_Assignment::MODE_HUB;
        }

        return HUB_Tibox_Page_Assignment::MODE_THEME;
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_edit_design_code()
            ? HUB_Tibox_Capabilities::EDIT_DESIGN_CODE
            : 'manage_options';

        add_submenu_page(
            $parent,
            'Actualizar página con IA',
            'Actualizar página',
            $capability,
            self::SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $notice_key = self::NOTICE_PREFIX . get_current_user_id();
        $notice = get_transient($notice_key);
        delete_transient($notice_key);

        $pages = $this->assigned_pages();
        $can_publish = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        ?>
        <div class="wrap">
            <h1>Actualizar página con IA</h1>
            <p style="max-width:860px;">
                Sube una nueva versión visual para una Page que ya usa un diseño HUB.
                Constructor HUB conserva el <strong>mismo Page ID, permalink, SEO, diseño y contenido editable</strong>.
                El ZIP entra como borrador y la versión pública actual no cambia hasta que decidas publicar.
            </p>

            <?php $this->render_notice($notice); ?>

            <?php if ($pages === []) : ?>
                <div class="notice notice-info inline">
                    <p>
                        Todavía no hay Pages con un diseño HUB asignado.
                        Primero usa <a href="<?php echo esc_url(admin_url('admin.php?page=constructor-hub-factory')); ?>">Crear página con IA</a>.
                    </p>
                </div>
            <?php else : ?>
                <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:960px;">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">

                    <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                        <h2 style="margin-top:0;">1. Página a rediseñar</h2>
                        <p>
                            <label for="hub-redesign-page"><strong>Page de WordPress</strong></label><br>
                            <select id="hub-redesign-page" name="hub_redesign_page_id" required style="min-width:440px;max-width:100%;margin-top:6px;">
                                <option value="0">— Seleccionar Page —</option>
                                <?php foreach ($pages as $page) : ?>
                                    <?php
                                    $design_id = HUB_Tibox_Page_Assignment::instance()->assigned_design_id($page->ID);
                                    $live = HUB_Tibox_Version_Store::instance()->get_live($design_id);
                                    $mode = HUB_Tibox_Page_Assignment::mode($page->ID);
                                    $mode_label = $mode === HUB_Tibox_Page_Assignment::MODE_HUB ? 'HUB' : 'Theme / Elementor';
                                    $version_label = $live !== null ? 'v' . (string) $live['version'] : 'sin versión live';
                                    ?>
                                    <option value="<?php echo esc_attr((string) $page->ID); ?>">
                                        <?php
                                        echo esc_html(sprintf(
                                            '%s — %s — %s',
                                            $page->post_title !== '' ? $page->post_title : ('#' . $page->ID),
                                            $mode_label,
                                            $version_label
                                        ));
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p class="description">
                            La nueva versión se importa sobre el diseño ya asignado; no se crea otro diseño ni se reemplazan los valores CONTENT de esta Page.
                        </p>
                    </div>

                    <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                        <h2 style="margin-top:0;">2. Nueva versión visual</h2>
                        <p>
                            <label for="hub-redesign-package"><strong>ZIP generado por IA</strong></label><br>
                            <input id="hub-redesign-package" type="file" name="hub_redesign_package" accept=".zip,application/zip" required>
                        </p>
                        <p class="description">
                            Antes de crear una versión se ejecuta un preflight: seguridad del ZIP, contrato, tipo Page, variables y content_schema.
                        </p>
                    </div>

                    <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                        <h2 style="margin-top:0;">3. Publicación</h2>
                        <p>
                            <label>
                                <input type="checkbox" name="hub_redesign_publish" value="1" <?php disabled(!$can_publish); ?>>
                                <strong>Publicar la nueva versión después de importarla</strong>
                            </label>
                        </p>
                        <p class="description">
                            Si no lo marcas, la versión queda en borrador y podrás verla mediante preview sin tocar producción.
                            Si el schema cambia de tipo o agrega campos obligatorios sin valor por defecto, HUB forzará revisión manual aunque esta opción esté marcada.
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="hub_redesign_activate" value="1" <?php disabled(!$can_publish); ?>>
                                Activar HUB si esta Page todavía usa Theme / Elementor
                            </label>
                        </p>
                        <p class="description">
                            Si la Page ya usa HUB, seguirá usando HUB durante todo el proceso y servirá la versión live anterior hasta publicar la nueva.
                        </p>

                        <?php if (!$can_publish) : ?>
                            <p class="description">Tu usuario puede importar y previsualizar, pero no publicar ni cambiar el renderer público.</p>
                        <?php endif; ?>
                    </div>

                    <?php submit_button('Importar nueva versión', 'primary'); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_redesign(): void
    {
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        // The admin-post handler verified the redesign nonce before reading form data.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $page_id = isset($_POST['hub_redesign_page_id']) ? absint($_POST['hub_redesign_page_id']) : 0;
        $publish_requested = !empty($_POST['hub_redesign_publish']);
        $activate_requested = !empty($_POST['hub_redesign_activate']);
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($page_id <= 0 || get_post_type($page_id) !== 'page' || !current_user_can('edit_post', $page_id)) {
            $this->notify('error', 'Selecciona una Page válida que puedas editar.');
            $this->redirect();
        }

        $assignment = HUB_Tibox_Page_Assignment::instance();
        $design_id = $assignment->assigned_design_id($page_id);

        if ($design_id <= 0 || HUB_Tibox_Design::get_type($design_id) !== 'page') {
            $this->notify('error', 'La Page seleccionada no tiene un diseño HUB de tipo Page asignado.', $page_id);
            $this->redirect();
        }

        $current_mode = HUB_Tibox_Page_Assignment::mode($page_id);
        $baseline = HUB_Tibox_Version_Store::instance()->get_live($design_id)
            ?? HUB_Tibox_Version_Store::instance()->get_working($design_id);
        $old_schema = HUB_Tibox_Content::schema_from_version($baseline);

        $uploaded = $this->receive_zip();
        if (is_wp_error($uploaded)) {
            $this->notify('error', $uploaded->get_error_message(), $page_id, $design_id);
            $this->redirect();
        }

        $preflight = $this->preflight_package($uploaded['path']);
        if (is_wp_error($preflight)) {
            @unlink($uploaded['path']);
            $this->notify('error', 'Preflight rechazado: ' . $preflight->get_error_message(), $page_id, $design_id);
            $this->redirect();
        }

        $manifest = $preflight['manifest'];
        if ((string) ($manifest['type'] ?? '') !== 'page') {
            @unlink($uploaded['path']);
            $this->notify('error', 'El package debe declarar "type": "page" para actualizar una Page.', $page_id, $design_id);
            $this->redirect();
        }

        $new_schema = (array) ($manifest['content_schema'] ?? []);
        $schema_diff = self::compare_schemas($old_schema, $new_schema);
        $breaking_schema = self::has_breaking_schema_change($schema_diff);

        $result = HUB_Tibox_Package::instance()->import(
            $uploaded['path'],
            $design_id,
            $uploaded['name']
        );
        @unlink($uploaded['path']);

        if (is_wp_error($result)) {
            $this->notify('error', $result->get_error_message(), $page_id, $design_id, 0, $schema_diff);
            $this->redirect();
        }

        $version_id = (int) $result['version_id'];
        $can_publish = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        $version_published = false;

        if ($publish_requested && $can_publish && !$breaking_schema) {
            $version_published = HUB_Tibox_Version_Store::instance()->publish($design_id, $version_id);

            if ($version_published && get_post_status($design_id) !== 'publish') {
                $updated = wp_update_post([
                    'ID' => $design_id,
                    'post_status' => 'publish',
                ], true);
                $version_published = !is_wp_error($updated) && (int) $updated > 0;
            }
        }

        $next_mode = self::renderer_after(
            $current_mode,
            $activate_requested && $can_publish,
            $version_published
        );
        update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_MODE, $next_mode);

        if ($breaking_schema) {
            $message = 'Nueva versión importada como borrador. El content_schema tiene cambios incompatibles y requiere preview/revisión antes de publicar.';
            $type = 'warning';
        } elseif ($publish_requested && !$can_publish) {
            $message = 'Nueva versión importada como borrador. Tu usuario no tiene permiso para publicarla.';
            $type = 'warning';
        } elseif ($publish_requested && !$version_published) {
            $message = 'Nueva versión importada, pero Publish Guard no permitió publicarla. La versión live anterior sigue intacta.';
            $type = 'warning';
        } elseif ($version_published) {
            $message = 'Nueva versión publicada correctamente. El contenido editable y la Page de WordPress se conservaron.';
            $type = 'success';
        } else {
            $message = 'Nueva versión importada como borrador. La versión live y el renderer público no cambiaron.';
            $type = 'success';
        }

        $this->notify($type, $message, $page_id, $design_id, $version_id, $schema_diff, $version_published);
        $this->redirect();
    }

    /** @return WP_Post[] */
    private function assigned_pages(): array
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $assigned = [];
        foreach ($pages as $page) {
            if (!$page instanceof WP_Post) {
                continue;
            }

            if (HUB_Tibox_Page_Assignment::instance()->assigned_design_id($page->ID) > 0) {
                $assigned[] = $page;
            }
        }

        return $assigned;
    }

    /** @return array{path:string,name:string}|WP_Error */
    private function receive_zip()
    {
        // The admin-post handler verified the redesign nonce before calling this helper.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (
            !isset($_FILES['hub_redesign_package'])
            || !is_array($_FILES['hub_redesign_package'])
            || !isset($_FILES['hub_redesign_package']['error'])
            || (int) $_FILES['hub_redesign_package']['error'] !== UPLOAD_ERR_OK
        ) {
            return new WP_Error('hub_redesign_upload', 'No se recibió un archivo ZIP válido.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload() validates the upload payload.
        $file = $_FILES['hub_redesign_package'];
        $original = sanitize_file_name(wp_unslash((string) ($file['name'] ?? 'redesign.zip')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'zip') {
            return new WP_Error('hub_redesign_extension', 'El archivo debe tener extensión .zip.');
        }

        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            'mimes' => [
                'zip' => 'application/zip',
                'zip|x-zip' => 'application/x-zip-compressed',
                'zip|multipart' => 'multipart/x-zip',
                'zip|octet' => 'application/octet-stream',
            ],
        ]);

        if (!is_array($uploaded) || !empty($uploaded['error']) || empty($uploaded['file'])) {
            return new WP_Error(
                'hub_redesign_upload',
                'No fue posible recibir el ZIP: ' . (string) ($uploaded['error'] ?? 'error desconocido')
            );
        }

        return [
            'path' => (string) $uploaded['file'],
            'name' => $original,
        ];
    }

    /**
     * Inspect a package without writing a design/version.
     *
     * @return array{manifest:array<string,mixed>,entry:string}|WP_Error
     */
    private function preflight_package(string $zip_path)
    {
        $staging = trailingslashit(get_temp_dir()) . 'hub-preflight-' . wp_generate_password(10, false, false);
        $entry = HUB_Tibox_Landing_Zip_Importer::instance()->extract_to($zip_path, $staging);

        if (is_wp_error($entry)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return $entry;
        }

        $manifest_path = trailingslashit($staging) . HUB_Tibox_Package::MANIFEST;
        if (!is_file($manifest_path)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_redesign_manifest', 'El ZIP no contiene manifest.json en la raíz.');
        }

        $raw = file_get_contents($manifest_path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_redesign_manifest_json', 'manifest.json no es JSON válido.');
        }

        $manifest = HUB_Tibox_Package::validate_manifest($decoded);
        if (is_wp_error($manifest)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return $manifest;
        }

        $declared_entry = (string) ($manifest['entry'] ?? '');
        if ($declared_entry !== '' && is_file(trailingslashit($staging) . $declared_entry)) {
            $entry = $declared_entry;
        }

        $entry_path = trailingslashit($staging) . $entry;
        if (!is_file($entry_path)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_redesign_entry', 'No se encontró el HTML de entrada declarado por el package.');
        }

        $html = file_get_contents($entry_path);
        if ($html === false) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_redesign_entry_read', 'No fue posible leer el HTML de entrada.');
        }

        $unknown = HUB_Tibox_Variables::unknown_in($html);
        if ($unknown !== []) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error(
                'hub_redesign_variables',
                'El HTML usa variables HUB desconocidas: ' . implode(', ', $unknown) . '.'
            );
        }

        $unknown_content = HUB_Tibox_Content::unknown_references(
            $html,
            (array) ($manifest['content_schema'] ?? [])
        );
        if ($unknown_content !== []) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error(
                'hub_redesign_content_schema',
                'El HTML usa campos CONTENT.* no declarados: ' . implode(', ', $unknown_content) . '.'
            );
        }

        HUB_Tibox_Filesystem::delete_directory($staging);

        return [
            'manifest' => $manifest,
            'entry' => $entry,
        ];
    }

    /** @param mixed $notice */
    private function render_notice($notice): void
    {
        if (!is_array($notice)) {
            return;
        }

        $type = sanitize_html_class((string) ($notice['type'] ?? 'info'));
        $message = (string) ($notice['message'] ?? '');
        $page_id = (int) ($notice['page_id'] ?? 0);
        $design_id = (int) ($notice['design_id'] ?? 0);
        $version_id = (int) ($notice['version_id'] ?? 0);
        $diff = is_array($notice['schema_diff'] ?? null) ? $notice['schema_diff'] : [];
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> inline">
            <p><strong><?php echo esc_html($message); ?></strong></p>

            <?php if ($diff !== []) : ?>
                <?php $this->render_schema_diff($diff); ?>
            <?php endif; ?>

            <p>
                <?php if ($page_id > 0 && $version_id > 0) : ?>
                    <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url(HUB_Tibox_Page_Assignment::instance()->preview_url($page_id, $version_id)); ?>">
                        Previsualizar nueva versión
                    </a>
                <?php endif; ?>
                <?php if ($page_id > 0) : ?>
                    <a class="button" href="<?php echo esc_url((string) get_edit_post_link($page_id, 'url')); ?>">Editar contenido de la Page</a>
                <?php endif; ?>
                <?php if ($design_id > 0) : ?>
                    <a class="button" href="<?php echo esc_url((string) get_edit_post_link($design_id, 'url')); ?>">Historial del diseño</a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    /** @param array<string,mixed> $diff */
    private function render_schema_diff(array $diff): void
    {
        $added = array_values((array) ($diff['added'] ?? []));
        $removed = array_values((array) ($diff['removed'] ?? []));
        $changed = (array) ($diff['type_changed'] ?? []);
        $required = array_values((array) ($diff['required_without_default'] ?? []));

        if ($added === [] && $removed === [] && $changed === [] && $required === []) {
            echo '<p><span style="color:#00713c;">✓ content_schema compatible con la versión anterior.</span></p>';
            return;
        }

        echo '<details style="margin:8px 0 12px;"><summary><strong>Cambios en content_schema</strong></summary><ul style="margin-left:20px;list-style:disc;">';

        if ($added !== []) {
            echo '<li><strong>Nuevos campos:</strong> ' . esc_html(implode(', ', array_map('strval', $added))) . '</li>';
        }
        if ($removed !== []) {
            echo '<li><strong>Campos que deja de usar el diseño:</strong> ' . esc_html(implode(', ', array_map('strval', $removed))) . '. Sus valores almacenados no se eliminan.</li>';
        }
        if ($changed !== []) {
            $labels = [];
            foreach ($changed as $path => $types) {
                if (!is_array($types)) {
                    continue;
                }
                $labels[] = (string) $path . ' (' . (string) ($types['from'] ?? '?') . ' → ' . (string) ($types['to'] ?? '?') . ')';
            }
            echo '<li style="color:#b32d2e;"><strong>Cambio de tipo:</strong> ' . esc_html(implode(', ', $labels)) . '</li>';
        }
        if ($required !== []) {
            echo '<li style="color:#b32d2e;"><strong>Campos obligatorios nuevos sin default:</strong> ' . esc_html(implode(', ', array_map('strval', $required))) . '</li>';
        }

        echo '</ul></details>';
    }

    /** @param array<string,mixed> $schema_diff */
    private function notify(
        string $type,
        string $message,
        int $page_id = 0,
        int $design_id = 0,
        int $version_id = 0,
        array $schema_diff = [],
        bool $published = false
    ): void {
        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            [
                'type' => $type,
                'message' => $message,
                'page_id' => $page_id,
                'design_id' => $design_id,
                'version_id' => $version_id,
                'schema_diff' => $schema_diff,
                'published' => $published,
            ],
            180
        );
    }

    private function redirect(): void
    {
        wp_safe_redirect(add_query_arg('page', self::SLUG, admin_url('admin.php')));
        exit;
    }
}
