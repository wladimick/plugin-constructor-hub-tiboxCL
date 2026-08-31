<?php
/**
 * Plugin Name: Constructor HUB Tibox
 * Plugin URI: https://github.com/wladimick/plugin-constructor-hub-tiboxCL
 * Description: Constructor frontend progresivo para WordPress. Permite reemplazar Header, Footer, bloques, landings y páginas por HTML/CSS/JS propio, manteniendo WordPress como backend y compatibilidad con sitios existentes basados en Elementor.
 * Version: 0.4.1
 * Requires PHP: 8.0
 * Author: Tibox
 * Text Domain: constructor-hub-tibox
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NOTA DE COMPATIBILIDAD
 *
 * El archivo bootstrap conserva temporalmente el nombre histórico
 * `tibox-ai-frontend.php` para no romper instalaciones de la versión MVP.
 * La identidad pública del producto desde v0.2.0 es Constructor HUB Tibox.
 */
define('TIBOX_AI_FRONTEND_VERSION', '0.4.1');
define('TIBOX_AI_FRONTEND_FILE', __FILE__);
define('TIBOX_AI_FRONTEND_DIR', plugin_dir_path(__FILE__));
define('TIBOX_AI_FRONTEND_URL', plugin_dir_url(__FILE__));

require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-capabilities.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-tibox-ai-frontend.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-component-manager.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-hybrid-renderer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-manager.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-lead-store.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-mailer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-forms.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-document.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-renderer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-landing-zip-importer.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-legacy-migrator.php';

add_action('admin_notices', ['HUB_Tibox_Capabilities', 'render_notices']);

register_activation_hook(__FILE__, static function (): void {
    HUB_Tibox_Capabilities::grant();
    HUB_Tibox_Landing_Lead_Store::instance()->maybe_install_table();
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, static function (): void {
    flush_rewrite_rules(false);
});

TIBOX_AI_Frontend::instance();
HUB_Tibox_Component_Manager::instance();
HUB_Tibox_Hybrid_Renderer::instance();
HUB_Tibox_Landing_Manager::instance();
HUB_Tibox_Landing_Lead_Store::instance();
HUB_Tibox_Landing_Mailer::instance();
HUB_Tibox_Landing_Forms::instance();
HUB_Tibox_Landing_Renderer::instance();
HUB_Tibox_Landing_Zip_Importer::instance();
HUB_Tibox_Legacy_Migrator::instance();
