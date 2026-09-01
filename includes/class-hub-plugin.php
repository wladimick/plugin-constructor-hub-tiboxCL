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
 *
 * That decision must not be made before `HUB_Tibox_Upgrade` has had a chance to
 * run. `HUB_Tibox_Plugin::instance()` is called at the bottom of the plugin's
 * main file, which — for a plugin's own file — executes before WordPress fires
 * `plugins_loaded`. Deciding `is_unified()` at construction time meant the very
 * request that completed a migration still rendered through the pre-migration
 * boot path, while the legacy content the migration had just retired was
 * already sitting in draft: a real request-boundary inconsistency, not a
 * theoretical one. The decision is deferred to `plugins_loaded` at a priority
 * after the upgrade routine's own hook (priority 5), so both run in the same
 * request before anything about legacy vs. unified is decided.
 */
final class HUB_Tibox_Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

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

        // Diagnostics and the migration retry/rollback controls must stay
        // reachable in both boot paths: a partial migration or an explicit
        // rollback both leave the site in the historical layout, and that is
        // exactly when an administrator needs this screen most.
        HUB_Tibox_Site_Config::instance();

        if (self::needs_deferred_boot()) {
            add_action('plugins_loaded', [$this, 'boot'], 10);
            return;
        }

        // We are being constructed from `plugins_loaded` itself (the normal
        // case, since this is only reached when something calls
        // `HUB_Tibox_Plugin::instance()` after that hook already fired) or
        // later: the upgrade routine, hooked at priority 5, has already run.
        $this->boot();
    }

    /**
     * True when the upgrade routine (hooked on `plugins_loaded` at priority 5)
     * cannot have run yet. Pure and args-driven so the ordering decision is
     * unit-testable without a WordPress environment.
     */
    public static function needs_deferred_boot(?bool $plugins_loaded_fired = null): bool
    {
        if ($plugins_loaded_fired === null) {
            $plugins_loaded_fired = (bool) did_action('plugins_loaded');
        }

        return !$plugins_loaded_fired;
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'constructor-hub-tibox',
            false,
            dirname(plugin_basename(TIBOX_AI_FRONTEND_FILE)) . '/languages'
        );
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (HUB_Tibox_Upgrade::is_unified()) {
            $this->boot_unified();
            return;
        }

        $this->boot_legacy();
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
     * yet, or if a site pinned `hub_tibox_designs_unified` back to `0` — either
     * because a migration is still partial, or because of an explicit rollback.
     */
    private function boot_legacy(): void
    {
        HUB_Tibox_Component_Manager::instance();
        HUB_Tibox_Hybrid_Renderer::instance();
        HUB_Tibox_Landing_Manager::instance();
        HUB_Tibox_Landing_Renderer::instance();
    }
}
