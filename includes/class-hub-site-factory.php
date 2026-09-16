<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Guided Page workflow for Constructor HUB.
 *
 * The lower-level tools deliberately expose designs, versions and package
 * imports. Site Factory sits above them and turns the common operation into one
 * safe flow:
 *
 * 1. import a Page package produced by an AI;
 * 2. create or select the WordPress Page that owns the public URL;
 * 3. assign the HUB design while keeping Theme/Elementor as the default renderer;
 * 4. optionally publish and activate HUB when the current user explicitly asks.
 *
 * Importing is therefore never a cutover by itself. The Page identity, SEO
 * object and permalink remain WordPress-owned throughout the workflow.
 */
final class HUB_Tibox_Site_Factory
{
    private const SLUG = 'constructor-hub-factory';
    private const ACTION = 'hub_tibox_site_factory_create';
    private const NONCE_ACTION = 'hub_tibox_site_factory_create';
    private const NONCE_FIELD = 'hub_tibox_site_factory_nonce';
    private const NOTICE_PREFIX = 'hub_site_factory_notice_';

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
        add_action('constructor_hub_admin_menu', [$this, 'register_page'], 5);
        add_action('admin_post_' . self::ACTION, [$this, 'handle_create']);
    }

    /**
     * Decide whether the public renderer may switch to HUB.
     *
     * Kept pure so permission/guard failures are covered by unit tests without
     * needing a WordPress database.
     */
    public static function should_activate(
        bool $requested,
        bool $can_publish_designs,
        bool $version_published,
        bool $design_published
    ): bool {
        return $requested && $can_publish_designs && $version_published && $design_published;
    }

    /**
     * Publishing a WordPress Page is separate from activating HUB.
     */
    public static function should_publish_page(bool $requested, bool $can_publish_pages): bool
    {
        return $requested && $can_publish_pages;
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_edit_design_code()
            ? HUB_Tibox_Capabilities::EDIT_DESIGN_CODE
            : 'manage_options';

        add_submenu_page(
            $parent,
            'Crear página con IA',
            'Crear página',
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

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $can_activate = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        $can_publish_page = current_user_can('publish_pages');
        ?>
        <div class="wrap">
            <h1>Crear página con IA</h1>
            <p style="max-width:820px;">
                Importa un package <strong>Page</strong> de Claude Design, ChatGPT u otra IA y asígnalo a una Page real de WordPress.
                Por defecto la URL sigue usando <strong>Theme / Elementor</strong> hasta que revises el preview y actives HUB explícitamente.
            </p>

            <?php $this->render_notice($notice); ?>

            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:960px;">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">

                <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                    <h2 style="margin-top:0;">1. Package de diseño</h2>
                    <p>
                        <label for="hub-factory-package"><strong>ZIP de la página</strong></label><br>
                        <input id="hub-factory-package" type="file" name="hub_factory_package" accept=".zip,application/zip" required>
                    </p>
                    <p class="description">
                        El manifest debe declarar <code>"type": "page"</code>. El ZIP se importa primero como versión borrador.
                    </p>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                    <h2 style="margin-top:0;">2. Page de WordPress</h2>
                    <fieldset>
                        <legend class="screen-reader-text">Destino de WordPress</legend>
                        <p>
                            <label>
                                <input type="radio" name="hub_factory_page_mode" value="new" checked>
                                <strong>Crear una Page nueva</strong>
                            </label>
                        </p>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="hub-factory-title">Título</label></th>
                                <td><input id="hub-factory-title" class="regular-text" type="text" name="hub_factory_page_title" placeholder="Ej. Sitios Web"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="hub-factory-slug">Slug</label></th>
                                <td>
                                    <input id="hub-factory-slug" class="regular-text" type="text" name="hub_factory_page_slug" placeholder="sitios-web">
                                    <p class="description">Opcional. Si queda vacío, WordPress lo genera desde el título.</p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin-top:22px;">
                            <label>
                                <input type="radio" name="hub_factory_page_mode" value="existing">
                                <strong>Usar una Page existente</strong>
                            </label>
                        </p>
                        <p>
                            <label for="hub-factory-page"><span class="screen-reader-text">Page existente</span></label>
                            <select id="hub-factory-page" name="hub_factory_existing_page_id" style="min-width:360px;max-width:100%;">
                                <option value="0">— Seleccionar Page —</option>
                                <?php foreach ($pages as $page) : ?>
                                    <?php if (!$page instanceof WP_Post) : ?>
                                        <?php continue; ?>
                                    <?php endif; ?>
                                    <option value="<?php echo esc_attr((string) $page->ID); ?>">
                                        <?php
                                        echo esc_html(sprintf(
                                            '%s — %s',
                                            $page->post_title !== '' ? $page->post_title : ('#' . $page->ID),
                                            $page->post_status
                                        ));
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    </fieldset>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:20px 24px;margin:20px 0;">
                    <h2 style="margin-top:0;">3. Activación</h2>
                    <p>
                        <label>
                            <input type="checkbox" name="hub_factory_activate" value="1" <?php disabled(!$can_activate); ?>>
                            <strong>Publicar el diseño y activar Constructor HUB en la Page</strong>
                        </label>
                    </p>
                    <?php if (!$can_activate) : ?>
                        <p class="description">
                            Tu usuario puede preparar e importar páginas, pero no tiene permiso para activar código HUB en una URL pública.
                        </p>
                    <?php else : ?>
                        <p class="description">
                            Si no lo marcas, la Page queda asignada en modo Theme / Elementor y podrás revisar HUB mediante un preview firmado.
                        </p>
                    <?php endif; ?>

                    <p>
                        <label>
                            <input type="checkbox" name="hub_factory_publish_page" value="1" <?php disabled(!$can_publish_page); ?>>
                            Publicar también la Page de WordPress si está en borrador
                        </label>
                    </p>
                    <?php if (!$can_publish_page) : ?>
                        <p class="description">Tu usuario no tiene permiso para publicar Pages de WordPress.</p>
                    <?php endif; ?>
                </div>

                <?php submit_button('Preparar página HUB', 'primary', 'submit', true); ?>
            </form>

            <p class="description" style="max-width:820px;">
                Site Factory no elimina Elementor ni modifica el contenido editorial existente de una Page seleccionada. Solo crea la relación con el diseño HUB y cambia el renderer cuando se solicita explícitamente.
            </p>
        </div>
        <?php
    }

    public function handle_create(): void
    {
        if (!HUB_Tibox_Capabilities::can_edit_design_code()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'), '', ['response' => 403]);
        }

        check_admin_referer(self::NONCE_ACTION, self::NONCE_FIELD);

        $page_mode = isset($_POST['hub_factory_page_mode'])
            ? sanitize_key(wp_unslash((string) $_POST['hub_factory_page_mode']))
            : 'new';
        $page_mode = in_array($page_mode, ['new', 'existing'], true) ? $page_mode : 'new';

        $activate_requested = !empty($_POST['hub_factory_activate']);
        $publish_page_requested = !empty($_POST['hub_factory_publish_page']);
        $can_activate = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');
        $can_publish_page = current_user_can('publish_pages');

        $uploaded = $this->receive_zip();
        if (is_wp_error($uploaded)) {
            $this->notify('error', $uploaded->get_error_message());
            $this->redirect();
        }

        $package = HUB_Tibox_Package::instance();
        $result = $package->import($uploaded['path'], 0, $uploaded['name']);
        @unlink($uploaded['path']);

        if (is_wp_error($result)) {
            $this->notify('error', $result->get_error_message());
            $this->redirect();
        }

        $design_id = (int) $result['design_id'];
        $version_id = (int) $result['version_id'];
        $manifest = (array) $result['manifest'];

        if ((string) ($manifest['type'] ?? '') !== 'page') {
            $this->notify(
                'error',
                sprintf(
                    'El ZIP se importó como diseño "%s", pero Site Factory solo puede asignar packages de tipo page. Corrige manifest.json y vuelve a importarlo.',
                    (string) ($manifest['type'] ?? 'desconocido')
                ),
                0,
                $design_id
            );
            $this->redirect();
        }

        $page_id = $page_mode === 'existing'
            ? $this->existing_page_from_request()
            : $this->create_page_from_request(self::should_publish_page($publish_page_requested, $can_publish_page));

        if (is_wp_error($page_id)) {
            $this->notify('error', $page_id->get_error_message(), 0, $design_id);
            $this->redirect();
        }

        $page_id = (int) $page_id;

        update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_DESIGN, $design_id);
        update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_MODE, HUB_Tibox_Page_Assignment::MODE_THEME);

        if (
            $page_mode === 'existing'
            && self::should_publish_page($publish_page_requested, $can_publish_page)
            && get_post_status($page_id) !== 'publish'
        ) {
            $page_update = wp_update_post([
                'ID' => $page_id,
                'post_status' => 'publish',
            ], true);

            if (is_wp_error($page_update)) {
                $this->notify(
                    'warning',
                    'El diseño quedó asignado, pero WordPress no pudo publicar la Page: ' . $page_update->get_error_message(),
                    $page_id,
                    $design_id,
                    $version_id
                );
                $this->redirect();
            }
        }

        $version_published = false;
        $design_published = false;

        if ($activate_requested && $can_activate) {
            $version_published = HUB_Tibox_Version_Store::instance()->publish($design_id, $version_id);

            if ($version_published) {
                $updated = wp_update_post([
                    'ID' => $design_id,
                    'post_status' => 'publish',
                ], true);
                $design_published = !is_wp_error($updated) && (int) $updated > 0;
            }
        }

        $activated = self::should_activate(
            $activate_requested,
            $can_activate,
            $version_published,
            $design_published
        );

        if ($activated) {
            update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_MODE, HUB_Tibox_Page_Assignment::MODE_HUB);
        }

        if ($activate_requested && !$activated) {
            $message = $can_activate
                ? 'La página se preparó, pero HUB no se activó. Revisa el Publish Guard y el estado del diseño antes de volver a publicar.'
                : 'La página se preparó en modo seguro. Tu usuario no tiene permiso para activar HUB.';
            $type = 'warning';
        } elseif ($activated) {
            $message = 'Página preparada y Constructor HUB activado correctamente.';
            $type = 'success';
        } else {
            $message = 'Página preparada. Theme / Elementor sigue activo hasta que revises el preview y decidas activar HUB.';
            $type = 'success';
        }

        $this->notify($type, $message, $page_id, $design_id, $version_id, $activated);
        $this->redirect();
    }

    /** @return array{path:string,name:string}|WP_Error */
    private function receive_zip()
    {
        // The admin-post handler verified the factory nonce before calling this helper.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        if (
            !isset($_FILES['hub_factory_package'])
            || !is_array($_FILES['hub_factory_package'])
            || !isset($_FILES['hub_factory_package']['error'])
            || (int) $_FILES['hub_factory_package']['error'] !== UPLOAD_ERR_OK
        ) {
            return new WP_Error('hub_factory_upload', 'No se recibió un archivo ZIP válido.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- wp_handle_upload() validates the upload payload.
        $file = $_FILES['hub_factory_package'];
        $original = sanitize_file_name(wp_unslash((string) ($file['name'] ?? 'package.zip')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing

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
                'hub_factory_upload',
                'No fue posible recibir el ZIP: ' . (string) ($uploaded['error'] ?? 'error desconocido')
            );
        }

        return [
            'path' => (string) $uploaded['file'],
            'name' => $original,
        ];
    }

    /** @return int|WP_Error */
    private function existing_page_from_request()
    {
        // The admin-post handler verified the factory nonce before calling this helper.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $page_id = isset($_POST['hub_factory_existing_page_id'])
            ? absint($_POST['hub_factory_existing_page_id'])
            : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($page_id <= 0 || get_post_type($page_id) !== 'page') {
            return new WP_Error('hub_factory_page', 'Selecciona una Page de WordPress válida.');
        }

        if (!current_user_can('edit_post', $page_id)) {
            return new WP_Error('hub_factory_page_permission', 'No tienes permiso para editar esa Page.');
        }

        return $page_id;
    }

    /** @return int|WP_Error */
    private function create_page_from_request(bool $publish)
    {
        if (!current_user_can('edit_pages')) {
            return new WP_Error('hub_factory_create_page', 'Tu usuario no tiene permiso para crear Pages.');
        }

        // The admin-post handler verified the factory nonce before calling this helper.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $title = isset($_POST['hub_factory_page_title'])
            ? sanitize_text_field(wp_unslash((string) $_POST['hub_factory_page_title']))
            : '';
        $slug = isset($_POST['hub_factory_page_slug'])
            ? sanitize_title(wp_unslash((string) $_POST['hub_factory_page_slug']))
            : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        if ($title === '') {
            return new WP_Error('hub_factory_title', 'Escribe un título para la nueva Page.');
        }

        $postarr = [
            'post_type' => 'page',
            'post_status' => $publish ? 'publish' : 'draft',
            'post_title' => $title,
        ];

        if ($slug !== '') {
            $postarr['post_name'] = $slug;
        }

        $page_id = wp_insert_post($postarr, true);

        return is_wp_error($page_id) ? $page_id : (int) $page_id;
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
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> inline">
            <p><strong><?php echo esc_html($message); ?></strong></p>
            <p>
                <?php if ($page_id > 0 && $version_id > 0) : ?>
                    <a class="button button-primary" target="_blank" rel="noopener" href="<?php echo esc_url(HUB_Tibox_Page_Assignment::instance()->preview_url($page_id, $version_id)); ?>">
                        Preview en la Page
                    </a>
                <?php endif; ?>
                <?php if ($page_id > 0) : ?>
                    <a class="button" href="<?php echo esc_url((string) get_edit_post_link($page_id, 'url')); ?>">Editar Page</a>
                <?php endif; ?>
                <?php if ($design_id > 0) : ?>
                    <a class="button" href="<?php echo esc_url((string) get_edit_post_link($design_id, 'url')); ?>">Editar diseño HUB</a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }

    private function notify(
        string $type,
        string $message,
        int $page_id = 0,
        int $design_id = 0,
        int $version_id = 0,
        bool $activated = false
    ): void {
        set_transient(
            self::NOTICE_PREFIX . get_current_user_id(),
            [
                'type' => $type,
                'message' => $message,
                'page_id' => $page_id,
                'design_id' => $design_id,
                'version_id' => $version_id,
                'activated' => $activated,
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
