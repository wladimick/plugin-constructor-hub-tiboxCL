<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Design Packages: the contract between an AI and this site.
 *
 * The previous importer accepted any ZIP, hunted for an HTML file and extracted
 * it. That works for "upload my Claude Design export" and for nothing else: no
 * type, no version, no declared variables, no destination. The manifest is what
 * turns a ZIP into a reusable component and what lets the importer answer an AI
 * with an actionable error instead of rendering literal braces on a live page.
 */
final class HUB_Tibox_Package
{
    /** Contract version. Bump only on a breaking change to the manifest. */
    public const CONTRACT = 1;

    public const MANIFEST = 'manifest.json';

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
        add_action('constructor_hub_admin_menu', [$this, 'register_page']);
        add_action('admin_post_hub_tibox_import_package', [$this, 'handle_import']);
        add_action('admin_post_hub_tibox_export_package', [$this, 'handle_export']);
        add_action('constructor_hub_design_deleted', [$this, 'delete_design_packages']);
    }

    // -------------------------------------------------------------- manifest

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|WP_Error
     */
    public static function validate_manifest(array $manifest)
    {
        $contract = (int) ($manifest['hub_package'] ?? 0);
        if ($contract <= 0) {
            return new WP_Error(
                'hub_manifest_contract',
                'El manifest no declara "hub_package". Añade "hub_package": ' . self::CONTRACT . '.'
            );
        }

        if ($contract > self::CONTRACT) {
            return new WP_Error(
                'hub_manifest_future',
                sprintf(
                    'El package usa el contrato %d y este sitio entiende hasta el %d. Actualiza Constructor HUB.',
                    $contract,
                    self::CONTRACT
                )
            );
        }

        $type = sanitize_key((string) ($manifest['type'] ?? ''));
        if (!HUB_Tibox_Design::is_valid_type($type)) {
            return new WP_Error(
                'hub_manifest_type',
                sprintf(
                    'Tipo "%s" no reconocido. Tipos válidos: %s.',
                    (string) ($manifest['type'] ?? ''),
                    implode(', ', HUB_Tibox_Design::type_keys())
                )
            );
        }

        $name = sanitize_text_field((string) ($manifest['name'] ?? ''));
        if ($name === '') {
            return new WP_Error('hub_manifest_name', 'El manifest debe declarar un "name".');
        }

        $declared = array_map('strval', (array) ($manifest['variables'] ?? []));
        $known = HUB_Tibox_Variables::names();
        $unknown = array_values(array_diff($declared, $known));

        if ($unknown !== []) {
            return new WP_Error(
                'hub_manifest_variables',
                sprintf(
                    'El manifest declara variables que este sitio no conoce: %s. Variables disponibles: %s.',
                    implode(', ', $unknown),
                    implode(', ', $known)
                )
            );
        }

        return [
            'hub_package' => $contract,
            'type' => $type,
            'name' => $name,
            'version' => sanitize_text_field((string) ($manifest['version'] ?? '1.0.0')),
            'site' => sanitize_key((string) ($manifest['site'] ?? 'generic')),
            'entry' => sanitize_text_field((string) ($manifest['entry'] ?? 'index.html')),
            'slug' => sanitize_title((string) ($manifest['slug'] ?? $name)),
            'variables' => $declared,
            'tokens' => array_map('sanitize_text_field', (array) ($manifest['tokens'] ?? [])),
            'scope' => sanitize_html_class((string) ($manifest['scope'] ?? '')),
            'mode' => sanitize_key((string) ($manifest['mode'] ?? '')),
            'description' => sanitize_textarea_field((string) ($manifest['description'] ?? '')),
        ];
    }

    // ---------------------------------------------------------------- import

    /**
     * Import a ZIP as a new draft version of a design.
     *
     * A package never overwrites what is published. It lands as a draft that has
     * to be previewed and published explicitly, which is the whole point of the
     * version store.
     *
     * @return array{design_id:int,version_id:int,manifest:array<string,mixed>}|WP_Error
     */
    public function import(string $zip_path, int $design_id = 0, string $original_name = '')
    {
        $staging = trailingslashit(get_temp_dir()) . 'hub-package-' . wp_generate_password(10, false, false);

        $importer = HUB_Tibox_Landing_Zip_Importer::instance();
        $entry = $importer->extract_to($zip_path, $staging);

        if (is_wp_error($entry)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return $entry;
        }

        $manifest = $this->read_manifest($staging);

        if (is_wp_error($manifest)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return $manifest;
        }

        // The manifest is authoritative about the entry point; the filesystem
        // guess is only a fallback for packages written before the contract.
        $manifest_entry = (string) $manifest['entry'];
        if ($manifest_entry !== '' && is_file(trailingslashit($staging) . $manifest_entry)) {
            $entry = $manifest_entry;
        }

        $html = (string) file_get_contents(trailingslashit($staging) . $entry);
        $unknown = HUB_Tibox_Variables::unknown_in($html);

        if ($unknown !== []) {
            HUB_Tibox_Filesystem::delete_directory($staging);

            return new WP_Error(
                'hub_package_variables',
                sprintf(
                    'El HTML usa variables que este sitio no puede resolver: %s. Se renderizarían literalmente en la página.',
                    implode(', ', array_map(static fn(string $name): string => '{{' . $name . '}}', $unknown))
                )
            );
        }

        if ($design_id <= 0) {
            $design_id = $this->create_design($manifest);

            if ($design_id <= 0) {
                HUB_Tibox_Filesystem::delete_directory($staging);
                return new WP_Error('hub_package_design', 'No fue posible crear el diseño de destino.');
            }
        }

        if (get_post_type($design_id) !== HUB_Tibox_Design::POST_TYPE) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_package_target', 'El destino no es un diseño HUB.');
        }

        $store = HUB_Tibox_Version_Store::instance();
        $version_id = $store->create($design_id, [
            'html' => $html,
            'css' => $this->read_optional($staging, 'style.css'),
            'js' => $this->read_optional($staging, 'script.js'),
            'manifest' => $manifest,
            'entry' => $entry,
            'source' => 'zip',
            'label' => $original_name !== '' ? $original_name : (string) $manifest['name'] . ' ' . (string) $manifest['version'],
        ]);

        if ($version_id <= 0) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_package_version', 'No fue posible guardar la versión del package.');
        }

        $target = $this->package_dir($design_id, $version_id);
        HUB_Tibox_Filesystem::delete_directory($target);

        if (!HUB_Tibox_Filesystem::copy_directory($staging, $target)) {
            HUB_Tibox_Filesystem::delete_directory($staging);
            return new WP_Error('hub_package_copy', 'No fue posible almacenar los assets del package.');
        }

        HUB_Tibox_Filesystem::delete_directory($staging);
        $store->update_assets($version_id, $this->relative_dir($design_id, $version_id), $entry);

        if ((string) $manifest['scope'] !== '') {
            update_post_meta($design_id, HUB_Tibox_Design::META_CSS_SCOPE, '1');
        }

        return ['design_id' => $design_id, 'version_id' => $version_id, 'manifest' => $manifest];
    }

    /**
     * @return array<string,mixed>|WP_Error
     */
    private function read_manifest(string $dir)
    {
        $path = trailingslashit($dir) . self::MANIFEST;

        if (!is_file($path)) {
            return new WP_Error(
                'hub_manifest_missing',
                'El ZIP no contiene manifest.json. Consulta docs/AI-PACKAGE-CONTRACT.md para el formato.'
            );
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return new WP_Error('hub_manifest_read', 'No fue posible leer manifest.json.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return new WP_Error(
                'hub_manifest_json',
                'manifest.json no es JSON válido: ' . json_last_error_msg() . '.'
            );
        }

        return self::validate_manifest($decoded);
    }

    private function read_optional(string $dir, string $file): string
    {
        $path = trailingslashit($dir) . $file;
        if (!is_file($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return $contents === false ? '' : $contents;
    }

    /** @param array<string,mixed> $manifest */
    private function create_design(array $manifest): int
    {
        $design_id = wp_insert_post([
            'post_type' => HUB_Tibox_Design::POST_TYPE,
            'post_status' => 'draft',
            'post_title' => (string) $manifest['name'],
            'post_name' => (string) $manifest['slug'],
            'post_excerpt' => (string) $manifest['description'],
        ], true);

        if (is_wp_error($design_id)) {
            return 0;
        }

        update_post_meta((int) $design_id, HUB_Tibox_Design::META_TYPE, (string) $manifest['type']);

        $mode = (string) $manifest['mode'];
        $modes = [
            HUB_Tibox_Design::MODE_HUB,
            HUB_Tibox_Design::MODE_STANDALONE,
            HUB_Tibox_Design::MODE_PACKAGE,
            HUB_Tibox_Design::MODE_LEGACY,
        ];
        update_post_meta(
            (int) $design_id,
            HUB_Tibox_Design::META_RENDER_MODE,
            in_array($mode, $modes, true) ? $mode : HUB_Tibox_Design::MODE_HUB
        );

        return (int) $design_id;
    }

    // ---------------------------------------------------------------- export

    /**
     * Export a published design as a package.
     *
     * Closes the loop: a component improved on the site can go back to an AI, or
     * to another site, in the same shape it arrived.
     */
    public function export(int $design_id, int $version_id = 0): void
    {
        $store = HUB_Tibox_Version_Store::instance();
        $version = $version_id > 0 ? $store->get($version_id) : $store->get_live($design_id);

        if ($version === null || (int) $version['design_id'] !== $design_id) {
            wp_die(esc_html__('No hay una versión que exportar.', 'constructor-hub-tibox'));
        }

        if (!class_exists('ZipArchive')) {
            wp_die(esc_html__('El servidor no tiene habilitada la extensión ZipArchive.', 'constructor-hub-tibox'));
        }

        $post = get_post($design_id);
        $slug = $post instanceof WP_Post && $post->post_name !== '' ? $post->post_name : 'hub-design-' . $design_id;

        $manifest = json_decode((string) $version['manifest'], true);
        $manifest = is_array($manifest) ? $manifest : [];
        $manifest = array_merge([
            'hub_package' => self::CONTRACT,
            'type' => HUB_Tibox_Design::get_type($design_id),
            'name' => $post instanceof WP_Post ? $post->post_title : $slug,
            'slug' => $slug,
            'version' => (string) $version['version'],
            'site' => 'generic',
            'entry' => 'index.html',
            'mode' => HUB_Tibox_Design::get_render_mode($design_id),
            'scope' => HUB_Tibox_Design::uses_css_scope($design_id) ? HUB_Tibox_Design::scope_class($design_id) : '',
        ], $manifest);

        $manifest['version'] = (string) $version['version'];
        $manifest['variables'] = $this->variables_used((string) $version['html']);

        $temp = trailingslashit(get_temp_dir()) . $slug . '-v' . (int) $version['version'] . '.zip';
        @unlink($temp);

        $zip = new ZipArchive();
        if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('No fue posible crear el ZIP.', 'constructor-hub-tibox'));
        }

        $zip->addFromString(self::MANIFEST, (string) wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('index.html', (string) $version['html']);

        if (trim((string) $version['css']) !== '') {
            $zip->addFromString('style.css', (string) $version['css']);
        }

        if (trim((string) $version['js']) !== '') {
            $zip->addFromString('script.js', (string) $version['js']);
        }

        $assets = $this->package_dir($design_id, (int) $version['id']) . '/assets';
        if (is_dir($assets)) {
            $this->add_directory($zip, $assets, 'assets');
        }

        $zip->close();

        nocache_headers();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name(basename($temp)) . '"');
        header('Content-Length: ' . (string) filesize($temp));

        readfile($temp);
        @unlink($temp);
        exit;
    }

    private function add_directory(ZipArchive $zip, string $dir, string $prefix): void
    {
        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            if (is_link($path)) {
                continue;
            }

            if (is_dir($path)) {
                $this->add_directory($zip, $path, $prefix . '/' . $item);
                continue;
            }

            $zip->addFile($path, $prefix . '/' . $item);
        }
    }

    /** @return string[] */
    private function variables_used(string $html): array
    {
        if (!preg_match_all('/\{\{\s*([A-Z0-9_]+)\s*\}\}/', $html, $matches)) {
            return [];
        }

        return array_values(array_unique($matches[1]));
    }

    // ----------------------------------------------------------------- admin

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_edit_design_code()
            ? HUB_Tibox_Capabilities::EDIT_DESIGN_CODE
            : 'manage_options';

        add_submenu_page($parent, 'Importar package', 'Importar ZIP', $capability, 'constructor-hub-import', [$this, 'render_page']);
    }

    public function render_page(): void
    {
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $notice = get_transient('hub_package_notice_' . get_current_user_id());
        delete_transient('hub_package_notice_' . get_current_user_id());
        ?>
        <div class="wrap">
            <h1>Importar package</h1>
            <p>
                Sube el ZIP exportado por Claude Design, ChatGPT u otra IA. El package se guarda como
                <strong>versión borrador</strong>: hay que previsualizarlo y publicarlo explícitamente, de modo que
                importar nunca reemplaza lo que está en producción.
            </p>

            <?php if (is_array($notice)) : ?>
                <div class="notice notice-<?php echo esc_attr((string) $notice['type']); ?>">
                    <p><?php echo esc_html((string) $notice['message']); ?></p>
                    <?php if (!empty($notice['preview'])) : ?>
                        <p>
                            <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url((string) $notice['preview']); ?>">Ver preview</a>
                            <a class="button" href="<?php echo esc_url((string) $notice['edit']); ?>">Abrir el diseño</a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('hub_tibox_import_package', 'hub_tibox_import_nonce'); ?>
                <input type="hidden" name="action" value="hub_tibox_import_package">

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="hub-package-file">Archivo ZIP</label></th>
                        <td><input id="hub-package-file" type="file" name="hub_package_file" accept=".zip,application/zip" required></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="hub-package-design">Destino</label></th>
                        <td>
                            <select id="hub-package-design" name="hub_design_id">
                                <option value="0">Crear un diseño nuevo</option>
                                <?php foreach (HUB_Tibox_Design::types() as $type => $definition) : ?>
                                    <?php foreach (HUB_Tibox_Design::list_by_type($type, 'any') as $design) : ?>
                                        <option value="<?php echo esc_attr((string) $design->ID); ?>">
                                            <?php echo esc_html($definition['label'] . ' — ' . $design->post_title); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                Elegir un diseño existente añade una versión nueva a su historial en lugar de crear otro objeto.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Importar package'); ?>
            </form>

            <hr>
            <h2>Formato esperado</h2>
            <pre style="background:#f6f7f7;padding:16px;overflow-x:auto;">package.zip
├── manifest.json
├── index.html
├── style.css
├── script.js   (opcional)
└── assets/     (opcional)</pre>

            <h3>manifest.json</h3>
            <pre style="background:#f6f7f7;padding:16px;overflow-x:auto;"><?php echo esc_html((string) wp_json_encode([
                'hub_package' => self::CONTRACT,
                'type' => 'hero',
                'name' => 'Hero servicios TI',
                'slug' => 'hero-servicios-ti',
                'version' => '1.0.0',
                'site' => 'generic',
                'entry' => 'index.html',
                'scope' => 'hub-scope-hero-servicios-ti',
                'variables' => ['SITE_NAME', 'HUB_FORM'],
                'tokens' => ['--hub-primary', '--hub-container'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>

            <p>
                Tipos válidos: <code><?php echo esc_html(implode('</code>, <code>', HUB_Tibox_Design::type_keys())); ?></code>.
                El contrato completo está en <code>docs/AI-PACKAGE-CONTRACT.md</code>.
            </p>
        </div>
        <?php
    }

    public function handle_import(): void
    {
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer('hub_tibox_import_package', 'hub_tibox_import_nonce');

        $design_id = isset($_POST['hub_design_id']) ? absint($_POST['hub_design_id']) : 0;

        if (
            !isset($_FILES['hub_package_file'])
            || !is_array($_FILES['hub_package_file'])
            || !isset($_FILES['hub_package_file']['error'])
            || (int) $_FILES['hub_package_file']['error'] !== UPLOAD_ERR_OK
        ) {
            $this->notify('error', 'No se recibió ningún archivo ZIP.');
            $this->back();
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- handed to wp_handle_upload(), which validates it.
        $file = $_FILES['hub_package_file'];
        $original = sanitize_file_name(wp_unslash((string) $file['name']));

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
            $this->notify('error', 'No fue posible recibir el ZIP: ' . (string) ($uploaded['error'] ?? 'error desconocido'));
            $this->back();
        }

        $result = $this->import((string) $uploaded['file'], $design_id, $original);
        @unlink((string) $uploaded['file']);

        if (is_wp_error($result)) {
            $this->notify('error', $result->get_error_message());
            $this->back();
        }

        $this->notify(
            'success',
            sprintf(
                'Package importado como versión borrador de "%s". Previsualízalo antes de publicar.',
                (string) $result['manifest']['name']
            ),
            HUB_Tibox_Preview::url($result['design_id'], $result['version_id']),
            (string) get_edit_post_link($result['design_id'], 'url')
        );

        $this->back();
    }

    public function handle_export(): void
    {
        $design_id = isset($_GET['design_id']) ? absint($_GET['design_id']) : 0;
        $version_id = isset($_GET['version_id']) ? absint($_GET['version_id']) : 0;

        check_admin_referer('hub_tibox_export_package_' . $design_id);

        if (!current_user_can('edit_post', $design_id)) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        $this->export($design_id, $version_id);
    }

    public function delete_design_packages(int $design_id): void
    {
        HUB_Tibox_Filesystem::delete_directory($this->design_packages_dir($design_id));
    }

    public function package_dir(int $design_id, int $version_id): string
    {
        return $this->design_packages_dir($design_id) . '/' . $version_id;
    }

    public function package_url(int $design_id, int $version_id): string
    {
        $upload = wp_upload_dir();

        return trailingslashit(trailingslashit((string) $upload['baseurl']) . 'constructor-hub/packages/' . $design_id . '/' . $version_id);
    }

    private function design_packages_dir(int $design_id): string
    {
        $upload = wp_upload_dir();

        return trailingslashit((string) $upload['basedir']) . 'constructor-hub/packages/' . $design_id;
    }

    private function relative_dir(int $design_id, int $version_id): string
    {
        return 'constructor-hub/packages/' . $design_id . '/' . $version_id;
    }

    private function notify(string $type, string $message, string $preview = '', string $edit = ''): void
    {
        set_transient('hub_package_notice_' . get_current_user_id(), [
            'type' => $type,
            'message' => $message,
            'preview' => $preview,
            'edit' => $edit,
        ], 120);
    }

    private function back(): void
    {
        wp_safe_redirect(add_query_arg('page', 'constructor-hub-import', admin_url('admin.php')));
        exit;
    }
}
