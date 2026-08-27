<?php
/**
 * Plugin Name: Tibox AI Frontend
 * Plugin URI: https://github.com/wladimick/plugin-wp-web-tiboxCL
 * Description: Frontend liviano para páginas de Tibox diseñadas con IA, manteniendo WordPress como backend y Rank Math/GTM mediante los hooks estándar.
 * Version: 0.1.0
 * Requires PHP: 8.0
 * Author: Tibox
 * Text Domain: tibox-ai-frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TIBOX_AI_FRONTEND_VERSION', '0.1.0');
define('TIBOX_AI_FRONTEND_FILE', __FILE__);
define('TIBOX_AI_FRONTEND_DIR', plugin_dir_path(__FILE__));
define('TIBOX_AI_FRONTEND_URL', plugin_dir_url(__FILE__));

require_once TIBOX_AI_FRONTEND_DIR . 'includes/class-tibox-ai-frontend.php';

TIBOX_AI_Frontend::instance();
