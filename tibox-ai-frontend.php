<?php
/**
 * Plugin Name: Constructor HUB Tibox
 * Plugin URI: https://github.com/wladimick/plugin-constructor-hub-tiboxCL
 * Description: Constructor frontend progresivo para WordPress. Permite reemplazar Header, Footer, bloques y páginas por HTML/CSS/JS propio, manteniendo WordPress como backend y compatibilidad con sitios existentes basados en Elementor.
 * Version: 0.3.0-beta.1
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
 * La migración de namespaces/clases internos se realizará de forma compatible
 * y debe quedar registrada en docs/CHANGELOG.md.
 */
define('TIBOX_AI_FRONTEND_VERSION', '0.3.0-beta.1');
define('TIBOX_AI_FRONTEND_FILE', __FILE__);
define('TIBOX_AI_FRONTEND_DIR', plugin_dir_path(__FILE__));
define('TIBOX_AI_FRONTEND_URL', plugin_dir_url(__FILE__));

require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-tibox-ai-frontend.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-component-manager.php';
require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-hub-hybrid-renderer.php';

TIBOX_AI_Frontend::instance();
HUB_Tibox_Component_Manager::instance();
HUB_Tibox_Hybrid_Renderer::instance();
