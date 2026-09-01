<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Constructor HUB as a first level menu.
 *
 * Everything used to hang off `edit.php?post_type=hub_component`, which tied the
 * whole product navigation to one post type and put Landings inside a menu
 * called Components. The administrator experience the project is aiming for is
 * a single HUB section with the design types inside it.
 */
final class HUB_Tibox_Admin_Menu
{
    public const SLUG = 'constructor-hub';

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
        add_action('admin_menu', [$this, 'register'], 9);
        add_filter('parent_file', [$this, 'highlight_parent']);
        add_filter('submenu_file', [$this, 'highlight_submenu'], 10, 2);
    }

    public function register(): void
    {
        $capability = HUB_Tibox_Capabilities::MANAGE_DESIGNS;
        if (!current_user_can($capability) && current_user_can('manage_options')) {
            $capability = 'manage_options';
        }

        add_menu_page(
            'Constructor HUB',
            'Constructor HUB',
            $capability,
            self::SLUG,
            [$this, 'render_dashboard'],
            'dashicons-layout',
            58
        );

        add_submenu_page(self::SLUG, 'Dashboard', 'Dashboard', $capability, self::SLUG, [$this, 'render_dashboard']);

        $groups = [
            'chrome' => 'Componentes globales',
            'bloques' => 'Bloques',
            'paginas' => 'Páginas',
        ];

        foreach ($groups as $group => $label) {
            $first = true;
            foreach (HUB_Tibox_Design::types() as $type => $definition) {
                if ($definition['group'] !== $group) {
                    continue;
                }

                if ($first) {
                    // A separator is not available in the WordPress menu API, so
                    // the group name is carried by the first item's title.
                    $first = false;
                }

                add_submenu_page(
                    self::SLUG,
                    $definition['plural'],
                    $definition['plural'],
                    'edit_hub_designs',
                    'edit.php?post_type=' . HUB_Tibox_Design::POST_TYPE . '&hub_type=' . $type
                );
            }
        }

        add_submenu_page(
            self::SLUG,
            'Leads de formularios',
            'Leads',
            HUB_Tibox_Capabilities::can_manage_leads() ? HUB_Tibox_Capabilities::MANAGE_LEADS : 'manage_options',
            'constructor-hub-leads',
            [HUB_Tibox_Landing_Forms::instance(), 'render_leads_page']
        );

        /** Integrations, migration and settings register their own pages here. */
        do_action('constructor_hub_admin_menu', self::SLUG);
    }

    public function render_dashboard(): void
    {
        if (!HUB_Tibox_Capabilities::can_manage_designs()) {
            wp_die(esc_html__('No autorizado.', 'constructor-hub-tibox'));
        }

        $counts = [];
        foreach (HUB_Tibox_Design::types() as $type => $definition) {
            $counts[$type] = count(HUB_Tibox_Design::list_by_type($type, 'any'));
        }

        $regions = [];
        foreach (HUB_Tibox_Regions::names() as $region) {
            $config = HUB_Tibox_Regions::config($region);
            $regions[$region] = $config;
        }
        ?>
        <div class="wrap">
            <h1>Constructor HUB</h1>
            <p>Estado de la capa de presentación de este sitio.</p>

            <h2>Diseños</h2>
            <table class="widefat striped" style="max-width:760px;">
                <thead><tr><th>Tipo</th><th style="width:110px;">Diseños</th><th style="width:160px;"></th></tr></thead>
                <tbody>
                <?php foreach (HUB_Tibox_Design::types() as $type => $definition) : ?>
                    <tr>
                        <td><strong><?php echo esc_html($definition['plural']); ?></strong></td>
                        <td style="font-variant-numeric:tabular-nums;"><?php echo esc_html((string) $counts[$type]); ?></td>
                        <td>
                            <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . HUB_Tibox_Design::POST_TYPE . '&hub_type=' . $type)); ?>">Ver</a>
                            ·
                            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . HUB_Tibox_Design::POST_TYPE . '&hub_type=' . $type)); ?>">Crear</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Regiones</h2>
            <table class="widefat striped" style="max-width:760px;">
                <thead><tr><th>Región</th><th>Modo</th><th>Diseño</th></tr></thead>
                <tbody>
                <?php foreach ($regions as $region => $config) : ?>
                    <tr>
                        <td><strong><?php echo esc_html(ucfirst($region)); ?></strong></td>
                        <td>
                            <?php
                            $labels = [
                                HUB_Tibox_Regions::MODE_THEME => 'Theme actual',
                                HUB_Tibox_Regions::MODE_INJECT => 'HUB inyectado',
                                HUB_Tibox_Regions::MODE_REPLACE => 'HUB reemplaza el documento',
                            ];
                            echo esc_html($labels[$config['mode']] ?? $config['mode']);
                            ?>
                        </td>
                        <td>
                            <?php
                            echo $config['design'] > 0
                                ? esc_html(get_the_title($config['design']))
                                : '—';
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=constructor-hub-regions')); ?>">Configurar regiones</a>
                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=constructor-hub-settings')); ?>">Configuración</a>
            </p>
        </div>
        <?php
    }

    /** Keeps the HUB menu open while editing a design. */
    public function highlight_parent(string $parent_file): string
    {
        $screen = get_current_screen();
        if ($screen instanceof WP_Screen && $screen->post_type === HUB_Tibox_Design::POST_TYPE) {
            return self::SLUG;
        }

        return $parent_file;
    }

    public function highlight_submenu($submenu_file, $parent_file = '')
    {
        $screen = get_current_screen();
        if (!$screen instanceof WP_Screen || $screen->post_type !== HUB_Tibox_Design::POST_TYPE) {
            return $submenu_file;
        }

        $type = isset($_GET['hub_type']) ? sanitize_key(wp_unslash($_GET['hub_type'])) : '';
        if ($type === '' && $screen->base === 'post') {
            $type = HUB_Tibox_Design::get_type((int) get_the_ID());
        }

        if (!HUB_Tibox_Design::is_valid_type($type)) {
            return $submenu_file;
        }

        return 'edit.php?post_type=' . HUB_Tibox_Design::POST_TYPE . '&hub_type=' . $type;
    }
}
