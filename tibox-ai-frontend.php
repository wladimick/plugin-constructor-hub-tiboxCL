<?php
/**
 * Plugin Name: Constructor HUB Tibox
 * Plugin URI: https://github.com/wladimick/plugin-constructor-hub-tiboxCL
 * Description: Constructor frontend progresivo para WordPress. Permite reemplazar Header, Footer, bloques, landings y páginas por HTML/CSS/JS propio, manteniendo WordPress como backend y compatibilidad con sitios existentes basados en Elementor.
 * Version: 0.5.0-dev
 * Requires PHP: 8.0
 * Requires at least: 6.2
 * Author: Tibox
 * Text Domain: constructor-hub-tibox
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NOTA DE COMPATIBILIDAD
 *
 * El archivo bootstrap conserva el nombre histórico `tibox-ai-frontend.php`
 * para no romper instalaciones de la versión MVP: WordPress identifica un
 * plugin por su ruta, y renombrarlo instalaría una segunda copia con clases
 * duplicadas. La identidad pública del producto desde v0.2.0 es
 * Constructor HUB Tibox.
 */
define('TIBOX_AI_FRONTEND_VERSION', '0.5.0-dev');
define('TIBOX_AI_FRONTEND_FILE', __FILE__);
define('TIBOX_AI_FRONTEND_DIR', plugin_dir_path(__FILE__));
define('TIBOX_AI_FRONTEND_URL', plugin_dir_url(__FILE__));

// Core.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-capabilities.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-filesystem.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-css-scoper.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-variables.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-version-store.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-design.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-preview.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-asset-compiler.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-regions.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-render.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-insertion.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-design-system.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-form-config.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-legacy-types.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-upgrade.php';

// Adapters.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/adapters/class-hub-elementor-adapter.php';

// Admin.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-admin-menu.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-design-admin.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-settings-page.php';

// Forms, leads and mail.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-antispam.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-mail-log.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-lead-store.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-lead-privacy.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-leads-export.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-mailer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-forms.php';

// Packages and documents.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-document.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-zip-importer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-package.php';

// Historical modules, kept for sites that have not been migrated yet.
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-tibox-ai-frontend.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-component-manager.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-hybrid-renderer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-manager.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-renderer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-legacy-migrator.php';

require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-plugin.php';

register_activation_hook(__FILE__, static function (): void {
    HUB_Tibox_Upgrade::instance()->install();
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules(false);
});

HUB_Tibox_Plugin::instance();
