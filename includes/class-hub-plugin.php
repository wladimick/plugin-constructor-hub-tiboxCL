<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bootstrap for Constructor HUB.
 *
 * Decides what runs: the unified design layer, or the historical modules while
 * a site has not been migrated yet. Keeping that decision in one place is what
 * makes the migration reversible.
 */
final class HUB_Tibox_Plugin
{
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
        add_action('init', [$this, 'load_textdomain'], 1);
        add_action('admin_notices', ['HUB_Tibox_Capabilities', 'render_notices']);

        HUB_Tibox_Upgrade::instance();

        // Always available: storage, forms, mail and migration tooling do not
        // depend on which design layer is active.
        HUB_Tibox_Landing_Lead_Store::instance();
        HUB_Tibox_Lead_Privacy::instance();
        HUB_Tibox_Leads_Export::instance();
        HUB_Tibox_Mail_Log::instance();
        HUB_Tibox_Landing_Mailer::instance();
        HUB_Tibox_Landing_Forms::instance();
        HUB_Tibox_Landing_Zip_Importer::instance();
        HUB_Tibox_Legacy_Migrator::instance();

        if (HUB_Tibox_Upgrade::is_unified()) {
            $this->boot_unified();
            return;
        }

        $this->boot_legacy();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'constructor-hub-tibox',
            false,
            dirname(plugin_basename(TIBOX_AI_FRONTEND_FILE)) . '/languages'
        );
    }

    private function boot_unified(): void
    {
        add_action('init', ['HUB_Tibox_Legacy_Types', 'register'], 4);

        HUB_Tibox_Design::instance();
        HUB_Tibox_Asset_Compiler::instance();
        HUB_Tibox_Render::instance();
        HUB_Tibox_Insertion::instance();
        HUB_Tibox_Design_System::instance();
        HUB_Tibox_Package::instance();
        HUB_Tibox_Asset_Optimizer::instance();
        HUB_Tibox_Migration_Map::instance();
        HUB_Tibox_Elementor_Adapter::instance();

        if (is_admin()) {
            HUB_Tibox_Admin_Menu::instance();
            HUB_Tibox_Design_Admin::instance();
            HUB_Tibox_Settings_Page::instance();
        }
    }

    /**
     * Pre-unification layout. Only reachable if the upgrade routine has not run
     * yet, or if a site pinned `hub_tibox_designs_unified` back to `0`.
     */
    private function boot_legacy(): void
    {
        HUB_Tibox_Component_Manager::instance();
        HUB_Tibox_Hybrid_Renderer::instance();
        HUB_Tibox_Landing_Manager::instance();
        HUB_Tibox_Landing_Renderer::instance();
    }
}
