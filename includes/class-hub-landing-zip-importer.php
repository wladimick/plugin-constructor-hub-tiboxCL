<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Secure importer for AI project ZIP packages (Claude Design, etc.).
 */
final class HUB_Tibox_Landing_Zip_Importer
{
    private const META_FOLDER = '_hub_landing_zip_folder';
    private const META_ENTRY = '_hub_landing_zip_entry';
    private const META_ORIGINAL_NAME = '_hub_landing_zip_original_name';
    private const UPLOAD_SUBDIR = 'constructor-hub-landings';
    private const NONCE_ACTION = 'hub_tibox_landing_zip';
    private const NONCE_FIELD = 'hub_tibox_landing_zip_nonce';

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
        add_action('post_edit_form_tag', [$this, 'add_multipart_enctype']);
        add_action('add_meta_boxes_' . HUB_Tibox_Landing_Manager::POST_TYPE, [$this, 'add_meta_box']);
        add_action('save_post_' . HUB_Tibox_Landing_Manager::POST_TYPE, [$this, 'handle_upload'], 30);
        add_action('template_redirect', [$this, 'maybe_render_package'], -20);
        add_action('admin_notices', [$this, 'display_stored_errors']);
    }

    public function add_multipart_enctype(WP_Post $post): void
    {
        if ($post->post_type === HUB_Tibox_Landing_Manager::POST_TYPE) {
            echo ' enctype="multipart/form-data"';
        }
    }

    public function add_meta_box(): void
    {
        add_meta_box(
            'hub-landing-zip',
            'Importar proyecto ZIP (Claude / IA)',
            [$this, 'render_meta_box'],
            HUB_Tibox_Landing_Manager::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        $entry = (string) get_post_meta($post->ID, self::META_ENTRY, true);
        $original = (string) get_post_meta($post->ID, self::META_ORIGINAL_NAME, true);
        ?>
        <p>
            Sube el <strong>Project archive (.zip)</strong> exportado por Claude Design u otra IA.
            Constructor HUB valida el paquete antes de extraerlo y bloquea PHP, ejecutables, path traversal y archivos de configuración.
        </p>
        <p><input type="file" name="hub_landing_zip_file" accept=".zip,application/zip"></p>
        <p class="description">Límites por defecto: 25 MB comprimidos, 120 MB descomprimidos, 600 archivos y 20 MB por archivo. Son filtrables por código.</p>
        <?php if ($entry !== '') : ?>
            <div style="padding:12px;border:1px solid #2271b1;background:#f0f6fc;border-radius:6px;">
                <strong>Package cargado</strong><br>
                <?php if ($original !== '') : ?>Archivo: <code><?php echo esc_html($original); ?></code><br><?php endif; ?>
                Entrada: <code><?php echo esc_html($entry); ?></code><br>
                <label style="display:block;margin-top:8px;"><input type="checkbox" name="hub_landing_zip_remove" value="1"> Eliminar package actual</label>
            </div>
        <?php endif; ?>
        <?php
    }

    public function handle_upload(int $post_id): void
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
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!empty($_POST['hub_landing_zip_remove'])) {
            $this->delete_existing_folder($post_id);
            delete_post_meta($post_id, self::META_FOLDER);
            delete_post_meta($post_id, self::META_ENTRY);
            delete_post_meta($post_id, self::META_ORIGINAL_NAME);
            return;
        }

        if (
            !isset($_FILES['hub_landing_zip_file']) ||
            !is_array($_FILES['hub_landing_zip_file']) ||
            (int) $_FILES['hub_landing_zip_file']['error'] === UPLOAD_ERR_NO_FILE
        ) {
            return;
        }

        $file = $_FILES['hub_landing_zip_file'];
        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            $this->add_admin_error('Error de subida ZIP: código ' . (int) $file['error'] . '.');
            return;
        }

        $max_zip = (int) apply_filters('constructor_hub_zip_max_compressed_bytes', 25 * MB_IN_BYTES, $post_id);
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $max_zip) {
            $this->add_admin_error('El ZIP supera el tamaño permitido o está vacío.');
            return;
        }

        $original = sanitize_file_name(wp_unslash((string) $file['name']));
        if (strtolower(pathinfo($original, PATHINFO_EXTENSION)) !== 'zip') {
            $this->add_admin_error('El archivo debe tener extensión .zip.');
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded = wp_handle_upload($file, [
            'test_form' => false,
            // Windows and several browsers announce ZIP files with other MIME
            // strings. The real validation is the content inspection below.
            'mimes' => [
                'zip' => 'application/zip',
                'zip|x-zip' => 'application/x-zip-compressed',
                'zip|multipart' => 'multipart/x-zip',
                'zip|octet' => 'application/octet-stream',
            ],
        ]);

        if (!is_array($uploaded) || !empty($uploaded['error']) || empty($uploaded['file'])) {
            $this->add_admin_error('No fue posible recibir el ZIP: ' . (string) ($uploaded['error'] ?? 'error desconocido'));
            return;
        }

        $zip_path = (string) $uploaded['file'];
        $result = $this->extract_validated_package($zip_path, $post_id);
        @unlink($zip_path);

        if (is_wp_error($result)) {
            $this->add_admin_error($result->get_error_message());
            return;
        }

        update_post_meta($post_id, self::META_FOLDER, basename($this->get_extract_dir($post_id)));
        update_post_meta($post_id, self::META_ENTRY, $result);
        update_post_meta($post_id, self::META_ORIGINAL_NAME, $original);
    }

    /** @return string|WP_Error */
    private function extract_validated_package(string $zip_path, int $post_id)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('hub_zip_missing', 'El servidor no tiene habilitada la extensión PHP ZipArchive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zip_path) !== true) {
            return new WP_Error('hub_zip_open', 'No fue posible abrir el ZIP.');
        }

        $max_files = (int) apply_filters('constructor_hub_zip_max_files', 600, $post_id);
        $max_total = (int) apply_filters('constructor_hub_zip_max_uncompressed_bytes', 120 * MB_IN_BYTES, $post_id);
        $max_single = (int) apply_filters('constructor_hub_zip_max_file_bytes', 20 * MB_IN_BYTES, $post_id);
        if ($zip->numFiles <= 0 || $zip->numFiles > $max_files) {
            $zip->close();
            return new WP_Error('hub_zip_count', 'El ZIP contiene una cantidad de archivos no permitida.');
        }

        $allowed_extensions = (array) apply_filters('constructor_hub_zip_allowed_extensions', [
            'html', 'htm', 'css', 'js', 'mjs', 'json', 'map', 'txt',
            'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
        ], $post_id);
        $denied_names = ['.htaccess', '.user.ini', 'php.ini', 'web.config'];

        $manifest = [];
        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat) || !isset($stat['name'])) {
                $zip->close();
                return new WP_Error('hub_zip_stat', 'No fue posible inspeccionar todos los archivos del ZIP.');
            }

            $name = str_replace('\\', '/', (string) $stat['name']);
            $is_dir = str_ends_with($name, '/');
            $safe = $this->validate_relative_path($name);
            if ($safe === '') {
                $zip->close();
                return new WP_Error('hub_zip_path', 'El ZIP contiene una ruta no segura: ' . esc_html($name));
            }

            if ($this->is_symlink($zip, $i)) {
                $zip->close();
                return new WP_Error('hub_zip_symlink', 'El ZIP contiene enlaces simbólicos, que no están permitidos.');
            }

            if (!$is_dir) {
                $basename = strtolower(basename($safe));
                if (in_array($basename, $denied_names, true)) {
                    $zip->close();
                    return new WP_Error('hub_zip_config', 'El ZIP contiene un archivo de configuración bloqueado: ' . esc_html($basename));
                }

                $extension = strtolower(pathinfo($safe, PATHINFO_EXTENSION));
                if ($extension === '' || !in_array($extension, $allowed_extensions, true)) {
                    $zip->close();
                    return new WP_Error('hub_zip_extension', 'Tipo de archivo no permitido en el ZIP: ' . esc_html($safe));
                }

                $size = absint($stat['size'] ?? 0);
                if ($size > $max_single) {
                    $zip->close();
                    return new WP_Error('hub_zip_file_size', 'Un archivo del ZIP supera el límite individual: ' . esc_html($safe));
                }
                $total += $size;
                if ($total > $max_total) {
                    $zip->close();
                    return new WP_Error('hub_zip_total_size', 'El contenido descomprimido supera el límite permitido.');
                }
            }

            $manifest[] = ['index' => $i, 'path' => $safe, 'dir' => $is_dir];
        }

        $target = $this->get_extract_dir($post_id);
        $staging = $target . '-staging-' . wp_generate_password(8, false, false);
        $this->rrmdir($staging);
        if (!wp_mkdir_p($staging)) {
            $zip->close();
            return new WP_Error('hub_zip_mkdir', 'No fue posible crear la carpeta temporal de extracción.');
        }

        $written_total = 0;

        foreach ($manifest as $item) {
            $destination = trailingslashit($staging) . $item['path'];
            if ($item['dir']) {
                wp_mkdir_p($destination);
                continue;
            }

            if (!wp_mkdir_p(dirname($destination))) {
                $zip->close();
                $this->rrmdir($staging);
                return new WP_Error('hub_zip_mkdir_file', 'No fue posible crear una subcarpeta del package.');
            }

            $stream = $zip->getStream((string) $zip->getNameIndex((int) $item['index']));
            if (!is_resource($stream)) {
                $zip->close();
                $this->rrmdir($staging);
                return new WP_Error('hub_zip_stream', 'No fue posible leer un archivo del ZIP.');
            }

            $out = fopen($destination, 'wb');
            if (!is_resource($out)) {
                fclose($stream);
                $zip->close();
                $this->rrmdir($staging);
                return new WP_Error('hub_zip_write', 'No fue posible escribir un archivo del package.');
            }

            // The size validated above comes from the archive header, which the
            // archive declares about itself. Bound the actual copy so a
            // decompression bomb cannot fill the disk.
            $budget = min($max_single, max(0, $max_total - $written_total));
            $written = (int) stream_copy_to_stream($stream, $out, $budget + 1);
            fclose($stream);
            fclose($out);

            $written_total += $written;
            if ($written > $max_single || $written_total > $max_total) {
                $zip->close();
                $this->rrmdir($staging);
                return new WP_Error(
                    'hub_zip_bomb',
                    'El contenido real del ZIP supera el límite permitido al descomprimirse: ' . esc_html($item['path'])
                );
            }
        }
        $zip->close();

        $svg_error = $this->sanitize_extracted_svgs($staging);
        if (is_wp_error($svg_error)) {
            $this->rrmdir($staging);
            return $svg_error;
        }

        $entry = $this->find_entry_html($staging);
        if ($entry === '') {
            $this->rrmdir($staging);
            return new WP_Error('hub_zip_entry', 'No se encontró un archivo HTML principal (index.html o un HTML en la raíz).');
        }

        $this->delete_existing_folder($post_id);
        if (!@rename($staging, $target)) {
            $this->rrmdir($staging);
            return new WP_Error('hub_zip_swap', 'No fue posible activar el package extraído.');
        }

        return $entry;
    }

    /**
     * SVG is XML: it can carry <script>, event handlers and javascript: URLs,
     * and once extracted it is served from the site origin. Every SVG in a
     * package is rewritten to a passive subset before it becomes reachable.
     *
     * @return true|WP_Error
     */
    private function sanitize_extracted_svgs(string $dir)
    {
        if (!apply_filters('constructor_hub_zip_allow_svg', true)) {
            return true;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            if (strtolower($file->getExtension()) !== 'svg') {
                continue;
            }

            $path = $file->getPathname();
            $contents = file_get_contents($path);
            if ($contents === false) {
                return new WP_Error('hub_zip_svg_read', 'No fue posible inspeccionar un SVG del package.');
            }

            $clean = self::sanitize_svg($contents);
            if ($clean === null) {
                return new WP_Error(
                    'hub_zip_svg',
                    'Un SVG del package no pudo sanearse: ' . esc_html(basename($path))
                );
            }

            if (file_put_contents($path, $clean) === false) {
                return new WP_Error('hub_zip_svg_write', 'No fue posible sanear un SVG del package.');
            }
        }

        return true;
    }

    /** Returns the passive SVG, or null when it cannot be parsed. */
    public static function sanitize_svg(string $svg): ?string
    {
        if (trim($svg) === '') {
            return $svg;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $loaded = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOENT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $forbidden_tags = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'handler', 'set', 'animate'];
        $xpath = new DOMXPath($document);

        foreach ($forbidden_tags as $tag) {
            $nodes = $xpath->query('//*[translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="' . $tag . '"]');
            if ($nodes === false) {
                continue;
            }
            foreach (iterator_to_array($nodes) as $node) {
                if ($node instanceof DOMNode && $node->parentNode instanceof DOMNode) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $elements = $xpath->query('//*');
        if ($elements !== false) {
            foreach ($elements as $element) {
                if (!$element instanceof DOMElement) {
                    continue;
                }
                foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                    if (!$attribute instanceof DOMAttr) {
                        continue;
                    }
                    $name = strtolower($attribute->nodeName);
                    $value = (string) $attribute->nodeValue;
                    $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? $value);

                    if (str_starts_with($name, 'on')) {
                        $element->removeAttributeNode($attribute);
                        continue;
                    }
                    if (in_array($name, ['href', 'xlink:href', 'src', 'from', 'to', 'values'], true)) {
                        if (str_starts_with($normalized, 'javascript:') || str_starts_with($normalized, 'data:text/html')) {
                            $element->removeAttributeNode($attribute);
                        }
                        continue;
                    }
                    if ($name === 'style' && (str_contains($normalized, 'javascript:') || str_contains($normalized, 'expression('))) {
                        $element->removeAttributeNode($attribute);
                    }
                }
            }
        }

        $output = $document->saveXML();
        return $output === false ? null : $output;
    }

    private function validate_relative_path(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//', $path)) {
            return '';
        }

        $parts = explode('/', $path);
        $safe_parts = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $safe_parts[] = $part;
        }

        return implode('/', $safe_parts) . (str_ends_with($path, '/') ? '/' : '');
    }

    private function is_symlink(ZipArchive $zip, int $index): bool
    {
        if (!method_exists($zip, 'getExternalAttributesIndex')) {
            return false;
        }
        $opsys = 0;
        $attr = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attr)) {
            return false;
        }
        $mode = ($attr >> 16) & 0xF000;
        return $mode === 0xA000;
    }

    public function maybe_render_package(): void
    {
        if (is_admin() || !is_singular(HUB_Tibox_Landing_Manager::POST_TYPE) || is_feed() || is_embed()) {
            return;
        }

        if (post_password_required(get_queried_object_id())) {
            return;
        }

        $post_id = get_queried_object_id();
        if (HUB_Tibox_Landing_Manager::instance()->get_mode($post_id) !== HUB_Tibox_Landing_Manager::MODE_PACKAGE) {
            return;
        }

        $entry = (string) get_post_meta($post_id, self::META_ENTRY, true);
        if ($entry === '') {
            return;
        }

        $file = trailingslashit($this->get_extract_dir($post_id)) . $entry;
        if (!is_file($file)) {
            return;
        }

        $html = file_get_contents($file);
        if ($html === false) {
            return;
        }

        $entry_dir = dirname($entry);
        $base = trailingslashit($this->get_extract_url($post_id));
        if ($entry_dir !== '.' && $entry_dir !== '') {
            $base .= trailingslashit($entry_dir);
        }

        HUB_Tibox_Landing_Document::render($html, $post_id, $base);
    }

    public function get_extract_dir(int $post_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . self::UPLOAD_SUBDIR . '/' . $post_id;
    }

    public function get_extract_url(int $post_id): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['baseurl']) . self::UPLOAD_SUBDIR . '/' . $post_id;
    }

    public function clone_package(int $source_id, int $target_id): bool
    {
        $entry = (string) get_post_meta($source_id, self::META_ENTRY, true);
        if ($entry === '') {
            return false;
        }
        $original = (string) get_post_meta($source_id, self::META_ORIGINAL_NAME, true);
        return $this->import_existing_directory(
            $target_id,
            $this->get_extract_dir($source_id),
            $entry,
            $original !== '' ? $original : 'duplicated-package.zip'
        );
    }

    public function import_existing_directory(int $post_id, string $source_dir, string $entry, string $original_name = 'legacy-package.zip'): bool
    {
        if (!current_user_can('manage_options') || !is_dir($source_dir)) {
            return false;
        }
        $safe_entry = $this->validate_relative_path($entry);
        if ($safe_entry === '' || !is_file(trailingslashit($source_dir) . $safe_entry)) {
            return false;
        }

        $target = $this->get_extract_dir($post_id);
        $this->delete_existing_folder($post_id);
        if (!$this->copy_directory($source_dir, $target)) {
            return false;
        }

        update_post_meta($post_id, self::META_FOLDER, basename($target));
        update_post_meta($post_id, self::META_ENTRY, $safe_entry);
        update_post_meta($post_id, self::META_ORIGINAL_NAME, sanitize_file_name($original_name));
        return true;
    }

    private function find_entry_html(string $dir): string
    {
        if (is_file($dir . '/index.html')) {
            return 'index.html';
        }
        $items = scandir($dir);
        if (!is_array($items)) {
            return '';
        }

        $root_html = [];
        $directories = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_file($path) && in_array(strtolower(pathinfo($item, PATHINFO_EXTENSION)), ['html', 'htm'], true)) {
                $root_html[] = $item;
            } elseif (is_dir($path)) {
                $directories[] = $item;
            }
        }
        if ($root_html !== []) {
            sort($root_html);
            return $root_html[0];
        }
        if (count($directories) === 1) {
            foreach (['index.html', 'index.htm'] as $index) {
                if (is_file($dir . '/' . $directories[0] . '/' . $index)) {
                    return $directories[0] . '/' . $index;
                }
            }
        }
        return '';
    }

    private function delete_existing_folder(int $post_id): void
    {
        $this->rrmdir($this->get_extract_dir($post_id));
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        if (!is_array($items)) return;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->rrmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private function copy_directory(string $source, string $target): bool
    {
        if (!wp_mkdir_p($target)) return false;
        $items = scandir($source);
        if (!is_array($items)) return false;
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $src = $source . '/' . $item;
            $dst = $target . '/' . $item;
            if (is_link($src)) return false;
            if (is_dir($src)) {
                if (!$this->copy_directory($src, $dst)) return false;
            } elseif (!copy($src, $dst)) {
                return false;
            }
        }
        return true;
    }

    private function add_admin_error(string $message): void
    {
        set_transient('hub_landing_zip_error_' . get_current_user_id(), $message, 90);
    }

    public function display_stored_errors(): void
    {
        $key = 'hub_landing_zip_error_' . get_current_user_id();
        $message = get_transient($key);
        if ($message === false || $message === '') return;
        delete_transient($key);
        echo '<div class="notice notice-error"><p><strong>Constructor HUB — ZIP:</strong> ' . esc_html((string) $message) . '</p></div>';
    }
}
