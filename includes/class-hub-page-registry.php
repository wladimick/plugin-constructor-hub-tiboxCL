<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Site-level registry of WordPress Pages and their active frontend renderer.
 *
 * The Page remains the public identity. This screen makes it obvious whether a
 * URL is currently served by Theme/Elementor or Constructor HUB, and provides a
 * reversible switch without deleting the HUB assignment or its version history.
 */
final class HUB_Tibox_Page_Registry
{
    private const SLUG = 'constructor-hub-pages';
    private const ACTION = 'hub_tibox_change_page_renderer';
    private const NONCE_PREFIX = 'hub_tibox_page_renderer_';

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
        add_action('admin_post_' . self::ACTION, [$this, 'handle_renderer_change']);
    }

    public static function can_activate_hub(
        bool $has_assignment,
        bool $design_published,
        bool $has_live_version
    ): bool {
        return $has_assignment && $design_published && $has_live_version;
    }

    public function register_page(string $parent): void
    {
        $capability = HUB_Tibox_Capabilities::can_manage_designs()
            ? HUB_Tibox_Capabilities::MANAGE_DESIGNS
            : 'manage_options';

        add_submenu_page(
            $parent,
            'Páginas y renderers',
            'Páginas del sitio',
            $capability,
            self::SLUG,
            [$this, 'render_page']
        );
    }

    public function render_page(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $can_switch = current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS)
            || current_user_can('manage_options');

        $notice = isset($_GET['hub_pages_notice'])
            ? sanitize_key(wp_unslash((string) $_GET['hub_pages_notice']))
            : '';
        $messages = [
            'hub_enabled' => ['success', 'Constructor HUB quedó activo en la Page.'],
            'theme_enabled' => ['success', 'La Page volvió a Theme / Elementor. La asignación HUB se conserva.'],
            'not_ready' => ['error', 'No se puede activar HUB: el diseño asignado debe estar publicado y tener una versión live.'],
            'invalid' => ['error', 'No fue posible cambiar el renderer de esa Page.'],
        ];
        ?>
        <div class="wrap">
            <h1>Páginas del sitio</h1>
            <p style="max-width:900px;">
                Vista operativa de quién renderiza cada URL. Cambiar a Theme / Elementor no elimina el diseño HUB:
                sirve como rollback inmediato y reversible.
            </p>

            <?php if (isset($messages[$notice])) : ?>
                <?php [$notice_type, $notice_message] = $messages[$notice]; ?>
                <div class="notice notice-<?php echo esc_attr($notice_type); ?> inline"><p><?php echo esc_html($notice_message); ?></p></div>
            <?php endif; ?>

            <p>
                <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=constructor-hub-factory')); ?>">Crear página con IA</a>
            </p>

            <table class="widefat striped" style="margin-top:18px;">
                <thead>
                    <tr>
                        <th>Page</th>
                        <th style="width:110px;">Estado WP</th>
                        <th style="width:150px;">Renderer</th>
                        <th>Diseño HUB</th>
                        <th style="width:90px;">Versión</th>
                        <th style="width:350px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pages === []) : ?>
                    <tr><td colspan="6">No hay Pages que mostrar.</td></tr>
                <?php endif; ?>
                <?php foreach ($pages as $page) : ?>
                    <?php
                    if (!$page instanceof WP_Post) {
                        continue;
                    }
                    $page_id = (int) $page->ID;
                    $assignment = HUB_Tibox_Page_Assignment::instance();
                    $design_id = $assignment->assigned_design_id($page_id);
                    $mode = HUB_Tibox_Page_Assignment::mode($page_id);
                    $design_published = $design_id > 0 && get_post_status($design_id) === 'publish';
                    $live = $design_id > 0 ? HUB_Tibox_Version_Store::instance()->get_live($design_id) : null;
                    $ready = self::can_activate_hub($design_id > 0, $design_published, $live !== null);
                    $owns = $assignment->owns_page($page_id);
                    $working = $design_id > 0 ? HUB_Tibox_Version_Store::instance()->get_working($design_id) : null;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($page->post_title !== '' ? $page->post_title : ('#' . $page_id)); ?></strong>
                            <?php if ($page->post_status === 'publish') : ?>
                                <br><a href="<?php echo esc_url((string) get_permalink($page_id)); ?>" target="_blank" rel="noopener"><?php echo esc_html((string) get_permalink($page_id)); ?></a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($page->post_status); ?></td>
                        <td>
                            <?php if ($owns) : ?>
                                <strong style="color:#00713c;">Constructor HUB</strong>
                            <?php else : ?>
                                <span>Theme / Elementor</span>
                                <?php if ($mode === HUB_Tibox_Page_Assignment::MODE_HUB && !$ready) : ?>
                                    <br><small style="color:#b32d2e;">fail-safe activo</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($design_id > 0) : ?>
                                <a href="<?php echo esc_url((string) get_edit_post_link($design_id, 'url')); ?>">
                                    <?php echo esc_html(get_the_title($design_id) !== '' ? get_the_title($design_id) : ('#' . $design_id)); ?>
                                </a>
                                <?php if (!$ready) : ?>
                                    <br><small style="color:#996800;">No listo para activar</small>
                                <?php endif; ?>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo $live !== null ? esc_html('v' . (int) $live['version']) : '—'; ?></td>
                        <td>
                            <a class="button button-small" href="<?php echo esc_url((string) get_edit_post_link($page_id, 'url')); ?>">Editar Page</a>

                            <?php if ($design_id > 0 && $working !== null) : ?>
                                <a class="button button-small" target="_blank" rel="noopener"
                                   href="<?php echo esc_url($assignment->preview_url($page_id, (int) $working['id'])); ?>">Preview HUB</a>
                            <?php endif; ?>

                            <?php if ($can_switch && $owns) : ?>
                                <a class="button button-small" href="<?php echo esc_url($this->renderer_url($page_id, HUB_Tibox_Page_Assignment::MODE_THEME)); ?>">Volver a Theme</a>
                            <?php elseif ($can_switch && !$owns && $ready) : ?>
                                <a class="button button-small button-primary" href="<?php echo esc_url($this->renderer_url($page_id, HUB_Tibox_Page_Assignment::MODE_HUB)); ?>">Activar HUB</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!$can_switch) : ?>
                <p class="description">Tu usuario puede revisar asignaciones y previews, pero no cambiar el renderer público.</p>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_renderer_change(): void
    {
        $page_id = isset($_GET['page_id']) ? absint($_GET['page_id']) : 0;
        $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash((string) $_GET['mode'])) : '';

        check_admin_referer(self::NONCE_PREFIX . $page_id . '_' . $mode);

        if (
            $page_id <= 0
            || get_post_type($page_id) !== 'page'
            || !current_user_can('edit_post', $page_id)
            || !(current_user_can(HUB_Tibox_Capabilities::PUBLISH_DESIGNS) || current_user_can('manage_options'))
        ) {
            $this->redirect('invalid');
        }

        if ($mode === HUB_Tibox_Page_Assignment::MODE_THEME) {
            update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_MODE, HUB_Tibox_Page_Assignment::MODE_THEME);
            $this->redirect('theme_enabled');
        }

        if ($mode !== HUB_Tibox_Page_Assignment::MODE_HUB) {
            $this->redirect('invalid');
        }

        $design_id = HUB_Tibox_Page_Assignment::instance()->assigned_design_id($page_id);
        $ready = self::can_activate_hub(
            $design_id > 0,
            $design_id > 0 && get_post_status($design_id) === 'publish',
            $design_id > 0 && HUB_Tibox_Version_Store::instance()->get_live($design_id) !== null
        );

        if (!$ready) {
            $this->redirect('not_ready');
        }

        update_post_meta($page_id, HUB_Tibox_Page_Assignment::META_MODE, HUB_Tibox_Page_Assignment::MODE_HUB);
        $this->redirect('hub_enabled');
    }

    private function renderer_url(int $page_id, string $mode): string
    {
        return wp_nonce_url(
            add_query_arg([
                'action' => self::ACTION,
                'page_id' => $page_id,
                'mode' => $mode,
            ], admin_url('admin-post.php')),
            self::NONCE_PREFIX . $page_id . '_' . $mode
        );
    }

    private function redirect(string $notice): void
    {
        wp_safe_redirect(add_query_arg([
            'page' => self::SLUG,
            'hub_pages_notice' => $notice,
        ], admin_url('admin.php')));
        exit;
    }
}
